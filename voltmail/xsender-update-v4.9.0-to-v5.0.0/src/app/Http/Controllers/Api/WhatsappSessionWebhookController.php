<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gateway;
use App\Enums\Common\Status;
use App\Enums\System\ChannelTypeEnum;
use App\Enums\System\Gateway\WhatsAppGatewayTypeEnum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp Session Webhook Controller
 *
 * Handles session status updates from WhatsApp Node service
 * This ensures Laravel database stays in sync with actual session state
 */
class WhatsappSessionWebhookController extends Controller
{
    /**
     * Handle session status update from Node service
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function sessionStatus(Request $request): JsonResponse
    {
        try {
            // Validate API key
            $apiKey = $request->header('X-API-Key');
            if ($apiKey !== env('WP_API_KEY')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $sessionId = $request->input('sessionId');
            $status = $request->input('status'); // 'connected', 'disconnected', 'qr', 'logged_out'
            $user = $request->input('user'); // WhatsApp user info when connected

            if (!$sessionId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session ID is required'
                ], 400);
            }

            // Find gateway by session ID (name)
            $gateway = Gateway::where('name', $sessionId)
                ->where('channel', ChannelTypeEnum::WHATSAPP)
                ->where('type', WhatsAppGatewayTypeEnum::NODE)
                ->first();

            if (!$gateway) {
                Log::warning("Session webhook: Gateway not found for session: {$sessionId}");
                return response()->json([
                    'success' => false,
                    'message' => 'Gateway not found'
                ], 404);
            }

            // Update gateway status based on session status
            $previousStatus = $gateway->status;

            switch ($status) {
                case 'connected':
                case 'authenticated':
                    $gateway->status = Status::ACTIVE;

                    // Update WhatsApp number from user info
                    if ($user && isset($user['id'])) {
                        $wpNumber = str_replace('@s.whatsapp.net', '', $user['id']);
                        $wpNumber = explode(':', $wpNumber)[0] ?? $wpNumber;

                        $metaData = $gateway->meta_data ?? [];
                        $metaData['number'] = $wpNumber;
                        $gateway->meta_data = $metaData;
                    }
                    break;

                case 'disconnected':
                case 'logged_out':
                case 'connection_lost':
                    $gateway->status = Status::INACTIVE;
                    break;

                case 'qr':
                    // QR code being shown - device is connecting
                    // Don't change status, keep as is
                    break;

                default:
                    Log::info("Session webhook: Unknown status '{$status}' for session: {$sessionId}");
            }

            // Use direct update to avoid lock contention with queue workers
            Gateway::where('id', $gateway->id)->update([
                'status'     => $gateway->status,
                'updated_at' => now(),
            ]);

            // Clear cached session status
            Cache::forget("whatsapp_session_{$gateway->id}");
            Cache::forget("gateway_status_{$gateway->id}");

            Log::info("Session webhook: Gateway status updated", [
                'session_id' => $sessionId,
                'gateway_id' => $gateway->id,
                'previous_status' => $previousStatus,
                'new_status' => $gateway->status,
                'event_status' => $status,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Status updated',
                'data' => [
                    'gateway_id' => $gateway->id,
                    'status' => $gateway->status->value ?? $gateway->status,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Session webhook error: " . $e->getMessage(), [
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal error'
            ], 500);
        }
    }

    /**
     * Sync all gateway statuses with Node service
     * Called periodically or on demand to ensure consistency
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function syncAll(Request $request): JsonResponse
    {
        try {
            // Validate API key
            $apiKey = $request->header('X-API-Key');
            if ($apiKey !== env('WP_API_KEY')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $sessions = $request->input('sessions', []);
            $updated = 0;

            // Get all Node gateways
            $gateways = Gateway::where('channel', ChannelTypeEnum::WHATSAPP)
                ->where('type', WhatsAppGatewayTypeEnum::NODE)
                ->get();

            foreach ($gateways as $gateway) {
                $sessionData = collect($sessions)->firstWhere('id', $gateway->name);

                $newStatus = Status::INACTIVE;
                if ($sessionData && ($sessionData['status'] === 'connected' || $sessionData['status'] === 'authenticated')) {
                    $newStatus = Status::ACTIVE;
                }

                if ($gateway->status !== $newStatus) {
                    Gateway::where('id', $gateway->id)->update(['status' => $newStatus, 'updated_at' => now()]);
                    $updated++;

                    Cache::forget("whatsapp_session_{$gateway->id}");
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Synced {$updated} gateways",
                'data' => [
                    'total' => $gateways->count(),
                    'updated' => $updated,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Session sync error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Internal error'
            ], 500);
        }
    }

    /**
     * Get gateway status - for Node service to verify
     *
     * @param string $sessionId
     * @return JsonResponse
     */
    public function getStatus(string $sessionId): JsonResponse
    {
        try {
            $gateway = Gateway::where('name', $sessionId)
                ->where('channel', ChannelTypeEnum::WHATSAPP)
                ->where('type', WhatsAppGatewayTypeEnum::NODE)
                ->first();

            if (!$gateway) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gateway not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'gateway_id' => $gateway->id,
                    'session_id' => $gateway->name,
                    'status' => $gateway->status->value ?? $gateway->status,
                    'number' => $gateway->meta_data['number'] ?? null,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal error'
            ], 500);
        }
    }
}
