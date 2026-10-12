<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Services\System\Communication\ChatService;
use App\Services\System\Communication\InboundSmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * v5.0-S3 — Inbound SMS webhook (all providers).
 *
 * Route: POST /webhooks/inbound-sms
 *
 * Provider adapters (via ?provider= query param — defaults to `generic`):
 *   twilio        Twilio Messaging (application/x-www-form-urlencoded)
 *   vonage        Vonage/Nexmo SMS API (form or JSON)
 *   infobip       Infobip Inbound (JSON: results[])
 *   messagebird   MessageBird SMS (JSON)
 *   generic       Custom relay / any provider — expects {from, to, text, message_id}
 *
 * Auth (in order of preference):
 *   1. Provider-native signature check (Twilio X-Twilio-Signature — implemented)
 *   2. Optional shared secret via ?secret= query param
 *      (env: INBOUND_SMS_SHARED_SECRET) — defence-in-depth for adapters
 *      without native signature support.
 *   3. Bare payload accepted only if shared-secret is not configured, to
 *      keep first-time setup one-step. Not recommended for production.
 *
 * All adapters normalise into the canonical shape below and hand off to
 * InboundSmsService::ingest():
 *   [
 *     'from'       => string,   // sender phone (E.164 or local)
 *     'to'         => string,   // recipient (= our SMS gateway number)
 *     'text'       => string,   // message body
 *     'message_id' => string,   // provider's message id (for dedupe)
 *     'provider'   => string,   // 'twilio' | 'vonage' | 'infobip' | ...
 *   ]
 */
class InboundSmsController extends Controller
{
    public function __invoke(Request $request, InboundSmsService $inbound, ChatService $chat): JsonResponse
    {
        $provider = strtolower((string) $request->query('provider', 'generic'));

        // 1. Provider-native signature (Twilio only — Vonage/Infobip use IP allowlist).
        if ($provider === 'twilio' && !$this->verifyTwilioSignature($request)) {
            Log::warning('InboundSms: Twilio signature verification failed');
            return response()->json(['ok' => false, 'reason' => 'twilio_signature_invalid'], 403);
        }

        // 2. Optional shared secret guard.
        $expected = (string) env('INBOUND_SMS_SHARED_SECRET', '');
        if ($expected !== '' && !hash_equals($expected, (string) $request->query('secret'))) {
            return response()->json(['ok' => false, 'reason' => 'invalid_secret'], 401);
        }

        $payload = $this->normalise($provider, $request);
        $payload['provider'] = $provider;

        Log::info('Inbound SMS webhook hit', [
            'provider'   => $provider,
            'from'       => $payload['from']       ?? null,
            'to'         => $payload['to']         ?? null,
            'message_id' => $payload['message_id'] ?? null,
        ]);

        $result = $inbound->ingest($payload, $chat);

        // Always 200 — providers retry on 5xx. Any legitimate skip
        // (dedupe / bad payload) is reported as { ok, reason }.
        return response()->json($result, 200);
    }

    /**
     * Normalise provider-specific payload into the canonical shape.
     */
    protected function normalise(string $provider, Request $req): array
    {
        switch ($provider) {
            case 'twilio':
                // Twilio Messaging — application/x-www-form-urlencoded
                return [
                    'from'       => (string) $req->input('From'),
                    'to'         => (string) $req->input('To'),
                    'text'       => (string) $req->input('Body'),
                    'message_id' => (string) $req->input('MessageSid'),
                ];

            case 'vonage':
                // Vonage / Nexmo SMS inbound
                return [
                    'from'       => (string) ($req->input('msisdn')    ?: $req->input('from')),
                    'to'         => (string) ($req->input('to')        ?? ''),
                    'text'       => (string) ($req->input('text')      ?? ''),
                    'message_id' => (string) ($req->input('messageId') ?? $req->input('id') ?? ''),
                ];

            case 'infobip':
                // Infobip Inbound — JSON body with results[]
                $data = $req->json()->all() ?: $req->all();
                $first = $data['results'][0] ?? [];
                return [
                    'from'       => (string) ($first['from'] ?? ''),
                    'to'         => (string) ($first['to']   ?? ''),
                    'text'       => (string) ($first['text'] ?? ''),
                    'message_id' => (string) ($first['messageId'] ?? ''),
                ];

            case 'messagebird':
                $data = $req->json()->all() ?: $req->all();
                return [
                    'from'       => (string) ($data['originator'] ?? ''),
                    'to'         => (string) ($data['recipient']  ?? ''),
                    'text'       => (string) ($data['body']       ?? ''),
                    'message_id' => (string) ($data['id']         ?? ''),
                ];

            case 'generic':
            default:
                $data = $req->json()->all() ?: $req->all();
                return [
                    'from'       => (string) ($data['from']       ?? ''),
                    'to'         => (string) ($data['to']         ?? ''),
                    'text'       => (string) ($data['text']       ?? ''),
                    'message_id' => (string) ($data['message_id'] ?? ''),
                ];
        }
    }

    /**
     * Verify Twilio's X-Twilio-Signature header per their spec:
     *   https://www.twilio.com/docs/usage/webhooks/webhooks-security
     * HMAC-SHA1 over (webhook URL + sorted POST params concatenated).
     * Requires TWILIO_AUTH_TOKEN set in the customer's .env — if unset,
     * we log a warning and let the request through (dev-mode escape hatch).
     */
    protected function verifyTwilioSignature(Request $req): bool
    {
        $authToken = (string) env('TWILIO_AUTH_TOKEN', '');
        if ($authToken === '') {
            Log::warning('InboundSms: Twilio provider used but TWILIO_AUTH_TOKEN not set — accepting unverified. Set the env var to enable signature verification.');
            return true;
        }

        $signature = (string) $req->header('X-Twilio-Signature', '');
        if ($signature === '') return false;

        $url  = $req->fullUrl();
        $data = $req->all();
        ksort($data);
        foreach ($data as $k => $v) {
            $url .= $k . (is_array($v) ? implode('', $v) : (string) $v);
        }
        $expected = base64_encode(hash_hmac('sha1', $url, $authToken, true));
        return hash_equals($expected, $signature);
    }
}
