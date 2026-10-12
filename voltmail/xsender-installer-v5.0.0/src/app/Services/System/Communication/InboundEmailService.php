<?php

namespace App\Services\System\Communication;

use App\Models\CampaignDispatch;
use App\Models\Contact;
use App\Models\DispatchLog;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * v5.0-S2 — Inbound email reply capture.
 *
 * Companion to CampaignDispatchService::injectInboundReplyToken(). The
 * token is embedded in outgoing Reply-To headers so any customer reply
 * hits an MX-forwarded webhook (SendGrid Inbound Parse, Mailgun Routes,
 * Postmark Inbound, generic postfix → curl). We DO NOT poll IMAP —
 * zero customer credentials stored, works with every SMTP provider,
 * realtime instead of polling delay.
 *
 * Token payload (base64url-encoded JSON):
 *   { cd: <CampaignDispatch id>, dl: <DispatchLog id?>, u: <user_id?>, t: <unix> }
 *
 * We ONLY require ONE of `cd` or `dl` — either dispatch model tells us
 * which contact + user + campaign the reply belongs to.
 *
 * The controller `Http\Controllers\Webhook\InboundEmailController` calls
 * this service. Reuses App\Services\System\Communication\ChatService for
 * conversation + message creation — no new persistence code.
 */
class InboundEmailService
{
    /**
     * Build a Reply-To address for an outbound email.
     *
     * Callers: CampaignDispatchService::sendEmail (unified path) and
     * SendMail utility (legacy DispatchLog path). The domain is read from
     * config('setting.inbound.reply_domain') — customer sets it in
     * Admin → Settings → General → Inbound Email Domain.
     *
     * @param  int|null  $campaignDispatchId  UnifiedCampaign / CampaignDispatch id
     * @param  int|null  $dispatchLogId       Legacy DispatchLog id
     * @param  int|null  $userId              Account owning the campaign
     * @return string|null  Full Reply-To address, or null if not configured
     */
    public function makeReplyToAddress(?int $campaignDispatchId = null, ?int $dispatchLogId = null, ?int $userId = null): ?string
    {
        $domain = (string) (site_settings('inbound_reply_domain') ?: config('setting.inbound.reply_domain', ''));
        if ($domain === '') {
            return null; // feature not configured yet — sender should skip the rewrite
        }

        $token = $this->encodeToken([
            'cd' => $campaignDispatchId,
            'dl' => $dispatchLogId,
            'u'  => $userId,
            't'  => time(),
        ]);

        return "reply-{$token}@{$domain}";
    }

    /**
     * Parse an inbound email payload and create a Conversation + Message
     * in the Unified Inbox. Idempotent — called from a webhook that might
     * retry on 5xx, so we dedupe by Message-ID header when possible.
     *
     * @param  array  $payload  Normalised payload from provider adapter.
     *                          Expected keys:
     *                            - to           string  the reply-*@domain address (required)
     *                            - from         string  sender address (required)
     *                            - from_name    string  optional display name
     *                            - subject      string  optional
     *                            - text         string  plain-text body (preferred)
     *                            - html         string  html body (fallback)
     *                            - message_id   string  RFC 5322 Message-ID (for dedupe)
     *                            - received_at  int     unix timestamp (optional)
     * @return array  { ok: bool, reason?: string, conversation_id?: int, message_id?: int }
     */
    public function ingest(array $payload, ChatService $chatService): array
    {
        // 1. Extract token from the recipient address (reply-{token}@domain).
        $to    = (string) ($payload['to'] ?? '');
        $token = $this->extractToken($to);
        if (!$token) {
            return ['ok' => false, 'reason' => 'no_token_in_recipient', 'to' => $to];
        }
        $data = $this->decodeToken($token);
        if (!$data) {
            return ['ok' => false, 'reason' => 'invalid_token'];
        }

        // 2. Resolve contact + user + gateway via the referenced dispatch.
        $ctx = $this->resolveContext($data);
        if (!$ctx['contact']) {
            return ['ok' => false, 'reason' => 'contact_not_found', 'data' => $data];
        }

        // 3. Dedupe by Message-ID header — providers retry on 5xx.
        $rfcMessageId = trim((string) ($payload['message_id'] ?? ''));
        if ($rfcMessageId !== '' && $this->alreadyIngested($rfcMessageId)) {
            return ['ok' => true, 'reason' => 'duplicate_message_id_skipped'];
        }

        // 4. Build the conversation (channel=email, scoped to owning user).
        $conversation = $chatService->createOrUpdateConversation(
            contact: $ctx['contact'],
            channel: 'email',
            gateway_id: $ctx['gateway_id'],
            metaData: [
                'ingest_source' => 'inbound_email_webhook',
                'from'          => (string) ($payload['from'] ?? ''),
                'subject'       => (string) ($payload['subject'] ?? ''),
                'rfc_message_id'=> $rfcMessageId,
            ],
            user: $ctx['user']
        );

        // 5. Create the message. Prefer plain text; strip HTML if only that came.
        $body = trim((string) ($payload['text'] ?? ''));
        if ($body === '' && !empty($payload['html'])) {
            $body = trim(preg_replace('/[\s\x{A0}]+/u', ' ', html_entity_decode(strip_tags((string) $payload['html']), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        }
        if ($body === '') {
            $body = '(empty message)';
        }

        $message = $chatService->createMessage(
            conversation:   $conversation,
            messageContent: $body,
            user:           $ctx['user'],
        );

        // 6. Sender = the contact (inbound). Receiver = the user.
        $chatService->createMessageParticipants(
            message:       $message,
            senderId:      $ctx['contact']->id,
            senderType:    Contact::class,
            receiverId:    $ctx['user']?->id ?? 0,
            receiverType:  User::class,
        );

        $chatService->updateConversation($conversation);

        // v5.0-S6 — fire the existing "contact_replied" automation trigger so
        // any customer-configured workflows / chatbot rules react to email
        // replies the same way they already react to WhatsApp replies
        // (WebhookController line 335 fires the exact same call for WhatsApp).
        // Non-blocking: workflow failures never break inbound ingestion.
        try {
            app(\App\Services\Automation\TriggerHandlerService::class)
                ->handleContactReplied($ctx['contact'], 'email', [
                    'message_id'   => $message->id,
                    'message_body' => $message->message,
                    'gateway_id'   => $ctx['gateway_id'],
                    'from'         => (string) ($payload['from'] ?? ''),
                    'subject'      => (string) ($payload['subject'] ?? ''),
                ]);
            if ($ctx['contact']->wasRecentlyCreated ?? false) {
                app(\App\Services\Automation\TriggerHandlerService::class)
                    ->handleNewContact($ctx['contact']);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Automation trigger (email inbound) failed', ['error' => $e->getMessage()]);
        }

        return [
            'ok'              => true,
            'conversation_id' => $conversation->id,
            'message_id'      => $message->id,
        ];
    }

    // ── internal ─────────────────────────────────────────────────────────

    /**
     * Base64URL-encode a signed token. HMAC keeps forged Reply-Tos from
     * injecting fake conversations into arbitrary accounts.
     */
    protected function encodeToken(array $data): string
    {
        $json = json_encode($data);
        $b64  = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
        $sig  = substr(hash_hmac('sha256', $b64, $this->hmacKey()), 0, 12);
        return $b64 . '.' . $sig;
    }

    protected function decodeToken(string $token): ?array
    {
        if (!str_contains($token, '.')) return null;
        [$b64, $sig] = explode('.', $token, 2);
        $expected = substr(hash_hmac('sha256', $b64, $this->hmacKey()), 0, 12);
        if (!hash_equals($expected, $sig)) return null;

        $json = base64_decode(strtr($b64, '-_', '+/'), true);
        if ($json === false) return null;
        $data = json_decode($json, true);
        return is_array($data) ? $data : null;
    }

    /**
     * Pull the token out of `reply-{token}@domain` or `Reply-{token}+extra@domain`.
     */
    protected function extractToken(string $address): ?string
    {
        // Strip display-name quoting if provider passes "Name <addr>"
        if (preg_match('/<([^>]+)>/', $address, $m)) {
            $address = $m[1];
        }
        [$local] = explode('@', $address, 2) + ['', ''];
        if (stripos($local, 'reply-') !== 0) return null;
        $tail = substr($local, 6);
        // Some providers append +tags — strip after +
        if (($plus = strpos($tail, '+')) !== false) {
            $tail = substr($tail, 0, $plus);
        }
        return $tail !== '' ? $tail : null;
    }

    /**
     * Resolve contact + user + gateway_id from the token payload.
     */
    protected function resolveContext(array $data): array
    {
        $contact   = null;
        $user      = null;
        $gatewayId = null;

        if (!empty($data['cd'])) {
            $dispatch = CampaignDispatch::with(['contact', 'campaign', 'gateway'])->find($data['cd']);
            if ($dispatch) {
                $contact   = $dispatch->contact;
                $gatewayId = $dispatch->gateway_id;
                if ($dispatch->campaign && $dispatch->campaign->user_id) {
                    $user = User::find($dispatch->campaign->user_id);
                }
            }
        }
        if (!$contact && !empty($data['dl'])) {
            $log = DispatchLog::with('contact')->find($data['dl']);
            if ($log) {
                $contact   = $log->contact;
                $gatewayId = $log->gatewayable_id ?? null;
                if ($log->user_id) {
                    $user = User::find($log->user_id);
                }
            }
        }
        if (!$user && !empty($data['u'])) {
            $user = User::find($data['u']);
        }

        return ['contact' => $contact, 'user' => $user, 'gateway_id' => $gatewayId];
    }

    /**
     * Dedupe key for provider webhook retries.
     */
    protected function alreadyIngested(string $rfcMessageId): bool
    {
        try {
            return \App\Models\Message::whereJsonContains('meta_data->rfc_message_id', $rfcMessageId)->exists();
        } catch (\Throwable $e) {
            Log::debug('InboundEmail dedupe check skipped: ' . $e->getMessage());
            return false;
        }
    }

    protected function hmacKey(): string
    {
        // App-key derives a stable secret without adding a new .env var. If
        // APP_KEY rotates, old tokens invalidate — acceptable trade-off since
        // Reply-To lifetimes are typically < 30 days per email retention policy.
        return (string) (config('app.key') ?: 'xsender-inbound-fallback-key');
    }
}
