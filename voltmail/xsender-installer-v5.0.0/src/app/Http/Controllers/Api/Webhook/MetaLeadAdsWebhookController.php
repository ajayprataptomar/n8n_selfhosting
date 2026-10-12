<?php

namespace App\Http\Controllers\Api\Webhook;

use App\Http\Controllers\Controller;
use App\Services\Lead\MetaLeadAdsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Meta Lead Ads webhook receiver.
 *
 *  GET  /api/webhook/meta-lead-ads   → verification challenge (Meta dashboard config)
 *  POST /api/webhook/meta-lead-ads   → leadgen notifications
 *
 * Verify token is read from site_settings('meta_lead_ads_verify_token').
 * App secret (for HMAC signature validation) from site_settings('meta_app_secret')
 * or the per-subscription page access token's owning Meta App.
 */
class MetaLeadAdsWebhookController extends Controller
{
    public function __construct(private MetaLeadAdsService $service) {}

    /**
     * Meta's GET handshake — echo hub.challenge if hub.verify_token matches.
     */
    public function verify(Request $request): Response
    {
        $mode      = $request->query('hub_mode');
        $challenge = $request->query('hub_challenge');
        $token     = $request->query('hub_verify_token');

        $expected = site_settings('meta_lead_ads_verify_token');

        if ($mode === 'subscribe' && $expected && hash_equals((string) $expected, (string) $token)) {
            return response((string) $challenge, 200);
        }

        Log::warning('MetaLeadAdsWebhook: verification failed', [
            'mode'       => $mode,
            'token_seen' => $token ? '<set>' : '<empty>',
        ]);
        return response('Forbidden', 403);
    }

    /**
     * Meta's POST leadgen notification.
     *
     * Payload shape:
     *   {
     *     "object": "page",
     *     "entry": [{
     *        "id": "<page_id>",
     *        "changes": [{
     *          "field": "leadgen",
     *          "value": { "leadgen_id": "...", "page_id": "...", "form_id": "...", "created_time": ... }
     *        }]
     *     }]
     *   }
     */
    public function receive(Request $request): JsonResponse
    {
        if (!$this->verifySignature($request)) {
            Log::warning('MetaLeadAdsWebhook: signature mismatch — payload rejected');
            return response()->json(['status' => false, 'message' => 'Invalid signature'], 401);
        }

        $payload = $request->all();
        $object  = $payload['object'] ?? null;
        $entries = $payload['entry']  ?? [];

        if ($object !== 'page' || empty($entries)) {
            return response()->json(['status' => true, 'processed' => 0]);
        }

        $processed = 0;

        foreach ($entries as $entry) {
            $pageId  = (string) ($entry['id'] ?? '');
            $changes = $entry['changes'] ?? [];

            foreach ($changes as $change) {
                if (($change['field'] ?? null) !== 'leadgen') continue;

                $value     = $change['value']        ?? [];
                $leadgenId = (string) ($value['leadgen_id'] ?? '');
                $formId    = (string) ($value['form_id']    ?? '');

                if ($leadgenId === '' || $formId === '' || $pageId === '') {
                    Log::warning('MetaLeadAdsWebhook: incomplete leadgen entry', $value);
                    continue;
                }

                try {
                    if ($this->service->processLead($leadgenId, $pageId, $formId)) {
                        $processed++;
                    }
                } catch (\Throwable $e) {
                    Log::error('MetaLeadAdsWebhook: processLead threw', [
                        'leadgen_id' => $leadgenId,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }
        }

        // Always 200 to Meta — non-200 triggers retries that pile up if the bug is on our side.
        return response()->json(['status' => true, 'processed' => $processed]);
    }

    /**
     * Validate X-Hub-Signature-256 against site_settings('meta_app_secret').
     * If no app secret configured, signature check is skipped (test-mode).
     */
    protected function verifySignature(Request $request): bool
    {
        $appSecret = site_settings('meta_app_secret');
        if (!$appSecret) return true; // dev / test mode

        $header = $request->header('X-Hub-Signature-256', '');
        if (!str_starts_with($header, 'sha256=')) return false;

        $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $appSecret);
        return hash_equals($expected, $header);
    }
}
