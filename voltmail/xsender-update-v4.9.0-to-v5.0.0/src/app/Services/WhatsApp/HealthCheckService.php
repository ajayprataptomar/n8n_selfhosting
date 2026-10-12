<?php

namespace App\Services\WhatsApp;

use App\Enums\MetaApiEndpoints;
use App\Enums\System\Gateway\WhatsAppGatewayTypeEnum;
use App\Models\Gateway;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HealthCheckService
{
    protected string $apiVersion = MetaApiEndpoints::CURRENT_VERSION;
    protected ?string $nodeServerUrl = null;

    public function __construct()
    {
        $this->nodeServerUrl = env('WP_SERVER_URL');
    }

    /**
     * Run health check for a specific gateway
     */
    public function checkGateway(Gateway $gateway): array
    {
        // Route to appropriate check based on gateway type
        if ($gateway->isCloudApi()) {
            return $this->checkCloudApiGateway($gateway);
        }

        if ($gateway->isNodeBased()) {
            return $this->checkNodeGateway($gateway);
        }

        return [
            'success' => false,
            'status' => Gateway::HEALTH_UNKNOWN,
            'message' => translate('Unknown gateway type')
        ];
    }

    /**
     * Check Cloud API gateway health
     */
    protected function checkCloudApiGateway(Gateway $gateway): array
    {
        try {
            $accessToken = $gateway->getAccessToken();
            $phoneNumberId = $gateway->getPhoneNumberId();

            if (!$accessToken || !$phoneNumberId) {
                $gateway->updateHealthStatus(Gateway::HEALTH_UNHEALTHY, 'Missing credentials');
                return [
                    'success' => false,
                    'status' => Gateway::HEALTH_UNHEALTHY,
                    'message' => translate('Missing access token or phone number ID')
                ];
            }

            // Test API connectivity by getting phone number info
            $response = Http::withToken($accessToken)
                ->timeout(10)
                ->get(
                    MetaApiEndpoints::PHONE_NUMBER_INFO->buildUrl($gateway->getApiVersion(), [
                        'phone_number_id' => $phoneNumberId
                    ]),
                    ['fields' => 'id,display_phone_number,verified_name,quality_rating,status']
                );

            if (!$response->successful()) {
                $error = Arr::get($response->json(), 'error', []);
                $errorCode = Arr::get($error, 'code');
                $errorMessage = Arr::get($error, 'message', 'API request failed');

                // Check for specific error codes
                $status = match ($errorCode) {
                    190 => Gateway::HEALTH_UNHEALTHY, // Invalid access token
                    4 => Gateway::HEALTH_DEGRADED,    // Rate limited
                    default => Gateway::HEALTH_UNHEALTHY,
                };

                $gateway->updateHealthStatus($status, $errorMessage);

                return [
                    'success' => false,
                    'status' => $status,
                    'message' => $errorMessage,
                    'error_code' => $errorCode
                ];
            }

            $data = $response->json();

            // Check quality rating
            $qualityRating = Arr::get($data, 'quality_rating');
            $status = match ($qualityRating) {
                'GREEN' => Gateway::HEALTH_HEALTHY,
                'YELLOW' => Gateway::HEALTH_DEGRADED,
                'RED' => Gateway::HEALTH_UNHEALTHY,
                default => Gateway::HEALTH_HEALTHY,
            };

            // Update gateway with latest info
            $gateway->update([
                'quality_rating' => $qualityRating,
                'address' => Arr::get($data, 'display_phone_number', $gateway->address),
            ]);

            $gateway->updateHealthStatus($status);

            return [
                'success' => true,
                'status' => $status,
                'message' => translate('Cloud API gateway is operational'),
                'data' => [
                    'phone_number' => Arr::get($data, 'display_phone_number'),
                    'verified_name' => Arr::get($data, 'verified_name'),
                    'quality_rating' => $qualityRating,
                    'status' => Arr::get($data, 'status'),
                ]
            ];
        } catch (Exception $e) {
            $gateway->updateHealthStatus(Gateway::HEALTH_UNHEALTHY, $e->getMessage());

            return [
                'success' => false,
                'status' => Gateway::HEALTH_UNHEALTHY,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Check Node-based gateway health
     */
    protected function checkNodeGateway(Gateway $gateway): array
    {
        try {
            if (!$this->nodeServerUrl) {
                return [
                    'success' => false,
                    'status' => Gateway::HEALTH_UNKNOWN,
                    'message' => translate('Node server URL not configured')
                ];
            }

            // Check session status from Node server
            $response = Http::timeout(10)
                ->withHeaders([
                    'X-API-Key' => env('WP_SERVER_API_KEY'),
                ])
                ->get("{$this->nodeServerUrl}/sessions/{$gateway->uid}/status");

            if (!$response->successful()) {
                $gateway->updateHealthStatus(Gateway::HEALTH_UNHEALTHY, 'Node server unreachable');
                return [
                    'success' => false,
                    'status' => Gateway::HEALTH_UNHEALTHY,
                    'message' => translate('Failed to connect to Node server')
                ];
            }

            $data = $response->json();
            $sessionStatus = Arr::get($data, 'status');

            $status = match ($sessionStatus) {
                'connected', 'authenticated' => Gateway::HEALTH_HEALTHY,
                'connecting', 'qr' => Gateway::HEALTH_DEGRADED,
                'disconnected', 'logged_out' => Gateway::HEALTH_UNHEALTHY,
                default => Gateway::HEALTH_UNKNOWN,
            };

            $gateway->updateHealthStatus($status, $sessionStatus !== 'connected' ? "Session: {$sessionStatus}" : null);

            return [
                'success' => $status === Gateway::HEALTH_HEALTHY,
                'status' => $status,
                'message' => translate("Node session status: {$sessionStatus}"),
                'data' => $data
            ];
        } catch (Exception $e) {
            $gateway->updateHealthStatus(Gateway::HEALTH_UNHEALTHY, $e->getMessage());

            return [
                'success' => false,
                'status' => Gateway::HEALTH_UNHEALTHY,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Run health checks for all active gateways
     */
    public function checkAllGateways(): array
    {
        $gateways = Gateway::active()->whatsapp()->get();
        $results = [
            'total' => $gateways->count(),
            'healthy' => 0,
            'degraded' => 0,
            'unhealthy' => 0,
            'unknown' => 0,
            'details' => [],
        ];

        foreach ($gateways as $gateway) {
            $result = $this->checkGateway($gateway);
            $status = $result['status'];

            $results['details'][$gateway->id] = [
                'name' => $gateway->name,
                'type' => $gateway->type,
                'status' => $status,
                'message' => $result['message'],
            ];

            match ($status) {
                Gateway::HEALTH_HEALTHY => $results['healthy']++,
                Gateway::HEALTH_DEGRADED => $results['degraded']++,
                Gateway::HEALTH_UNHEALTHY => $results['unhealthy']++,
                default => $results['unknown']++,
            };
        }

        return $results;
    }

    /**
     * Get Cloud API gateways that need attention (admin only)
     */
    public function getGatewaysNeedingAttention(): \Illuminate\Database\Eloquent\Collection
    {
        return Gateway::active()
            ->cloudApi()
            ->whereNull('user_id') // Admin gateways only
            ->where(function ($query) {
                $query->where('health_status', '!=', Gateway::HEALTH_HEALTHY)
                    ->orWhere('consecutive_failures', '>', 3)
                    ->orWhere('quality_rating', 'RED')
                    ->orWhereNull('last_health_check_at')
                    ->orWhere('last_health_check_at', '<', now()->subHours(24));
            })
            ->get();
    }

    /**
     * Get health summary statistics for Cloud API gateways (admin only)
     */
    public function getHealthSummary(): array
    {
        // Only count admin-owned Cloud API gateways
        $gateways = Gateway::cloudApi()->whereNull('user_id')->get();

        return [
            'total' => $gateways->count(),
            'active' => $gateways->where('status', 'active')->count(),
            'healthy' => $gateways->where('health_status', Gateway::HEALTH_HEALTHY)->count(),
            'degraded' => $gateways->where('health_status', Gateway::HEALTH_DEGRADED)->count(),
            'unhealthy' => $gateways->where('health_status', Gateway::HEALTH_UNHEALTHY)->count(),
            'unknown' => $gateways->whereNull('health_status')->count()
                + $gateways->where('health_status', Gateway::HEALTH_UNKNOWN)->count(),
            'quality' => [
                'green' => $gateways->where('quality_rating', 'GREEN')->count(),
                'yellow' => $gateways->where('quality_rating', 'YELLOW')->count(),
                'red' => $gateways->where('quality_rating', 'RED')->count(),
            ],
        ];
    }

    /**
     * Test Meta API connectivity (general)
     */
    public function testMetaApiConnectivity(): array
    {
        try {
            $response = Http::timeout(10)->get(MetaApiEndpoints::getGraphBaseUrl() . '/' . MetaApiEndpoints::CURRENT_VERSION . '/me', [
                'access_token' => 'test', // Will fail with auth error, but proves connectivity
            ]);

            // We expect an error, but if we get a response, API is reachable
            return [
                'success' => true,
                'message' => translate('Meta API is reachable'),
                'response_code' => $response->status()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => translate('Cannot reach Meta API: ') . $e->getMessage()
            ];
        }
    }

    /**
     * Test Node server connectivity
     */
    public function testNodeServerConnectivity(): array
    {
        try {
            if (!$this->nodeServerUrl) {
                return [
                    'success' => false,
                    'message' => translate('Node server URL not configured')
                ];
            }

            $response = Http::timeout(10)
                ->withHeaders(['X-API-Key' => env('WP_SERVER_API_KEY')])
                ->get("{$this->nodeServerUrl}/health");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => translate('Node server is reachable'),
                    'data' => $response->json()
                ];
            }

            return [
                'success' => false,
                'message' => translate('Node server returned error'),
                'response_code' => $response->status()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => translate('Cannot reach Node server: ') . $e->getMessage()
            ];
        }
    }
}
