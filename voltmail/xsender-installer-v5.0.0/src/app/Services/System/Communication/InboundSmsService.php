<?php

namespace App\Services\System\Communication;

use App\Models\Contact;
use App\Models\DispatchLog;
use App\Models\Gateway;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * v5.0-S3 — Inbound SMS reply capture.
 *
 * Called from Http\Controllers\Webhook\InboundSmsController after the
 * per-provider adapter normalises the payload into a canonical shape:
 *   {
 *     from        string  E.164 or local sender number (required)
 *     to          string  the SMS gateway number that received the reply
 *     text        string  message body
 *     message_id  string  provider's message id (for dedupe on retry)
 *     provider    string  which adapter dispatched (audit only)
 *   }
 *
 * Matching:
 *   1. Contact  — search by phone-format variations against sms_contact
 *                 (uses generatePhoneFormats() same as WebhookController does
 *                 for WhatsApp) so +1-555-0100 / 15550100 / 555-0100 all match.
 *   2. Gateway  — find SMS gateway whose address matches the `to` number
 *                 (or falls back to the contact's most recent outbound SMS
 *                 dispatch's gateway).
 *   3. User     — from gateway.user_id, or from the most recent outbound
 *                 DispatchLog for this contact.
 *
 * If contact doesn't exist we CREATE one (unknown-sender inbounds go into
 * the inbox under a placeholder contact — better than dropping them).
 *
 * Reuses ChatService for every conversation/message write, matching the
 * v5.0-S2 InboundEmailService pattern. Zero new persistence code.
 */
class InboundSmsService
{
    /**
     * Ingest a normalised inbound SMS payload.
     *
     * @return array { ok: bool, reason?: string, conversation_id?: int, message_id?: int }
     */
    public function ingest(array $payload, ChatService $chat): array
    {
        $from       = trim((string) ($payload['from']       ?? ''));
        $to         = trim((string) ($payload['to']         ?? ''));
        $text       = (string)      ($payload['text']       ?? '');
        $providerId = (string)      ($payload['message_id'] ?? '');
        $provider   = (string)      ($payload['provider']   ?? 'generic');

        if ($from === '' || $text === '') {
            return ['ok' => false, 'reason' => 'missing_from_or_text'];
        }

        // Dedupe first — SMS providers retry on 5xx.
        if ($providerId !== '' && $this->alreadyIngested($providerId)) {
            return ['ok' => true, 'reason' => 'duplicate_provider_id_skipped'];
        }

        $ctx = $this->resolveContext($from, $to);

        // Never drop a real customer reply because the contact record is
        // missing. Create a shell contact so the message lands in the inbox.
        if (!$ctx['contact']) {
            $ctx['contact'] = Contact::create([
                'sms_contact' => $this->normaliseSender($from),
                'first_name'  => translate('Unknown'),
                'user_id'     => $ctx['user']?->id,
                'status'      => 1,
            ]);
        }

        $conversation = $chat->createOrUpdateConversation(
            contact:    $ctx['contact'],
            channel:    'sms',
            gateway_id: $ctx['gateway_id'],
            metaData: [
                'ingest_source'   => 'inbound_sms_webhook',
                'provider'        => $provider,
                'provider_msg_id' => $providerId,
                'from'            => $from,
                'to'              => $to,
            ],
            user: $ctx['user']
        );

        $message = $chat->createMessage(
            conversation:   $conversation,
            messageContent: $text,
            user:           $ctx['user'],
        );

        $chat->createMessageParticipants(
            message:      $message,
            senderId:     $ctx['contact']->id,
            senderType:   Contact::class,
            receiverId:   $ctx['user']?->id ?? 0,
            receiverType: User::class,
        );

        $chat->updateConversation($conversation);

        // v5.0-S6 — fire the "contact_replied" automation trigger so any
        // customer-configured workflows / chatbot rules react to SMS replies
        // the same way they already react to WhatsApp + email replies.
        // Non-blocking: workflow failures never break inbound ingestion.
        try {
            app(\App\Services\Automation\TriggerHandlerService::class)
                ->handleContactReplied($ctx['contact'], 'sms', [
                    'message_id'   => $message->id,
                    'message_body' => $message->message,
                    'gateway_id'   => $ctx['gateway_id'],
                    'from'         => $from,
                    'provider'     => $provider,
                ]);
            if ($ctx['contact']->wasRecentlyCreated ?? false) {
                app(\App\Services\Automation\TriggerHandlerService::class)
                    ->handleNewContact($ctx['contact']);
            }
        } catch (\Throwable $e) {
            Log::warning('Automation trigger (SMS inbound) failed', ['error' => $e->getMessage()]);
        }

        return [
            'ok'              => true,
            'conversation_id' => $conversation->id,
            'message_id'      => $message->id,
        ];
    }

    // ── internal ─────────────────────────────────────────────────────────

    /**
     * Resolve contact + user + gateway from the inbound (from, to) pair.
     */
    protected function resolveContext(string $from, string $to): array
    {
        $formats = $this->generatePhoneFormats($from);

        // 1. Contact by any phone-format variation on sms_contact
        $contact = Contact::where(function ($q) use ($formats) {
            foreach ($formats as $f) {
                $q->orWhere('sms_contact', $f);
            }
        })->first();

        // 2. Gateway match by the destination number (`to`)
        $gateway = null;
        if ($to !== '') {
            $toFormats = $this->generatePhoneFormats($to);
            $gateway = Gateway::where('channel', 'sms')
                ->where(function ($q) use ($toFormats) {
                    foreach ($toFormats as $f) {
                        $q->orWhere('address', $f);
                    }
                })->first();
        }

        // 3. Fallback: gateway from the contact's most recent outbound SMS
        if (!$gateway && $contact) {
            $lastOut = DispatchLog::where('contact_id', $contact->id)
                ->where('type', 'sms')
                ->latest('id')
                ->first();
            if ($lastOut && $lastOut->gatewayable_id) {
                $gateway = Gateway::find($lastOut->gatewayable_id);
            }
        }

        // 4. User: gateway.user_id → else last outbound dispatch.user_id
        $user = null;
        if ($gateway && $gateway->user_id) {
            $user = User::find($gateway->user_id);
        }
        if (!$user && $contact) {
            $lastOut = DispatchLog::where('contact_id', $contact->id)
                ->where('type', 'sms')
                ->latest('id')
                ->first();
            if ($lastOut && $lastOut->user_id) {
                $user = User::find($lastOut->user_id);
            } elseif ($contact->user_id) {
                $user = User::find($contact->user_id);
            }
        }

        return [
            'contact'    => $contact,
            'user'       => $user,
            'gateway_id' => $gateway?->id,
        ];
    }

    /**
     * Same shape as WebhookController::generatePhoneFormats() used for
     * WhatsApp inbound matching — covers +CC / CC / 0-prefix / raw-digits
     * variants so E.164 and local formats both hit.
     */
    protected function generatePhoneFormats(string $phone): array
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);
        if ($digits === '') return [$phone];

        $variants = [
            $phone,
            $digits,
            '+' . $digits,
            ltrim($digits, '0'),
            '0' . ltrim($digits, '0'),
        ];

        // De-dupe + strip empties
        return array_values(array_unique(array_filter($variants, fn ($v) => $v !== '')));
    }

    /**
     * Store sender number in the tidiest available shape (prefer +CC form).
     */
    protected function normaliseSender(string $from): string
    {
        $digits = preg_replace('/[^0-9]/', '', $from);
        return $digits === '' ? $from : '+' . $digits;
    }

    /**
     * Dedupe key on the provider's message id — providers retry on 5xx.
     */
    protected function alreadyIngested(string $providerMsgId): bool
    {
        try {
            return \App\Models\Conversation::where('channel', 'sms')
                ->whereJsonContains('meta_data->provider_msg_id', $providerMsgId)
                ->exists();
        } catch (\Throwable $e) {
            Log::debug('InboundSms dedupe check skipped: ' . $e->getMessage());
            return false;
        }
    }
}
