<?php

namespace App\Http\Controllers\Api\Automation;

use App\Http\Controllers\Controller;
use App\Services\Automation\TriggerHandlerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AutomationWebhookController extends Controller
{
    /**
     * Handle incoming webhook trigger for automation workflows
     *
     * External systems can POST to /api/automation/webhook/{id}
     * to trigger a workflow. The payload should contain contact
     * identification (email, phone, or whatsapp number).
     *
     * Example payload:
     * {
     *   "email": "john@example.com",
     *   "phone": "+1234567890",
     *   "first_name": "John",
     *   "last_name": "Doe",
     *   "custom_field": "value"
     * }
     */
    public function trigger(Request $request, string $id): JsonResponse
    {
        try {
            $triggerHandler = app(TriggerHandlerService::class);

            $result = $triggerHandler->handleWebhookTrigger($id, $request->all());

            return response()->json([
                'success' => true,
                'message' => 'Webhook processed',
                'workflows_triggered' => $result['workflows_triggered'] ?? 0,
                'contacts_enrolled'   => $result['contacts_enrolled'] ?? 0,
            ]);

        } catch (\Throwable $e) {
            Log::error('Automation webhook trigger error', [
                'webhook_id' => $id,
                'error'      => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Webhook processing failed',
            ], 500);
        }
    }
}
