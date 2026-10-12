<?php

namespace App\Http\Controllers\Api\IncomingApi;

use App\Models\User;
use App\Models\Admin;
use App\Models\Contact;
use App\Models\Gateway;
use App\Models\Conversation;
use App\Models\MessageStatus;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Enums\System\ChannelTypeEnum;
use App\Services\System\Communication\ChatService;
use App\Enums\System\ConversationMessageStatusEnum;
use App\Enums\System\Gateway\WhatsAppGatewayTypeEnum;

class NodeWhatsAppWebhookController extends Controller
{
    protected ChatService $chatService;

    public function __construct(ChatService $chatService)
    {
        $this->chatService = $chatService;
    }

    /**
     * Handle incoming messages from Node WhatsApp service
     */
    public function webhook(Request $request): JsonResponse
    {
        try {
            Log::info('Node WhatsApp webhook received', [
                'data' => $request->all(),
                'headers' => $request->headers->all()
            ]);

            // Validate API key
            $apiKey = $request->header('X-API-Key');
            $sessionId = $request->header('X-Session-ID');

            if (!$this->validateApiKey($apiKey)) {
                Log::warning('Invalid API key for Node webhook');
                return response()->json(['success' => false, 'message' => 'Invalid API key'], 401);
            }

            // Get message data
            $from = $request->input('from');
            $messageId = $request->input('messageId');
            $type = $request->input('type');
            $text = $request->input('text');
            $pushName = $request->input('pushName');
            $timestamp = $request->input('timestamp');
            $isGroup = $request->input('isGroup', false);

            // Skip group messages for now
            if ($isGroup) {
                Log::info('Skipping group message', ['from' => $from]);
                return response()->json(['success' => true, 'message' => 'Group messages skipped']);
            }

            // Find the gateway by session ID (gateway name)
            $gateway = Gateway::where('channel', ChannelTypeEnum::WHATSAPP->value)
                ->where('type', WhatsAppGatewayTypeEnum::NODE->value)
                ->where('name', $sessionId)
                ->first();

            if (!$gateway) {
                Log::warning('Gateway not found for session', ['sessionId' => $sessionId]);
                return response()->json(['success' => false, 'message' => 'Gateway not found'], 404);
            }

            // Find or create contact
            $contact = $this->findOrCreateContact($from, $pushName, $gateway->user_id);

            // SECURITY FIX: Find conversation scoped by BOTH contact and user/gateway
            // This ensures complete isolation - even if two users message the same phone number,
            // their conversations are completely separate.
            $conversationQuery = Conversation::where('contact_id', $contact->id)
                ->where('channel', 'whatsapp')
                ->where('gateway_id', $gateway->id);

            // Scope by user - admin gateways have user_id = null
            if ($gateway->user_id) {
                $conversationQuery->where('user_id', $gateway->user_id);
            } else {
                $conversationQuery->whereNull('user_id');
            }

            $conversation = $conversationQuery->first();

            // If no conversation exists, create one scoped to this user/gateway
            if (!$conversation) {
                $conversation = $this->chatService->createOrUpdateConversation(
                    contact: $contact,
                    channel: 'whatsapp',
                    gateway_id: $gateway->id,
                    metaData: ['session_id' => $sessionId, 'gateway_type' => 'node'],
                    user: $gateway->user_id ? User::find($gateway->user_id) : null
                );

                Log::info('New conversation created for incoming message', [
                    'conversationId' => $conversation->id,
                    'contactId' => $contact->id,
                    'gatewayId' => $gateway->id,
                    'userId' => $gateway->user_id
                ]);
            }

            // Check for duplicate message using WhatsApp message ID
            // This prevents duplicate messages when webhook is called multiple times
            $existingMessage = MessageStatus::where('whatsapp_message_id', $messageId)->first();
            if ($existingMessage) {
                Log::info('Duplicate message detected, skipping', [
                    'messageId' => $messageId,
                    'existingMessageId' => $existingMessage->message_id
                ]);

                // Still update conversation timestamp for activity tracking
                $this->chatService->updateConversation($conversation);

                return response()->json([
                    'success' => true,
                    'message' => 'Duplicate message skipped',
                    'conversation_id' => $conversation->id
                ]);
            }

            // Create message if there's text content
            $message = null;
            if ($text) {
                $message = $this->chatService->createMessage(
                    conversation: $conversation,
                    messageContent: $text,
                    user: $gateway->user_id ? User::find($gateway->user_id) : null
                );
            }

            // Handle media messages
            if (in_array($type, ['image', 'audio', 'video', 'document', 'sticker']) && !$message) {
                // Create placeholder message for media
                $mediaLabel = ucfirst($type) . ' message';
                $message = $this->chatService->createMessage(
                    conversation: $conversation,
                    messageContent: $text ?: "[$mediaLabel]",
                    user: $gateway->user_id ? User::find($gateway->user_id) : null
                );
            }

            if ($message) {
                // Store raw message data
                $message->update([
                    'meta_data' => $request->input('rawMessage', [])
                ]);

                // Create message participants
                $this->chatService->createMessageParticipants(
                    message: $message,
                    senderId: $contact->id,
                    senderType: Contact::class,
                    receiverId: $gateway->user_id ?? 1,
                    receiverType: $gateway->user_id ? User::class : Admin::class
                );

                // Create message status
                $this->chatService->createMessageStatus(
                    message: $message,
                    status: ConversationMessageStatusEnum::DELIVERED,
                    whatsappMessageId: $messageId,
                    additionalData: [
                        'session_id' => $sessionId,
                        'gateway_type' => 'node',
                        'timestamp' => $timestamp
                    ]
                );

                Log::info('Incoming Node WhatsApp message stored', [
                    'messageId' => $messageId,
                    'conversationId' => $conversation->id,
                    'contactId' => $contact->id
                ]);
            }

            // Update conversation timestamp
            $this->chatService->updateConversation($conversation);

            // Fire automation triggers (non-blocking)
            if ($message) {
                try {
                    $triggerHandler = app(\App\Services\Automation\TriggerHandlerService::class);

                    // Trigger chatbot / auto-reply workflows
                    $triggerHandler->handleContactReplied($contact, 'whatsapp', [
                        'message_id'   => $message->id,
                        'message_body' => $message->message,
                        'gateway_type' => 'node',
                        'gateway_id'   => $gateway->id,
                        'session_id'   => $sessionId,
                    ]);

                    // Trigger new-contact workflows if just created
                    if ($contact->wasRecentlyCreated) {
                        $triggerHandler->handleNewContact($contact);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Automation trigger failed (non-blocking)', ['error' => $e->getMessage()]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => $message ? 'Message received and stored' : 'Duplicate message skipped',
                'conversation_id' => $conversation->id
            ]);

        } catch (\Exception $e) {
            Log::error('Node WhatsApp webhook error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process message',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate API key
     */
    protected function validateApiKey(?string $apiKey): bool
    {
        if (!$apiKey) {
            return false;
        }

        $configuredKey = env('WP_API_KEY', '');

        // If no key is configured, allow all requests (development mode)
        if (empty($configuredKey)) {
            return true;
        }

        return $apiKey === $configuredKey;
    }

    /**
     * Find or create contact by WhatsApp number
     *
     * SECURITY: Each user has their own isolated contacts. A contact with the same
     * phone number can exist for multiple users - this is intentional to ensure
     * complete user isolation and prevent cross-user message leakage.
     */
    protected function findOrCreateContact(string $whatsappNumber, ?string $pushName, ?int $userId): Contact
    {
        // Clean the phone number
        $whatsappNumber = preg_replace('/[^0-9]/', '', $whatsappNumber);

        // Generate possible phone number formats for matching
        // WhatsApp sends: 8801717193953 (country code + number)
        // User might have stored: 01717193953, 1717193953, or 8801717193953
        $possibleFormats = $this->generatePhoneFormats($whatsappNumber);

        // SECURITY FIX: Always scope contact lookup by user_id to ensure user isolation
        // Each user has their own set of contacts - same phone number can exist for multiple users
        $contactQuery = Contact::where(function ($query) use ($possibleFormats) {
            foreach ($possibleFormats as $format) {
                $query->orWhere('whatsapp_contact', $format)
                      ->orWhere('sms_contact', $format);
            }
        });

        // Scope by user_id - admin (null) and each user have separate contact pools
        if ($userId) {
            $contactQuery->where('user_id', $userId);
        } else {
            $contactQuery->whereNull('user_id');
        }

        $contact = $contactQuery->first();

        if (!$contact) {
            // Create new contact with the original number - scoped to this user
            $contact = Contact::create([
                'whatsapp_contact' => $whatsappNumber,
                'sms_contact' => $whatsappNumber,
                'first_name' => $pushName ?: 'Unknown',
                'user_id' => $userId,
                'status' => 1,
            ]);

            Log::info('New contact created from Node WhatsApp', [
                'contactId' => $contact->id,
                'whatsapp' => $whatsappNumber,
                'userId' => $userId
            ]);
        } elseif ($pushName && empty($contact->first_name)) {
            // Update contact name if we have pushName and contact doesn't have a name
            $contact->update(['first_name' => $pushName]);
        }

        return $contact;
    }

    /**
     * Generate possible phone number formats for matching
     * Handles variations like with/without country code, leading zeros, etc.
     */
    protected function generatePhoneFormats(string $phone): array
    {
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
