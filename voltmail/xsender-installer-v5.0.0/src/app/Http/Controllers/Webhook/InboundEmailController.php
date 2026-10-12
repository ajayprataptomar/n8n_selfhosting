<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Services\System\Communication\ChatService;
use App\Services\System\Communication\InboundEmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * v5.0-S2 — Inbound email webhook (all providers).
 *
 * Route: POST /webhooks/inbound-email
 *
 * Provider adapters:
 *   ?provider=sendgrid      SendGrid Inbound Parse (multipart form-data)
 *   ?provider=mailgun       Mailgun Routes (form-data)
 *   ?provider=postmark      Postmark Inbound (JSON body)
 *   ?provider=generic       Any postfix→curl script that POSTs JSON with
 *                           {to, from, subject, text, html, message_id}
 *   (default = generic)
 *
 * All adapters normalise into the same shape then hand off to
 * InboundEmailService::ingest(). Zero adapter-specific persistence logic.
 *
 * Auth: no auth middleware — the token in the Reply-To address IS the
 * authentication (HMAC-signed). Anything without a valid token is dropped.
 * Optionally the customer sets INBOUND_EMAIL_SHARED_SECRET in .env and the
 * provider adds `?secret=…` — checked below.
 */
class InboundEmailController extends Controller
{
    public function __invoke(Request $request, InboundEmailService $inbound, ChatService $chat): JsonResponse
    {
        // Optional shared-secret guard (defence-in-depth over the HMAC token).
        $expected = (string) env('INBOUND_EMAIL_SHARED_SECRET', '');
        if ($expected !== '' && !hash_equals($expected, (string) $request->query('secret'))) {
            return response()->json(['ok' => false, 'reason' => 'invalid_secret'], 401);
        }

        $provider = strtolower((string) $request->query('provider', 'generic'));
        $payload  = $this->normalise($provider, $request);

        Log::info('Inbound email webhook hit', [
            'provider' => $provider,
            'to'       => $payload['to']         ?? null,
            'from'     => $payload['from']       ?? null,
            'msgid'    => $payload['message_id'] ?? null,
        ]);

        $result = $inbound->ingest($payload, $chat);

        // Always 200 — providers retry on 5xx. If we've deduped or the token
        // is bad, that's not a retryable condition.
        return response()->json($result, 200);
    }

    /**
     * Normalise provider-specific payload → canonical shape for InboundEmailService.
     */
    protected function normalise(string $provider, Request $req): array
    {
        switch ($provider) {
            case 'sendgrid':
                // SendGrid Inbound Parse sends multipart form-data.
                return [
                    'to'         => (string) $req->input('envelope.to.0', $req->input('to')),
                    'from'       => (string) $req->input('envelope.from', $req->input('from')),
                    'from_name'  => '',
                    'subject'    => (string) $req->input('subject', ''),
                    'text'       => (string) $req->input('text', ''),
                    'html'       => (string) $req->input('html', ''),
                    'message_id' => $this->parseMessageId((string) $req->input('headers', '')),
                ];

            case 'mailgun':
                return [
                    'to'         => (string) $req->input('recipient', $req->input('To')),
                    'from'       => (string) $req->input('sender',    $req->input('From')),
                    'from_name'  => '',
                    'subject'    => (string) $req->input('subject', ''),
                    'text'       => (string) $req->input('body-plain', ''),
                    'html'       => (string) $req->input('body-html', ''),
                    'message_id' => (string) $req->input('Message-Id', ''),
                ];

            case 'postmark':
                $data = $req->json()->all() ?: $req->all();
                return [
                    'to'         => (string) ($data['OriginalRecipient'] ?? ($data['To'] ?? '')),
                    'from'       => (string) ($data['FromFull']['Email'] ?? ($data['From'] ?? '')),
                    'from_name'  => (string) ($data['FromFull']['Name']  ?? ''),
                    'subject'    => (string) ($data['Subject'] ?? ''),
                    'text'       => (string) ($data['TextBody'] ?? ''),
                    'html'       => (string) ($data['HtmlBody'] ?? ''),
                    'message_id' => (string) ($data['MessageID'] ?? ''),
                ];

            case 'generic':
            default:
                // Any postfix→curl or custom relay that POSTs JSON.
                $data = $req->json()->all() ?: $req->all();
                return [
                    'to'         => (string) ($data['to'] ?? ''),
                    'from'       => (string) ($data['from'] ?? ''),
                    'from_name'  => (string) ($data['from_name'] ?? ''),
                    'subject'    => (string) ($data['subject'] ?? ''),
                    'text'       => (string) ($data['text'] ?? ''),
                    'html'       => (string) ($data['html'] ?? ''),
                    'message_id' => (string) ($data['message_id'] ?? ''),
                ];
        }
    }

    /**
     * Pull Message-ID out of a raw headers blob (SendGrid provides this as text).
     */
    protected function parseMessageId(string $headers): string
    {
        if ($headers === '') return '';
        if (preg_match('/^Message-I[Dd]:\s*<([^>]+)>/mi', $headers, $m)) {
            return $m[1];
        }
        return '';
    }
}
