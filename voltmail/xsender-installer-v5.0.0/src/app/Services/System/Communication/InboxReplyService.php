<?php

namespace App\Services\System\Communication;

use App\Enums\System\ConversationMessageStatusEnum;
use App\Http\Utility\SendMail;
use App\Http\Utility\SendSMS;
use App\Jobs\SendWhatsappConversationMessage;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Gateway;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * v5.0-S4 — Cross-channel reply dispatcher for the Unified Inbox.
 *
 * The inbox thread now has a reply box that posts to the InboxController's
 * reply endpoint, which delegates here. This class:
 *
 *   1. Writes the outbound Message + MessageParticipant + MessageStatus
 *      via the existing ChatService (same code path the WhatsApp chat
 *      already uses — proven, no new persistence).
 *   2. Routes to the per-channel sender by channel:
 *        whatsapp → SendWhatsappConversationMessage job (already routes
 *                    Cloud API vs Node internally)
 *        email    → App\Http\Utility\SendMail::send() (proven at 8 sites)
 *        sms      → App\Http\Utility\SendSMS::send()
 *   3. Marks final MessageStatus SENT / FAILED so the inbox UI reflects
 *      delivery.
 *
 * Returns the fresh Message model so the controller can hand it straight
 * back to the browser and the inbox JS appends it to the thread live.
 */
class InboxReplyService
{
    public function __construct(
        private ChatService $chat,
    ) {}

    /**
     * Send a reply on any channel.
     *
     * @throws \InvalidArgumentException on unsupported channel or missing gateway
     * @throws \RuntimeException         on send failure with underlying reason
     */
    public function reply(Conversation $conversation, string $body, ?User $user, ?array $media = null): Message
    {
        $body = trim($body);
        if ($body === '' && empty($media)) {
            throw new \InvalidArgumentException(translate('Message body or media is required.'));
        }

        $contact = $conversation->contact;
        if (!$contact) {
            throw new \InvalidArgumentException(translate('Conversation is missing a contact.'));
        }

        // 1. Persist the outbound message + participants + PENDING status.
        //    Every channel goes through the same ChatService write path,
        //    so the inbox thread renders consistently regardless of channel.
        $message = $this->chat->createMessage(
            conversation:   $conversation,
            messageContent: $body ?: '',
            user:           $user,
            fileInfo:       $media,
        );

        $this->chat->createMessageParticipants(
            message:      $message,
            senderId:     $user?->id ?? 0,
            senderType:   User::class,
            receiverId:   $contact->id,
            receiverType: Contact::class,
        );

        $messageStatus = $this->chat->createMessageStatus(
            message: $message,
            status:  ConversationMessageStatusEnum::PENDING
        );

        // 2. Route to the per-channel sender. Every branch reuses an existing
        //    utility class — no new outbound send logic in this service.
        try {
            $channelValue = $conversation->channel; // 'email' | 'sms' | 'whatsapp'
            $gateway = $conversation->gateway;

            switch ($channelValue) {
                case 'whatsapp':
                    if (!$gateway) throw new \RuntimeException(translate('WhatsApp conversation has no gateway.'));
                    if (empty($contact->whatsapp_contact)) throw new \RuntimeException(translate('Contact has no WhatsApp number.'));

                    // Dispatch sync so the message status flips synchronously
                    // and the inbox JS can render the final delivery state.
                    SendWhatsappConversationMessage::dispatchSync(
                        to:            $contact->whatsapp_contact,
                        messageStatus: $messageStatus,
                        gateway:       $gateway,
                        mediaUrl:      $media[0] ?? null,
                        mediaType:     null,
                    );
                    break;

                case 'email':
                    $emailGateway = $gateway ?: $this->pickEmailGateway($user);
                    if (!$emailGateway) throw new \RuntimeException(translate('No email gateway available to send from.'));
                    if (empty($contact->email_contact)) throw new \RuntimeException(translate('Contact has no email address.'));

                    // Reuse the app's canonical SendMail utility — same class
                    // that powers verification emails, password resets, etc.
                    $sendMail = new SendMail();
                    $subject = $this->deriveEmailSubject($conversation);
                    $ok = $sendMail->send(
                        gateway:  $emailGateway,
                        to:       $contact->email_contact,
                        subject:  $subject,
                        mailBody: $body,
                    );
                    if (!$ok) throw new \RuntimeException($sendMail->getLastError() ?? translate('Email send failed.'));

                    $this->chat->createMessageStatus(
                        message: $message,
                        status:  ConversationMessageStatusEnum::SENT,
                    );
                    break;

                case 'sms':
                    $smsGateway = $gateway ?: $this->pickSmsGateway($user);
                    if (!$smsGateway) throw new \RuntimeException(translate('No SMS gateway available to send from.'));
                    if (empty($contact->sms_contact)) throw new \RuntimeException(translate('Contact has no SMS phone number.'));

                    $sendSms = new SendSMS();
                    $ok = $sendSms->send(
                        provider:    (string) ($smsGateway->type ?? ''),
                        to:          $contact->sms_contact,
                        gateway:     $smsGateway,
                        dispatchLog: [],   // no DispatchLog row for inbox replies
                        message:     $body,
                    );
                    if (!$ok) throw new \RuntimeException(translate('SMS send failed via provider.'));

                    $this->chat->createMessageStatus(
                        message: $message,
                        status:  ConversationMessageStatusEnum::SENT,
                    );
                    break;

                default:
                    throw new \InvalidArgumentException(translate('Unsupported channel: ') . $channelValue);
            }
        } catch (\Throwable $e) {
            Log::warning('InboxReplyService failure', [
                'conversation_id' => $conversation->id,
                'channel'         => $conversation->channel,
                'error'           => $e->getMessage(),
            ]);
            $this->chat->createMessageStatus(
                message: $message,
                status:  ConversationMessageStatusEnum::FAILED,
                additionalData: ['error' => $e->getMessage()],
            );
            throw $e;
        }

        // 3. Bump conversation timestamp so the row jumps to the top of the list.
        $conversation->update(['last_message_at' => now()]);

        return $message->fresh(['statuses', 'participants.participantable']);
    }

    // ── helpers ─────────────────────────────────────────────────────────

    /**
     * Fallback: pick the user's default email gateway when the conversation
     * doesn't have one attached (older conversation rows may lack gateway_id).
     */
    protected function pickEmailGateway(?User $user): ?Gateway
    {
        $q = Gateway::where('channel', 'email')->where('status', 'active');
        if ($user) {
            $g = (clone $q)->where('user_id', $user->id)->where('is_default', 1)->first()
                ?? (clone $q)->where('user_id', $user->id)->first();
            if ($g) return $g;
        }
        return $q->whereNull('user_id')->where('is_default', 1)->first()
            ?? $q->whereNull('user_id')->first();
    }

    protected function pickSmsGateway(?User $user): ?Gateway
    {
        $q = Gateway::where('channel', 'sms')->where('status', 'active');
        if ($user) {
            $g = (clone $q)->where('user_id', $user->id)->where('is_default', 1)->first()
                ?? (clone $q)->where('user_id', $user->id)->first();
            if ($g) return $g;
        }
        return $q->whereNull('user_id')->where('is_default', 1)->first()
            ?? $q->whereNull('user_id')->first();
    }

    /**
     * Derive an email reply subject. Prefer the original conversation subject
     * from meta_data (set by InboundEmailService); prefix with "Re: " once.
     */
    protected function deriveEmailSubject(Conversation $conversation): string
    {
        $original = trim((string) (($conversation->meta_data['subject'] ?? '') ?: ''));
        if ($original === '') {
            $name = trim(($conversation->contact->first_name ?? '') . ' ' . ($conversation->contact->last_name ?? ''));
            return translate('Reply from ') . ($name ?: translate('support'));
        }
        return stripos($original, 're:') === 0 ? $original : 'Re: ' . $original;
    }
}
