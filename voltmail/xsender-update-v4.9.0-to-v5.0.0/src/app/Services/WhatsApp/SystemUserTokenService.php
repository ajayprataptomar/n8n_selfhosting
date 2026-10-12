<?php

namespace App\Services\WhatsApp;

use App\Enums\MetaApiEndpoints;
use App\Models\MetaConfiguration;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SystemUserTokenService
{
    protected ?MetaConfiguration $config = null;
    protected string $apiVersion;

    public function __construct(?MetaConfiguration $config = null)
    {
        $this->config = $config ?? MetaConfiguration::getDefault();
        $this->apiVersion = $this->config?->api_version ?? MetaApiEndpoints::CURRENT_VERSION;
    }

    /**
     * Set the Meta configuration to use
     */
    public function useConfiguration(MetaConfiguration $config): self
    {
        $this->config = $config;
        $this->apiVersion = $config->api_version;
        return $this;
    }

    /**
     * Generate System User Access Token
     * This is used for Tech Provider flow to get long-lived tokens
     */
    public function generateSystemUserToken(string $userAccessToken): array
    {
        try {
            if (!$this->config->system_user_id) {
                return [
                    'success' => false,
                    'message' => translate('System User ID not configured')
                ];
            }

            // Generate System User token using Business Manager
            $response = Http::asForm()->post(
                MetaApiEndpoints::SYSTEM_USER_ACCESS_TOKENS->buildUrl($this->apiVersion, [
                    'system_user_id' => $this->config->system_user_id
                ]),
                [
                    'access_token' => $userAccessToken,
                    'business_app' => $this->config->app_id,
                    'scope' => 'whatsapp_business_messaging,whatsapp_business_management,business_management',
                    'appsecret_proof' => $this->generateAppSecretProof($userAccessToken),
                ]
            );

            if (!$response->successful()) {
                $error = Arr::get($response->json(), 'error', []);
                return [
                    'success' => false,
                    'message' => Arr::get($error, 'message', 'Failed to generate System User token'),
                    'error' => $error
                ];
            }

            $data = $response->json();
            $accessToken = Arr::get($data, 'access_token');

            if (!$accessToken) {
                return [
                    'success' => false,
                    'message' => translate('No access token in response')
                ];
            }

            // Update configuration with new token
            $this->config->update([
                'system_user_token' => $accessToken,
                'system_user_token_expires_at' => now()->addDays(60), // System User tokens typically last 60 days
            ]);

            return [
                'success' => true,
                'data' => $data,
                'message' => translate('System User token generated successfully')
            ];
        } catch (Exception $e) {
            Log::error('System User token generation failed', [
                'error' => $e->getMessage(),
                'config_id' => $this->config->id,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Extend/refresh token
     */
    public function extendToken(string $shortLivedToken): array
    {
        try {
            $response = Http::get(MetaApiEndpoints::OAUTH_ACCESS_TOKEN->buildUrl(), [
                'grant_type' => 'fb_exchange_token',
                'client_id' => $this->config->app_id,
                'client_secret' => $this->config->app_secret,
                'fb_exchange_token' => $shortLivedToken,
            ]);

            if (!$response->successful()) {
                $error = Arr::get($response->json(), 'error', []);
                return [
                    'success' => false,
                    'message' => Arr::get($error, 'message', 'Token extension failed'),
                    'error' => $error
                ];
            }

            $data = $response->json();

            return [
                'success' => true,
                'data' => $data,
                'message' => translate('Token extended successfully')
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Debug/inspect token
     */
    public function debugToken(string $token): array
    {
        try {
            $response = Http::get(MetaApiEndpoints::OAUTH_DEBUG_TOKEN->buildUrl(), [
                'input_token' => $token,
                'access_token' => $this->config->app_id . '|' . $this->config->app_secret,
            ]);

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'message' => translate('Token debug failed'),
                    'error' => $response->json()
                ];
            }

            $data = Arr::get($response->json(), 'data', []);

            return [
                'success' => true,
                'data' => [
                    'is_valid' => Arr::get($data, 'is_valid', false),
                    'app_id' => Arr::get($data, 'app_id'),
                    'user_id' => Arr::get($data, 'user_id'),
                    'scopes' => Arr::get($data, 'scopes', []),
                    'expires_at' => Arr::get($data, 'expires_at'),
                    'data_access_expires_at' => Arr::get($data, 'data_access_expires_at'),
                    'type' => Arr::get($data, 'type'),
                ]
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Validate token is still active
     */
    public function validateToken(string $token): array
    {
        $debugResult = $this->debugToken($token);

        if (!$debugResult['success']) {
            return $debugResult;
        }

        $isValid = Arr::get($debugResult, 'data.is_valid', false);
        $expiresAt = Arr::get($debugResult, 'data.expires_at');

        if (!$isValid) {
            return [
                'success' => false,
                'message' => translate('Token is no longer valid'),
                'data' => $debugResult['data']
            ];
        }

        // Check if token is expiring soon (within 7 days)
        if ($expiresAt && $expiresAt > 0) {
            $expiresInDays = (int)(($expiresAt - time()) / 86400);
            if ($expiresInDays < 7) {
                return [
                    'success' => true,
                    'warning' => true,
                    'message' => translate("Token expires in {$expiresInDays} days. Consider refreshing."),
                    'data' => $debugResult['data']
                ];
            }
        }

        return [
            'success' => true,
            'message' => translate('Token is valid'),
            'data' => $debugResult['data']
        ];
    }

    /**
     * Generate app secret proof for secure API calls
     */
    public function generateAppSecretProof(string $accessToken): string
    {
        return hash_hmac('sha256', $accessToken, $this->config->app_secret);
    }

    /**
     * Assign WABA to System User
     */
    public function assignWabaToSystemUser(string $wabaId, string $accessToken): array
    {
        try {
            if (!$this->config->system_user_id) {
                return [
                    'success' => false,
                    'message' => translate('System User ID not configured')
                ];
            }

            $response = Http::withToken($accessToken)->post(
                MetaApiEndpoints::SYSTEM_USER_ASSIGN_WABA->buildUrl($this->apiVersion, ['waba_id' => $wabaId]),
                [
                    'user' => $this->config->system_user_id,
                    'tasks' => ['MANAGE', 'DEVELOP'],
                ]
            );

            if (!$response->successful()) {
                $error = Arr::get($response->json(), 'error', []);
                return [
                    'success' => false,
                    'message' => Arr::get($error, 'message', 'Failed to assign WABA to System User'),
                    'error' => $error
                ];
            }

            return [
                'success' => true,
                'message' => translate('WABA assigned to System User successfully')
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get all tokens that need refresh
     */
    public static function getConfigurationsNeedingTokenRefresh(int $daysBeforeExpiry = 7): \Illuminate\Database\Eloquent\Collection
    {
        return MetaConfiguration::active()
            ->whereNotNull('system_user_token')
            ->whereNotNull('system_user_token_expires_at')
            ->where('system_user_token_expires_at', '<=', now()->addDays($daysBeforeExpiry))
            ->get();
    }

    /**
     * Check if current configuration needs token refresh
     */
    public function needsTokenRefresh(int $daysBeforeExpiry = 7): bool
    {
        return $this->config->isTokenExpiringSoon($daysBeforeExpiry * 24);
    }
}
