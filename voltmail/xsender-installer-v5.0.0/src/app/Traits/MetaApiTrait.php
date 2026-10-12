<?php

namespace App\Traits;

use Exception;
use App\Models\User;
use App\Models\Gateway;
use App\Enums\SettingKey;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Enums\MetaApiEndpoints;
use App\Models\MetaConfiguration;
use Illuminate\Support\Facades\Http;
use App\Models\WhatsappClientOnboarding;
use App\Enums\System\Gateway\WhatsAppGatewayTypeEnum;

trait MetaApiTrait
{
     protected string $metaApiBaseUrl = 'https://www.facebook.com';
     protected string $metaApiGraphUrl = 'https://graph.facebook.com';
     protected string $defaultMetaApiVersion = MetaApiEndpoints::CURRENT_VERSION;

     public function makeMetaApiRequest(MetaApiEndpoints|string $endpoint, array $params = [], string $method = 'get', ?string $version = null): array
     {
          try {
               $endpointValue = $endpoint instanceof MetaApiEndpoints ? $endpoint->value : $endpoint; 
               $version = $version ?? $this->defaultMetaApiVersion;
               // OAuth endpoints don't use API version prefix
               $isOAuthEndpoint = in_array($endpoint, [
                    MetaApiEndpoints::OAUTH_ACCESS_TOKEN,
                    MetaApiEndpoints::OAUTH_DEBUG_TOKEN,
               ], true);

               $url = $isOAuthEndpoint
                    ? "{$this->metaApiGraphUrl}" . $this->buildEndpoint($endpointValue, $params)
                    : "{$this->metaApiGraphUrl}/{$version}/" . $this->buildEndpoint($endpointValue, $params);

               $http = Http::withHeaders([]);
               if ($method === 'post') {
                    $http = $http->asForm();
               }
               if (!$isOAuthEndpoint) {
                    $http = $http->withHeaders(
                         collect($params)->mapWithKeys(function ($value, $key) {
                              return $key === 'access_token' ? ['Authorization' => "Bearer $value"] : [];
                         })->all()
                    );
               }
               // 'Content-Type' => 'application/json',

               $response = $http->$method($url, $params);

               if (!$response->successful()) {
                    $error = Arr::get($response->json(), 'error', []);
                    return [
                         'success' => false,
                         'message' => Arr::get($error, 'message', 'Unknown error'),
                         'error' => $error
                    ];
               }

               $data = $response->json();
               if ($endpoint === MetaApiEndpoints::OAUTH_ACCESS_TOKEN && !Arr::has($data, 'access_token')) {
                    return [
                         'success' => false,
                         'message' => 'No access token in response',
                         'error' => $data
                    ];
               }

               return [
                    'success' => true,
                    'data' => $data
               ];
          } catch (Exception $e) {
               
               return [
                    'success' => false,
                    'message' => $e->getMessage()
               ];
          }
     }

     private function buildEndpoint(string $endpoint, array &$params): string
     {
          return collect(explode('/', $endpoint))->map(function ($segment) use (&$params) {
               if (str_starts_with($segment, ':')) {
                    $key = ltrim($segment, ':');
                    $value = Arr::get($params, $key);
                    Arr::forget($params, $key);
                    return $value ?? $segment;
               }
               return $segment;
          })->implode('/');
     }

     /**
      * Initiate Meta Embedded Signup with optional Meta 2025 config_id support
      *
      * @param Request $request
      * @param string $redirectRoute
      * @param array $scopes
      * @param string $feature
      * @param string $flow
      * @param User|null $user
      * @param MetaConfiguration|null $metaConfig Optional MetaConfiguration for config_id support
      * @return array
      */
     public function initiateMetaEmbeddedSignup(
          Request $request,
          string $redirectRoute,
          array $scopes = ['whatsapp_business_messaging', 'whatsapp_business_management', 'business_management'],
          string $feature = 'whatsapp_embedded_signup',
          string $flow = 'signup',
          User|null $user = null,
          ?MetaConfiguration $metaConfig = null
     ): array {
          try {
               // Use MetaConfiguration if provided, otherwise fallback to legacy settings
               $appId = $metaConfig?->app_id ?? site_settings(SettingKey::META_APP_ID->value);
               $appSecret = $metaConfig?->app_secret ?? site_settings(SettingKey::META_APP_SECRET->value);
               $configId = $metaConfig?->config_id; // Meta 2025 requirement
               $apiVersion = $metaConfig?->api_version ?? $this->defaultMetaApiVersion;

               if (empty($appId) || empty($appSecret)) {
                    return [
                         'success' => false,
                         'message' => translate('Meta App credentials not configured. Please configure App ID and App Secret in settings.')
                    ];
               }

               // Build state with configuration reference
               $stateData = [
                    'user_type' => $user ? "user" : 'admin',
                    'user_id' => $user?->id,
                    'timestamp' => now()->timestamp,
                    'nonce' => Str::random(16),
               ];

               // Add meta configuration reference if available
               if ($metaConfig) {
                    $stateData['meta_configuration_id'] = $metaConfig->id;
               }

               $state = base64_encode(json_encode($stateData));

               // Build extras with Meta 2025 config_id if available
               $extras = [
                    'feature' => $feature,
                    'version' => 2,
                    'setup' => [
                         'solution' => 'whatsapp',
                         'flow' => $flow
                    ]
               ];

               // CRITICAL: Add config_id for Tech Provider flow (Meta 2025 requirement)
               if ($configId) {
                    $extras['setup']['config_id'] = $configId;
               }

               // Create onboarding record if using MetaConfiguration
               $onboarding = null;
               if ($metaConfig) {
                    $onboarding = WhatsappClientOnboarding::create([
                         'meta_configuration_id' => $metaConfig->id,
                         'user_id' => $user?->id,
                         'state' => $state,
                         'onboarding_status' => WhatsappClientOnboarding::STATUS_INITIATED,
                         'initiated_at' => now(),
                    ]);
               }

               $signupUrl = "{$this->metaApiBaseUrl}/{$apiVersion}/dialog/oauth?" . http_build_query([
                    'client_id' => $appId,
                    'redirect_uri' => route($redirectRoute),
                    'response_type' => 'code',
                    'state' => $state,
                    'scope' => implode(',', $scopes),
                    'extras' => json_encode($extras)
               ]);

               return [
                    'success' => true,
                    'signup_url' => $signupUrl,
                    'onboarding_id' => $onboarding?->id,
                    'message' => translate('Embedded signup URL generated successfully')
               ];
          } catch (Exception $e) {
               return [
                    'success' => false,
                    'message' => translate('Failed to initiate embedded signup: ') . $e->getMessage()
               ];
          }
     }

     /**
      * Initiate Meta Embedded Signup with MetaConfiguration (Meta 2025 compliant)
      *
      * @param Request $request
      * @param MetaConfiguration $metaConfig
      * @param string $redirectRoute
      * @param User|null $user
      * @return array
      */
     public function initiateEmbeddedSignupWithConfig(
          Request $request,
          MetaConfiguration $metaConfig,
          string $redirectRoute,
          ?User $user = null
     ): array {
          return $this->initiateMetaEmbeddedSignup(
               $request,
               $redirectRoute,
               ['whatsapp_business_messaging', 'whatsapp_business_management', 'business_management'],
               'whatsapp_embedded_signup',
               'signup',
               $user,
               $metaConfig
          );
     }

     /**
      * Handle Meta OAuth Callback with enhanced tracking
      *
      * @param Request $request
      * @param User|null $user
      * @param callable|null $onSuccess
      * @return array
      */
     public function handleMetaOAuthCallback(Request $request, ?User $user = null, ?callable $onSuccess = null): array
     {
          // Check for OAuth error
          if ($request->has('error')) {
               $this->updateOnboardingStatus($request->state, WhatsappClientOnboarding::STATUS_FAILED, [
                    'error' => $request->error,
                    'error_description' => $request->error_description ?? null,
               ]);

               return [
                    'success' => false,
                    'message' => translate('Embedded signup failed: ') . ($request->error_description ?? $request->error)
               ];
          }

          // Validate state
          $stateResult = $this->validateMetaState($request->state);
          if (!Arr::get($stateResult, 'success')) {
               return $stateResult;
          }

          $state = Arr::get($stateResult, 'data');
          $metaConfig = null;

          // Get MetaConfiguration if referenced in state
          if (Arr::has($state, 'meta_configuration_id')) {
               $metaConfig = MetaConfiguration::find(Arr::get($state, 'meta_configuration_id'));
          }

          // Exchange code for token
          $tokenResult = $this->exchangeCodeForAccessToken(
               $request->code,
               $metaConfig,
               route($this->getCallbackRoute($user))
          );

          if (!Arr::get($tokenResult, 'success')) {
               $this->updateOnboardingStatus($request->state, WhatsappClientOnboarding::STATUS_FAILED, [
                    'error' => 'token_exchange_failed',
                    'message' => Arr::get($tokenResult, 'message'),
               ]);
               return $tokenResult;
          }

          $tokenData = Arr::get($tokenResult, 'data');

          // Get complete account info
          $accountResult = $this->fetchWhatsAppAccountInfo($tokenData, $metaConfig);
          if (!Arr::get($accountResult, 'success')) {
               $this->updateOnboardingStatus($request->state, WhatsappClientOnboarding::STATUS_PHONE_SELECTED, [
                    'error' => 'account_info_failed',
                    'message' => Arr::get($accountResult, 'message'),
               ]);
               return $accountResult;
          }

          $accountInfo = Arr::get($accountResult, 'data');

          // Create or update gateway
          $gatewayResult = $this->createGatewayFromCallback($state, $tokenData, $accountInfo, $metaConfig, $user);
          if (!Arr::get($gatewayResult, 'success')) {
               return $gatewayResult;
          }

          // Update onboarding status to completed
          $this->updateOnboardingStatus($request->state, WhatsappClientOnboarding::STATUS_COMPLETED, [
               'gateway_id' => Arr::get($gatewayResult, 'gateway_id'),
          ]);

          // Call success callback if provided
          if ($onSuccess) {
               $onSuccess(Arr::get($gatewayResult, 'gateway'));
          }

          return [
               'success' => true,
               'gateway_id' => Arr::get($gatewayResult, 'gateway_id'),
               'message' => translate('WhatsApp Business Account connected successfully')
          ];
     }

     /**
      * Validate Meta OAuth state
      *
      * @param string|null $stateParam
      * @return array
      */
     protected function validateMetaState(?string $stateParam): array
     {
          try {
               if (empty($stateParam)) {
                    return ['success' => false, 'message' => translate('State parameter is missing')];
               }

               $state = json_decode(base64_decode($stateParam), true);

               if (!$state || !is_array($state)) {
                    return ['success' => false, 'message' => translate('Invalid state structure')];
               }

               $required = ['user_type', 'timestamp', 'nonce'];
               $missing = collect($required)->filter(fn($field) => !array_key_exists($field, $state))->values()->all();

               if (!empty($missing)) {
                    return ['success' => false, 'message' => translate('Missing field: ') . implode(', ', $missing)];
               }

               // Check if state is expired (1 hour)
               if (now()->timestamp - Arr::get($state, 'timestamp') > 3600) {
                    return ['success' => false, 'message' => translate('Session expired. Please try again.')];
               }

               return ['success' => true, 'data' => $state];
          } catch (Exception $e) {
               return ['success' => false, 'message' => translate('State validation error: ') . $e->getMessage()];
          }
     }

     /**
      * Exchange OAuth code for access token
      *
      * @param string $code
      * @param MetaConfiguration|null $metaConfig
      * @param string $redirectUri
      * @return array
      */
     protected function exchangeCodeForAccessToken(string $code, ?MetaConfiguration $metaConfig, string $redirectUri): array
     {
          $params = [
               'client_id' => $metaConfig?->app_id ?? site_settings(SettingKey::META_APP_ID->value),
               'client_secret' => $metaConfig?->app_secret ?? site_settings(SettingKey::META_APP_SECRET->value),
               'code' => $code,
               'redirect_uri' => $redirectUri,
          ];

          return $this->makeMetaApiRequest(MetaApiEndpoints::OAUTH_ACCESS_TOKEN, $params, 'post');
     }

     /**
      * Fetch WhatsApp account info from Meta API
      *
      * @param array $tokenData
      * @param MetaConfiguration|null $metaConfig
      * @return array
      */
     protected function fetchWhatsAppAccountInfo(array $tokenData, ?MetaConfiguration $metaConfig = null): array
     {
          $accessToken = Arr::get($tokenData, 'access_token');
          $apiVersion = $metaConfig?->api_version ?? $this->defaultMetaApiVersion;

          // Step 1: Use debug_token to get granular_scopes (Meta's current approach for embedded signup)
          $appId     = $metaConfig?->app_id ?? site_settings(SettingKey::META_APP_ID->value);
          $appSecret = $metaConfig?->app_secret ?? site_settings(SettingKey::META_APP_SECRET->value);
          $appAccessToken = $appId . '|' . $appSecret;

          $debugResult = $this->makeMetaApiRequest(MetaApiEndpoints::OAUTH_DEBUG_TOKEN, [
               'input_token'  => $accessToken,
               'access_token' => $appAccessToken,
          ], 'get', $apiVersion);

          $wabaIds = [];
          if (Arr::get($debugResult, 'success')) {
               $granularScopes = Arr::get($debugResult, 'data.data.granular_scopes', []);
               foreach ($granularScopes as $scope) {
                    if (in_array(Arr::get($scope, 'scope'), ['whatsapp_business_management', 'whatsapp_business_messaging'])) {
                         $wabaIds = array_merge($wabaIds, Arr::get($scope, 'target_ids', []));
                    }
               }
               $wabaIds = array_unique($wabaIds);
          }

          $businessAccount = null;
          $userData = null;

          // Step 2: If we have WABA IDs from granular_scopes, fetch first WABA's details
          if (!empty($wabaIds)) {
               $wabaId = $wabaIds[0];
               $wabaResult = $this->makeMetaApiRequest(MetaApiEndpoints::WABA_INFO, [
                    'waba_id'      => $wabaId,
                    'access_token' => $accessToken,
                    'fields'       => 'id,name,currency,timezone_id,message_template_namespace,account_review_status',
               ], 'get', $apiVersion);

               if (Arr::get($wabaResult, 'success')) {
                    $businessAccount = Arr::get($wabaResult, 'data');
                    $userData = ['id' => null, 'name' => Arr::get($businessAccount, 'name')];
               }
          }

          // Step 3: Fallback to legacy whatsapp_business_accounts field on /me
          if (!$businessAccount) {
               $userParams = [
                    'access_token' => $accessToken,
                    'fields' => 'id,name,whatsapp_business_accounts{id,name,currency,timezone_id,message_template_namespace,account_review_status}',
               ];

               $userResult = $this->makeMetaApiRequest(MetaApiEndpoints::USER_INFO, $userParams, 'get', $apiVersion);

               if (Arr::get($userResult, 'success')) {
                    $userData = Arr::get($userResult, 'data');
                    $legacyWabas = Arr::get($userData, 'whatsapp_business_accounts.data', []);
                    if (!empty($legacyWabas)) {
                         $businessAccount = $legacyWabas[0];
                    }
               }
          }

          if (!$businessAccount) {
               return ['success' => false, 'message' => translate('No WhatsApp Business Account found. Please ensure embedded signup completed successfully.')];
          }

          // Step 4: Get phone numbers for the WABA
          $phoneParams = [
               'access_token' => $accessToken,
               'business_account_id' => Arr::get($businessAccount, 'id'),
               'fields' => 'id,display_phone_number,verified_name,quality_rating,status,messaging_limit_tier',
          ];

          $phoneResult = $this->makeMetaApiRequest(MetaApiEndpoints::PHONE_NUMBERS, $phoneParams, 'get', $apiVersion);

          if (!Arr::get($phoneResult, 'success')) {
               return $phoneResult;
          }

          return [
               'success' => true,
               'data' => [
                    'user' => $userData,
                    'business_account' => $businessAccount,
                    'phone_numbers' => Arr::get($phoneResult, 'data.data', []),
               ]
          ];
     }

     /**
      * Create gateway from OAuth callback data
      *
      * @param array $state
      * @param array $tokenData
      * @param array $accountInfo
      * @param MetaConfiguration|null $metaConfig
      * @param User|null $user
      * @return array
      */
     protected function createGatewayFromCallback(
          array $state,
          array $tokenData,
          array $accountInfo,
          ?MetaConfiguration $metaConfig,
          ?User $user
     ): array {
          try {
               $businessAccount = Arr::get($accountInfo, 'business_account');
               $phoneNumbers = Arr::get($accountInfo, 'phone_numbers', []);
               $primaryPhone = Arr::first($phoneNumbers);

               // Build meta_data for token storage
               $metaData = [
                    'user_access_token' => Arr::get($tokenData, 'access_token'),
                    'whatsapp_business_account_id' => Arr::get($businessAccount, 'id'),
               ];

               if ($primaryPhone) {
                    $metaData['phone_number_id'] = Arr::get($primaryPhone, 'id');
               }

               // Build gateway data
               $gatewayData = [
                    'user_id' => $user?->id,
                    'meta_configuration_id' => $metaConfig?->id,
                    'type' => WhatsAppGatewayTypeEnum::CLOUD->value,
                    'channel' => 'whatsapp',
                    'name' => Arr::get($primaryPhone, 'verified_name', Arr::get($businessAccount, 'name', 'WhatsApp Gateway')),
                    'address' => Arr::get($primaryPhone, 'display_phone_number'),
                    'status' => 'connected',
                    'meta_data' => json_encode($metaData),
                    'waba_id' => Arr::get($businessAccount, 'id'),
                    'phone_number_id' => Arr::get($primaryPhone, 'id'),
                    'verified_name' => Arr::get($primaryPhone, 'verified_name'),
                    'quality_rating' => Arr::get($primaryPhone, 'quality_rating'),
                    'messaging_limit_tier' => Arr::get($primaryPhone, 'messaging_limit_tier'),
                    'payload' => json_encode([
                         'token_data' => $tokenData,
                         'account_info' => $accountInfo,
                         'embedded_signup_completed_at' => now()->toISOString(),
                         'state_info' => $state,
                    ]),
                    'api_version' => $metaConfig?->api_version ?? $this->defaultMetaApiVersion,
                    'setup_method' => $metaConfig ? 'embedded_v2' : 'embedded',
                    'bulk_contact_limit' => 1,
               ];

               $gateway = new Gateway();
               $gateway->fill($gatewayData);
               $gateway->save();

               return [
                    'success' => true,
                    'gateway' => $gateway,
                    'gateway_id' => $gateway->id,
               ];
          } catch (Exception $e) {
               return [
                    'success' => false,
                    'message' => translate('Failed to create gateway: ') . $e->getMessage()
               ];
          }
     }

     /**
      * Update onboarding status
      *
      * @param string|null $state
      * @param string $status
      * @param array $additionalData
      * @return void
      */
     protected function updateOnboardingStatus(?string $state, string $status, array $additionalData = []): void
     {
          if (empty($state)) {
               return;
          }

          try {
               $onboarding = WhatsappClientOnboarding::where('state', $state)->first();

               if ($onboarding) {
                    $updateData = ['onboarding_status' => $status];

                    if ($status === WhatsappClientOnboarding::STATUS_COMPLETED) {
                         $updateData['completed_at'] = now();
                    }

                    if ($status === WhatsappClientOnboarding::STATUS_FAILED) {
                         $updateData['last_error_at'] = now();
                         $updateData['error_message'] = Arr::get($additionalData, 'message') ?? Arr::get($additionalData, 'error');
                         $updateData['error_details'] = $additionalData;
                    }

                    if (Arr::has($additionalData, 'gateway_id')) {
                         $updateData['gateway_id'] = Arr::get($additionalData, 'gateway_id');
                    }

                    $onboarding->update($updateData);
               }
          } catch (Exception $e) {
               // Log silently - don't break the flow
          }
     }

     /**
      * Get callback route based on user type
      *
      * @param User|null $user
      * @return string
      */
     protected function getCallbackRoute(?User $user): string
     {
          return $user
               ? 'user.gateway.whatsapp.cloud.api.embedded.callback'
               : 'admin.gateway.whatsapp.cloud.api.embedded.callback';
     }

     /**
      * Get default MetaConfiguration
      * Falls back to any active configuration if no default is explicitly set
      *
      * @return MetaConfiguration|null
      */
     protected function getDefaultMetaConfiguration(): ?MetaConfiguration
     {
          // First try to get the explicitly set default
          $default = MetaConfiguration::where('is_default', true)
               ->where('status', 'active')
               ->first();

          if ($default) {
               return $default;
          }

          // Fallback to any active configuration (prefer production environment)
          return MetaConfiguration::where('status', 'active')
               ->orderByRaw("CASE WHEN environment = 'production' THEN 0 ELSE 1 END")
               ->orderBy('created_at', 'desc')
               ->first();
     }
}