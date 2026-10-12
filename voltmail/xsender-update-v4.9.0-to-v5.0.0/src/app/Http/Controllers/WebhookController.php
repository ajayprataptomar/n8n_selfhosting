<?php

namespace App\Http\Controllers;

use App\Enums\ServiceType;
use App\Enums\System\ChannelTypeEnum;
use App\Enums\System\CommunicationStatusEnum;
use App\Enums\System\ConversationMessageStatusEnum;
use App\Models\Admin;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\DispatchLog;
use App\Models\Message;
use App\Models\MessageParticipant;
use App\Models\MessageStatus;
use App\Models\PostWebhookLog;
use App\Models\User;
use App\Models\WhatsappLog;
use App\Service\Admin\Dispatch\WhatsAppService;
use App\Services\System\Communication\ChatService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function postWebhook(Request $request, WhatsAppService $whatsAppService, ChatService $chatService) {
        
        try {

            if ($request->isMethod('get')) {

                $apiKey     = site_settings("webhook_verify_token");
                $query      = $request->query();
                $hubMode    = $query["hub_mode"] ?? null;
                $hubToken   = $query["hub_verify_token"] ?? null;
                $challenge  = $query["hub_challenge"] ?? null;
                $usersCount = User::where("webhook_token", $hubToken)->count();
                $configExists = \App\Models\MetaConfiguration::where("webhook_verify_token", $hubToken)->exists();

                if ($hubMode && $hubToken && $hubMode === 'subscribe' && ($hubToken === $apiKey || $usersCount > 0 || $configExists)) {

                    return response($challenge, 200)->header('Content-Type', 'text/plain');
                } else {
                    throw new Exception("Invalid Request");
                }
            }

            $payload = $request->all();
            $user = User::where('uid', $request->input('uid'))->first();

            $webhookLog = PostWebhookLog::create([
                'user_id' => $user ? $user->id : null,
                'uid' => $request->input('uid'),
                'webhook_response' => json_encode($payload),
                'webhook_type' => $this->determineWebhookType($payload),
                'whatsapp_message_id' => $this->extractWamid($payload),
                'processed' => false,
            ]);

            $response = json_decode($webhookLog->webhook_response, true);
            $idFromRequest = Arr::get($response, 'entry.0.changes.0.value.statuses.0.id');

            if ($idFromRequest) {

               $whatsappLog = DispatchLog::where("type", ChannelTypeEnum::WHATSAPP->value)
                                            ->get()
                                            ->first(function ($log) use ($idFromRequest) {
                                                $messages = $log->response_message['messages'] ?? [];
                                                return collect($messages)->contains('id', $idFromRequest);
                                            });

                if ($whatsappLog) {

                    $errors = Arr::get($response, 'entry.0.changes.0.value.statuses.0.errors', []);
                    if (!empty($errors)) {
                        $whatsappLog->status = CommunicationStatusEnum::FAIL;
                        $whatsAppService->addedCreditLog($whatsappLog, $errors[0]['message']);
                        $whatsappLog->save();
                    } else {
                        $status = Arr::get($response, 'entry.0.changes.0.value.statuses.0.status');
                        if ($status == 'failed') {
                            $whatsappLog->status = CommunicationStatusEnum::FAIL;
                            $whatsAppService->addedCreditLog($whatsappLog, "Cloud API couldn't send the message.");
                        } elseif ($status == 'sent' || $status == 'read') {
                            $meta_data = $whatsappLog->meta_data;
                            $meta_data['delivered_at'] = Carbon::now()->toDayDateTimeString();
                            $whatsappLog->meta_data = $meta_data;
                            $whatsappLog->status = CommunicationStatusEnum::DELIVERED;
                        }
                        $whatsappLog->save();
                        $webhookLog->dispatch_log_id = $whatsappLog->id;
                    }
                    $webhookLog->processed = true;
                    $webhookLog->save();
                } else {
                    
                    $messageStatus = MessageStatus::where('whatsapp_message_id', $idFromRequest)->first();
                    if ($messageStatus) {
                        $message = $messageStatus->message;
                        $newStatus = Arr::get($response, 'entry.0.changes.0.value.statuses.0.status');
                        $timestamp = Arr::get($response, 'entry.0.changes.0.value.statuses.0.timestamp');
                        $errors = Arr::get($response, 'entry.0.changes.0.value.statuses.0.errors', []);
                        $additionalData = array_filter([
                            'errors' => $errors,
                            'conversation' => Arr::get($response, 'entry.0.changes.0.value.statuses.0.conversation'), // Deprecated in Graph API v24.0+
                            'pricing' => Arr::get($response, 'entry.0.changes.0.value.statuses.0.pricing'),
                        ], fn($v) => !is_null($v));

                        $chatService->createMessageStatus(
                            message: $message,
                            status: ConversationMessageStatusEnum::from($newStatus),
                            whatsappMessageId: $idFromRequest,
                            additionalData: $additionalData
                        );

                        if ($timestamp) {
                            $messageStatus->update(['status_timestamp' => Carbon::createFromTimestamp($timestamp)]);
                        }
                    }
                    
                }
                $webhookLog->processed = true;
                $webhookLog->save();
                return;
            } elseif ($webhookLog->webhook_type === 'message_received') {
                $this->processIncomingMessage($response, $webhookLog, $chatService, $user);
            }

            return response()->json(['success' => true], 200);
        } catch (Exception $e) {
            if (isset($webhookLog) && $webhookLog) {
                $webhookLog->processing_error = $e->getMessage();
                $webhookLog->save();
            }
            Log::error('Webhook processing error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    protected function determineWebhookType(array $payload): string
    {
        $changes = Arr::get($payload, 'entry.0.changes.0.value');
        if (isset($changes['messages'])) {
            return 'message_received';
        } elseif (isset($changes['statuses'])) {
            return 'status_update';
        }
        return 'unknown';
    }

    protected function extractWamid(array $payload): ?string
    {
        return Arr::get($payload, 'entry.0.changes.0.value.statuses.0.id') ??
               Arr::get($payload, 'entry.0.changes.0.value.messages.0.id');
    }

    protected function processIncomingMessage(array $response, PostWebhookLog $webhookLog, ChatService $chatService, User|null $user = null)
    {
        Log::info("Webhook Response", ['response' => $response]);
        $changes        = Arr::get($response, 'entry.0.changes.0.value');
        $messageData    = Arr::get($changes, "messages.0");
        $from           = ltrim(Arr::get($messageData, 'from', ""), '+');
        $wamId          = Arr::get($messageData, "id");
        $type           = Arr::get($messageData, "type");
        $content        = Arr::get($messageData, "text.body") ?? Arr::get($messageData, "$type.caption");
        $timestamp      = Carbon::createFromTimestamp(Arr::get($messageData, "timestamp"));
        $contactName    = Arr::get($changes, 'contacts.0.profile.name');
        $phoneNumberId  = Arr::get($changes, "metadata.phone_number_id");

        // ------------------------------------------------------------------
        // Resolve the Cloud API gateway + owning user FIRST, before we touch
        // Contact/Conversation records. Cloud API webhooks hit a generic URL
        // with no `uid` query param, so $user was null on entry. Without
        // resolution here, the contact + conversation would be created under
        // user_id = null (admin scope), invisible to the actual owning user
        // in their Chats UI. Bug #1 in v4.7.0.x: the query used a
        // `credentials->phone_number_id` JSON path that no code path ever
        // writes to (phone_number_id is stored in the real column and in
        // meta_data->phone_number_id). That silently returned null on every
        // Cloud API reply.
        // ------------------------------------------------------------------
        $gateway   = null;
        $gatewayId = null;
        if ($phoneNumberId) {
            $gateway = \App\Models\Gateway::where('channel', ChannelTypeEnum::WHATSAPP->value)
                ->where('type', \App\Enums\System\Gateway\WhatsAppGatewayTypeEnum::CLOUD->value)
                ->where(function ($q) use ($phoneNumberId) {
                    $q->where('phone_number_id', $phoneNumberId)
                      ->orWhereJsonContains('meta_data->phone_number_id', $phoneNumberId);
                })
                ->first();
            if ($gateway) {
                $gatewayId = $gateway->id;
                if (!$user && $gateway->user_id) {
                    $user = User::find($gateway->user_id);
                }
            }
        }

        // Fallback: try to reconstruct via the outbound dispatch log so we
        // can credit replies to campaigns that were sent through a gateway
        // whose phone_number_id lookup failed (deleted gateway, imported data).
        if (!$gateway) {
            $logQuery = DispatchLog::where("type", ChannelTypeEnum::WHATSAPP->value);
            if ($user) {
                $logQuery->where("user_id", $user->id);
            }
            $whatsappLog = $logQuery->get()
                                    ->first(function ($log) use ($from) {
                                        $messages = $log->response_message['contacts'] ?? [];
                                        return collect($messages)->contains('wa_id', $from);
                                    });
            if ($whatsappLog) {
                $gatewayId = $whatsappLog->gatewayable_id;
                if ($gatewayId) {
                    $gateway = \App\Models\Gateway::find($gatewayId);
                }
                if (!$user) $user = $whatsappLog->user;
            }
        }

        // Scope contact lookup to the resolved user when we have one so we
        // don't attach the reply to a namesake contact in a different account.
        $possibleFormats = $this->generatePhoneFormats($from);
        $contactQuery = Contact::where(function ($query) use ($possibleFormats) {
            foreach ($possibleFormats as $format) {
                $query->orWhere('whatsapp_contact', $format)
                      ->orWhere('sms_contact', $format);
            }
        });
        if ($user) {
            $contactQuery->where('user_id', $user->id);
        } else {
            $contactQuery->whereNull('user_id');
        }
        $contact = $contactQuery->first();

        if (!$contact) {
            $contact = Contact::create([
                'whatsapp_contact' => $from,
                'sms_contact'      => $from,
                'first_name'       => $contactName ?: 'Unknown',
                'user_id'          => $user?->id ?? $webhookLog->user_id,
                'status'           => 1,
            ]);
        } elseif ($contactName && empty($contact->first_name)) {
            // Update contact name if we have name and contact doesn't have one
            $contact->update(['first_name' => $contactName]);
        }

        // Find existing conversation first - check for conversation with this gateway,
        // then check for admin conversation (user_id = null). This ensures replies go to the correct conversation.
        $conversation = Conversation::where('contact_id', $contact->id)
            ->where('channel', 'whatsapp')
            ->where('gateway_id', $gatewayId)
            ->first();

        // If no conversation with this gateway, check for any admin conversation with this contact
        if (!$conversation && $gatewayId) {
            $conversation = Conversation::where('contact_id', $contact->id)
                ->where('channel', 'whatsapp')
                ->whereNull('user_id')
                ->first();

            // Update gateway_id if found
            if ($conversation && !$conversation->gateway_id) {
                $conversation->update(['gateway_id' => $gatewayId]);
            }
        }

        // If still no conversation, create one based on user
        if (!$conversation) {
            $conversation = $chatService->createOrUpdateConversation(
                contact: $contact,
                channel: 'whatsapp',
                gateway_id: $gatewayId,
                metaData: ['phone_number_id' => Arr::get($changes, "metadata.phone_number_id"), 'gateway_type' => 'cloud_api'],
                user: $user
            );
        }

        $message = null;
        if ($content) {
            $message = $chatService->createMessage(
                conversation: $conversation,
                messageContent: $content,
                user: $user
            );
        }

        // Process media files if present
        if (in_array($type, ['image', 'audio', 'video', 'document', 'sticker'])) {
            $message = $chatService->processWhatsAppMedia(
                messageData: $messageData,
                message: $message,
                conversation: $conversation,
                gatewayId: $gatewayId,
                user: $user
            );
        }

        if ($message) {
            $message->update(['meta_data' => $messageData]);
            $chatService->createMessageParticipants(
                message: $message,
                senderId: $contact->id,
                senderType: Contact::class,
                receiverId: $conversation->user_id ?? 1,
                receiverType: $conversation->user_id ? User::class : Admin::class
            );

            $chatService->createMessageStatus(
                message: $message,
                status: ConversationMessageStatusEnum::DELIVERED,
                whatsappMessageId: $wamId,
                additionalData: ['phone_number_id' => Arr::get($changes, "metadata.phone_number_id")]
            );
        }

        $chatService->updateConversation($conversation);
        $webhookLog->processed = true;
        $webhookLog->save();

        // Fire automation triggers (non-blocking)
        if ($message) {
            try {
                $triggerHandler = app(\App\Services\Automation\TriggerHandlerService::class);

                $triggerHandler->handleContactReplied($contact, 'whatsapp', [
                    'message_id'      => $message->id,
                    'message_body'    => $message->message,
                    'gateway_type'    => 'cloud_api',
                    'gateway_id'      => $gateway?->id,
                    'phone_number_id' => Arr::get($changes, 'metadata.phone_number_id'),
                ]);

                if ($contact->wasRecentlyCreated) {
                    $triggerHandler->handleNewContact($contact);
                }
            } catch (\Throwable $e) {
                Log::warning('Automation trigger failed (non-blocking)', ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Generate possible phone number formats for matching
     * Handles variations like with/without country code, leading zeros, etc.
     */
    protected function generatePhoneFormats(string $phone): array
    {
        // Clean the phone number
        $phone = preg_replace('/[^0-9]/', '', $phone);
        $formats = [$phone];

        // Common country codes to try stripping
        $countryCodes = ['880', '91', '1', '44', '61', '81', '86', '971', '966', '92', '234', '254', '27'];

        foreach ($countryCodes as $code) {
            if (str_starts_with($phone, $code)) {
                $withoutCode = substr($phone, strlen($code));
                $formats[] = $withoutCode;
                $formats[] = '0' . $withoutCode; // With leading zero
                break; // Only strip one country code
            }
        }

        // If phone starts with 0, also try without the leading 0
        if (str_starts_with($phone, '0')) {
            $formats[] = substr($phone, 1);
        }

        // Try adding common country codes if phone looks like a local number
        if (strlen($phone) <= 11) {
            foreach (['880', '91', '1'] as $code) {
                $formats[] = $code . $phone;
                $formats[] = $code . ltrim($phone, '0');
            }
        }

        return array_unique($formats);
    }
}
