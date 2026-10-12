<?php

namespace App\Http\Controllers\Api\Webhook;

use App\Http\Controllers\Controller;
use App\Services\Lead\GoogleAdsLeadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Google Ads Lead Form Extensions webhook receiver.
 *
 *  POST /api/webhook/google-ads-lead
 *
 * Google's payload includes a `google_key` field. The service compares it to the
 * matching subscription's stored shared_key with hash_equals (timing-safe).
 */
class GoogleAdsLeadWebhookController extends Controller
{
    public function __construct(private GoogleAdsLeadService $service) {}

    public function receive(Request $request): JsonResponse
    {
        $payload = $request->all();

        try {
            $contact = $this->service->processLead($payload);
            return response()->json([
                'status' => true,
                'lead_id' => $payload['lead_id'] ?? null,
                'imported' => (bool) $contact,
            ]);
        } catch (\Throwable $e) {
            Log::error('GoogleAdsLeadWebhook: processLead threw', [
                'error' => $e->getMessage(),
            ]);
            // Always return 200 to Google — non-200 triggers retries.
            return response()->json(['status' => false, 'message' => 'internal']);
        }
    }
}
