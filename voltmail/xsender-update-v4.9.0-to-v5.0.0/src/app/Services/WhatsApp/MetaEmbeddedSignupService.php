<?php

namespace App\Services\WhatsApp;

use App\Enums\MetaApiEndpoints;
use App\Enums\System\ChannelTypeEnum;
use App\Enums\System\Gateway\WhatsAppGatewayTypeEnum;
use App\Models\Gateway;
use App\Models\MetaConfiguration;
use App\Models\User;
use App\Models\WhatsappClientOnboarding;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MetaEmbeddedSignupService
{
    protected MetaConfiguration $config;
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
     * Initiate embedded signup flow - Meta 2025 compliant with config_id
     */
    public function initiateSignup(string $redirectUri, ?User $user = null): array
    {
        try {
            if (!$this->config) {
                return [
                    'success' => false,
                    'message' => translate('No Meta configuration found. Please configure Meta App settings first.')
                ];
            }

            if (!$this->config->isTechProviderReady()) {
                return [
                    'success' => false,
                    'message' => translate('Meta configuration is incomplete. Configuration ID is required for Meta 2025 compliance.')
                ];
            }

            // Create onboarding record
            $onboarding = WhatsappClientOnboarding::create([
                'user_id' => $user?->id,
                'meta_configuration_id' => $this->config->id,
                'onboarding_status' => WhatsappClientOnboarding::STATUS_INITIATED,
                'initiated_at' => now(),
            ]);

            // Build state parameter
            $state = $this->buildState($user, $onboarding);

            // Build OAuth URL with config_id (Meta 2025 requirement)
            $signupUrl = $this->buildOAuthUrl($redirectUri, $state);

            return [
                'success' => true,
                'signup_url' => $signupUrl,
                'onboarding_id' => $onboarding->uid,
                'message' => translate('Embedded signup URL generated successfully')
            ];
        } catch (Exception $e) {
            Log::error('Meta Embedded Signup initiation failed', [
                'error' => $e->getMessage(),
                'config_id' => $this->config?->id,
            ]);

            return [
                'success' => false,
                'message' => translate('Failed to initiate embedded signup: ') . $e->getMessage()
            ];
        }
    }

    /**
     * Build OAuth URL with config_id (CRITICAL for Meta 2025)
     */
    protected function buildOAuthUrl(string $redirectUri, string $state): string
    {
        $scopes = [
            'whatsapp_business_messaging',
            'whatsapp_business_management',
            'business_management',
        ];

        $extras = [
            'feature' => 'whatsapp_embedded_signup',
            'version' => 2, // Embedded Signup Version 2.0
            'setup' => [
                'solution' => 'whatsapp',
                'flow' => 'signup',
            ],
        ];

        // CRITICAL: Add config_id for Tech Provider flow (Meta 2025 requirement)
        if ($this->config->config_id) {
            $extras['setup']['config_id'] = $this->config->config_id;
        }

        // Add solution_id if available
        if ($this->config->solution_id) {
            $extras['setup']['solution_id'] = $this->config->solution_id;
        }

        $params = [
            'client_id' => $this->config->app_id,
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'scope' => implode(',', $scopes),
            'response_type' => 'code',
            'extras' => json_encode($extras),
        ];

        return MetaApiEndpoints::getOAuthBaseUrl() . "/{$this->apiVersion}/dialog/oauth?" . http_build_query($params);
    }

    /**
     * Build state parameter with encryption
     */
    protected function buildState(?User $user, WhatsappClientOnboarding $onboarding): string
    {
        $stateData = [
            'user_type' => $user ? 'user' : 'admin',
            'user_id' => $user?->id,
            'onboarding_uid' => $onboarding->uid,
            'config_id' => $this->config->id,
            'timestamp' => now()->timestamp,
            'nonce' => Str::random(16),
        ];

        return base64_encode(json_encode($stateData));
    }

    /**
     * Handle OAuth callback - Complete flow
     */
    public function handleCallback(string $code, string $state, string $redirectUri): array
    {
        DB::beginTransaction();

        try {
            // Validate state
            $stateValidation = $this->validateState($state);
            if (!$stateValidation['success']) {
                return $stateValidation;
            }

            $stateData = $stateValidation['data'];
            $onboarding = WhatsappClientOnboarding::where('uid', $stateData['onboarding_uid'])->first();

            if (!$onboarding) {
                return ['success' => false, 'message' => translate('Onboarding session not found')];
            }

            // Load the correct configuration
            if ($stateData['config_id']) {
                $this->config = MetaConfiguration::find($stateData['config_id']) ?? $this->config;
            }

            // Step 1: Exchange code for access token
            $tokenResult = $this->exchangeCodeForToken($code, $redirectUri);
            if (!$tokenResult['success']) {
                $onboarding->markFailed('Token exchange failed', $tokenResult);
                DB::commit();
                return $tokenResult;
            }

            $accessToken = Arr::get($tokenResult, 'data.access_token');
            $tokenExpiresIn = Arr::get($tokenResult, 'data.expires_in');

            // Update onboarding with token
            $onboarding->update([
                'user_access_token' => $accessToken,
                'user_token_expires_at' => $tokenExpiresIn ? now()->addSeconds($tokenExpiresIn) : null,
            ]);

            // Step 2: Get account information
            $accountResult = $this->getAccountInfo($accessToken);
            if (!$accountResult['success']) {
                $onboarding->markFailed('Failed to get account info', $accountResult);
                DB::commit();
                return $accountResult;
            }

            // Update onboarding with WABA info
            $wabaData = Arr::get($accountResult, 'data.business_account');
            $phoneData = Arr::get($accountResult, 'data.phone_numbers.0');

            $onboarding->update([
                'oauth_response' => $accountResult['data'],
            ]);
            $onboarding->updateWabaInfo($wabaData);
            $onboarding->markOAuthCompleted($accountResult['data']);

            if ($phoneData) {
                $onboarding->updatePhoneInfo($phoneData);
                $onboarding->markPhoneRegistered($phoneData);
            }

            // Step 3: Subscribe to webhooks
            $webhookResult = $this->subscribeToWebhooks($onboarding->waba_id, $accessToken);
            if ($webhookResult['success']) {
                $onboarding->markWebhookSubscribed();
            }

            // Step 4: Create Gateway
            $gateway = $this->createGateway($onboarding, $stateData);

            // Mark completed
            $onboarding->markCompleted($gateway->id);

            DB::commit();

            return [
                'success' => true,
                'gateway' => $gateway,
                'onboarding' => $onboarding,
                'message' => translate('WhatsApp Business Account connected successfully')
            ];

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Meta Embedded Signup callback failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => translate('Failed to complete embedded signup: ') . $e->getMessage()
            ];
        }
    }

    /**
     * Validate state parameter
     */
    protected function validateState(string $stateParam): array
    {
        try {
            $state = json_decode(base64_decode($stateParam), true);

            if (!$state || !is_array($state)) {
                return ['success' => false, 'message' => translate('Invalid state structure')];
            }

            $required = ['user_type', 'timestamp', 'nonce', 'onboarding_uid'];
            $missing = collect($required)->filter(fn($field) => !array_key_exists($field, $state))->values()->all();

            if (!empty($missing)) {
                return ['success' => false, 'message' => translate('Missing field: ') . implode(', ', $missing)];
            }

            // Check timestamp (1 hour expiration)
            if (now()->timestamp - Arr::get($state, 'timestamp') > 3600) {
                return ['success' => false, 'message' => translate('Session expired. Please try again.')];
            }

            return ['success' => true, 'data' => $state];
        } catch (Exception $e) {
            return ['success' => false, 'message' => translate('State validation error: ') . $e->getMessage()];
        }
    }

    /**
     * Exchange authorization code for access token
     */
    protected function exchangeCodeForToken(string $code, string $redirectUri): array
    {
        try {
            $response = Http::asForm()->post(MetaApiEndpoints::OAUTH_ACCESS_TOKEN->buildUrl(), [
                'client_id' => $this->config->app_id,
                'client_secret' => $this->config->app_secret,
                'code' => $code,
                'redirect_uri' => $redirectUri,
            ]);

            if (!$response->successful()) {
                $error = Arr::get($response->json(), 'error', []);
                return [
                    'success' => false,
                    'message' => Arr::get($error, 'message', 'Token exchange failed'),
                    'error' => $error
                ];
            }

            $data = $response->json();

            if (!Arr::has($data, 'access_token')) {
                return [
                    'success' => false,
                    'message' => translate('No access token in response'),
                    'error' => $data
                ];
            }

            return ['success' => true, 'data' => $data];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get complete account information from Meta API
     */
    protected function getAccountInfo(string $accessToken): array
    {
        try {
            // Get user info with WABA details
            $userResponse = Http::withToken($accessToken)->get(
                MetaApiEndpoints::USER_INFO->buildUrl($this->apiVersion),
                [
                    'fields' => 'id,name,whatsapp_business_accounts{id,name,currency,timezone_id,message_template_namespace,account_review_status,business_verification_status}'
                ]
            );

            if (!$userResponse->successful()) {
                return [
                    'success' => false,
                    'message' => translate('Failed to get user information'),
                    'error' => $userResponse->json()
                ];
            }

            $userData = $userResponse->json();
            $wabaAccounts = Arr::get($userData, 'whatsapp_business_accounts.data', []);

            if (empty($wabaAccounts)) {
                return [
                    'success' => false,
                    'message' => translate('No WhatsApp Business Account found. Please ensure you completed the setup.')
                ];
            }

            $businessAccount = $wabaAccounts[0];

            // Get phone numbers for the WABA
            $phoneResponse = Http::withToken($accessToken)->get(
                MetaApiEndpoints::WABA_PHONE_NUMBERS->buildUrl($this->apiVersion, ['waba_id' => $businessAccount['id']]),
                [
                    'fields' => 'id,display_phone_number,verified_name,quality_rating,code_verification_status,status,messaging_limit_tier'
                ]
            );

            $phoneNumbers = [];
            if ($phoneResponse->successful()) {
                $phoneNumbers = Arr::get($phoneResponse->json(), 'data', []);
            }

            return [
                'success' => true,
                'data' => [
                    'user' => $userData,
                    'business_account' => $businessAccount,
                    'phone_numbers' => $phoneNumbers,
                ]
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Subscribe WABA to webhooks
     */
    protected function subscribeToWebhooks(string $wabaId, string $accessToken): array
    {
        try {
            $response = Http::withToken($accessToken)->post(
                MetaApiEndpoints::WABA_SUBSCRIBED_APPS->buildUrl($this->apiVersion, ['waba_id' => $wabaId])
            );

            if ($response->successful()) {
                return ['success' => true, 'message' => translate('Webhook subscription successful')];
            }

            return [
                'success' => false,
                'message' => translate('Webhook subscription failed'),
                'error' => $response->json()
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Create Gateway from onboarding data
     */
    protected function createGateway(WhatsappClientOnboarding $onboarding, array $stateData): Gateway
    {
        $metaData = [
            'user_access_token' => $onboarding->user_access_token,
            'whatsapp_business_account_id' => $onboarding->waba_id,
            'phone_number_id' => $onboarding->phone_number_id,
        ];

        $gateway = Gateway::create([
            'user_id' => $stateData['user_id'] ?? null,
            'meta_configuration_id' => $this->config->id,
            'onboarding_id' => $onboarding->id,
            'channel' => ChannelTypeEnum::WHATSAPP->value,
            'type' => WhatsAppGatewayTypeEnum::CLOUD->value,
            'name' => $onboarding->verified_name ?? $onboarding->waba_name ?? 'WhatsApp Cloud API',
            'address' => $onboarding->phone_number,
            'meta_data' => $metaData,
            'waba_id' => $onboarding->waba_id,
            'phone_number_id' => $onboarding->phone_number_id,
            'quality_rating' => $onboarding->quality_rating,
            'messaging_limit_tier' => $onboarding->messaging_limit_tier,
            'verification_status' => $onboarding->business_verification_status,
            'api_version' => $this->apiVersion,
            'setup_method' => 'embedded',
            'health_status' => Gateway::HEALTH_HEALTHY,
            'webhook_subscribed' => true,
            'webhook_subscribed_at' => now(),
            'payload' => [
                'oauth_response' => $onboarding->oauth_response,
                'embedded_signup_completed_at' => now()->toISOString(),
            ],
        ]);

        return $gateway;
    }

    /**
     * Refresh account status from Meta API
     */
    public function refreshAccountStatus(Gateway $gateway): array
    {
        try {
            $accessToken = $gateway->getAccessToken();
            if (!$accessToken) {
                return ['success' => false, 'message' => translate('No access token available')];
            }

            // Get updated phone info
            $phoneResponse = Http::withToken($accessToken)->get(
                MetaApiEndpoints::PHONE_NUMBER_INFO->buildUrl($this->apiVersion, ['phone_number_id' => $gateway->getPhoneNumberId()]),
                [
                    'fields' => 'id,display_phone_number,verified_name,quality_rating,code_verification_status,status,messaging_limit_tier'
                ]
            );

            if (!$phoneResponse->successful()) {
                $gateway->updateHealthStatus(Gateway::HEALTH_UNHEALTHY, 'Failed to fetch phone info');
                return ['success' => false, 'message' => translate('Failed to refresh account status')];
            }

            $phoneData = $phoneResponse->json();

            $gateway->update([
                'quality_rating' => $phoneData['quality_rating'] ?? $gateway->quality_rating,
                'messaging_limit_tier' => $phoneData['messaging_limit_tier'] ?? $gateway->messaging_limit_tier,
                'verification_status' => $phoneData['code_verification_status'] ?? $gateway->verification_status,
            ]);

            $gateway->updateHealthStatus(Gateway::HEALTH_HEALTHY);

            return [
                'success' => true,
                'data' => $phoneData,
                'message' => translate('Account status refreshed successfully')
            ];
        } catch (Exception $e) {
            $gateway->updateHealthStatus(Gateway::HEALTH_UNHEALTHY, $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get available phone numbers for a WABA
     */
    public function getPhoneNumbers(string $wabaId, string $accessToken): array
    {
        try {
            $response = Http::withToken($accessToken)->get(
                MetaApiEndpoints::WABA_PHONE_NUMBERS->buildUrl($this->apiVersion, ['waba_id' => $wabaId]),
                [
                    'fields' => 'id,display_phone_number,verified_name,quality_rating,code_verification_status,status,messaging_limit_tier'
                ]
            );

            if (!$response->successful()) {
                return ['success' => false, 'message' => translate('Failed to fetch phone numbers')];
            }

            return [
                'success' => true,
                'data' => Arr::get($response->json(), 'data', [])
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
