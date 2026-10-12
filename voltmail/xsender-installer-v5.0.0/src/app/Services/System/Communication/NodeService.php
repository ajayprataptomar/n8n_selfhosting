<?php

namespace App\Services\System\Communication;

use App\Models\Gateway;
use App\Models\Setting;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use App\Enums\Common\Status;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use App\Enums\System\ChannelTypeEnum;
use App\Enums\System\Gateway\WhatsAppGatewayTypeEnum;
use App\Exceptions\ApplicationException;
use App\Http\Requests\WhatsappServerRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class NodeService
{
     /**
      * Get base URL for WhatsApp Node service
      *
      * @return string
      */
     private function getBaseUrl(): string
     {
          return env('WP_SERVER_URL', 'http://127.0.0.1:3001');
     }

     /**
      * Get or generate API key for Node service communication
      *
      * @return string
      */
     private function getApiKey(): string
     {
          $apiKey = env('WP_API_KEY', '');

          // If API key is empty, generate one and save to .env
          if (empty($apiKey)) {
               $apiKey = $this->generateAndSaveApiKey();
          }

          return $apiKey;
     }

     /**
      * Generate a secure API key and save to .env file
      *
      * @return string
      */
     private function generateAndSaveApiKey(): string
     {
          $apiKey = self::generateApiKey();

          try {
               $envFile = base_path('.env');
               if (File::exists($envFile)) {
                    $envContent = File::get($envFile);

                    // Check if WP_API_KEY exists in .env
                    if (Str::contains($envContent, 'WP_API_KEY=')) {
                         // Update existing
                         $envContent = preg_replace('/WP_API_KEY=.*/', 'WP_API_KEY=' . $apiKey, $envContent);
                    } else {
                         // Add new
                         $envContent .= PHP_EOL . 'WP_API_KEY=' . $apiKey;
                    }

                    File::put($envFile, $envContent);
                    Log::info('Generated new WP_API_KEY for Node service communication');

                    // Also sync to Node's .env file for immediate compatibility
                    $this->syncApiKeyToNodeEnvFile($apiKey);
               }
          } catch (\Exception $e) {
               Log::error('Failed to save WP_API_KEY to .env: ' . $e->getMessage());
          }

          return $apiKey;
     }

     /**
      * Sync API key directly to Node service .env file (filesystem)
      * This is a backup mechanism - Node also reads from Laravel's .env automatically
      *
      * @param string $apiKey
      * @return void
      */
     private function syncApiKeyToNodeEnvFile(string $apiKey): void
     {
          try {
               $possiblePaths = [
                    base_path('../xsender-whatsapp-service/.env'),
                    base_path('../../xsender-whatsapp-service/.env'),
               ];

               foreach ($possiblePaths as $nodeEnvPath) {
                    $resolved = realpath(dirname($nodeEnvPath));
                    if (!$resolved) {
                         continue;
                    }

                    $fullPath = $resolved . DIRECTORY_SEPARATOR . '.env';
                    if (!File::exists($fullPath)) {
                         continue;
                    }

                    $content = File::get($fullPath);
                    if (Str::contains($content, 'API_KEY=')) {
                         $content = preg_replace('/^API_KEY=.*$/m', 'API_KEY=' . $apiKey, $content);
                    } else {
                         $content .= PHP_EOL . 'API_KEY=' . $apiKey;
                    }

                    File::put($fullPath, $content);
                    Log::info('Synced API key to Node service .env file');
                    break;
               }
          } catch (\Exception $e) {
               // Silently fail - Node can read from Laravel's .env as fallback
               Log::debug('Could not sync API key to Node .env: ' . $e->getMessage());
          }
     }

     /**
      * Get headers with API key authentication
      *
      * @return array
      */
     private function getHeaders(): array
     {
          return [
               'X-API-Key' => $this->getApiKey(),
               'Content-Type' => 'application/json',
          ];
     }

     /**
      * Push configuration to Node service
      * This should be called when Laravel env changes
      *
      * @return bool
      */
     public function pushConfigToNode(): bool
     {
          try {
               $apiKey = $this->getApiKey();
               $domain = $this->domain();

               // Get purchase credentials - prefer database, fallback to env
               $purchaseKey = $this->getPurchaseKey();
               $envatoUsername = $this->getEnvatoUsername();

               $config = [
                    // API & Security
                    'apiKey' => $apiKey,
                    'allowedOrigins' => explode(',', env('WP_ALLOWED_ORIGINS', env('APP_URL', '*'))),
                    'domain' => $domain,

                    // License Verification
                    'purchaseKey' => $purchaseKey,
                    'envatoUsername' => $envatoUsername,
                    'softwareId' => env('SOFTWARE_ID', config('installer.software_id', 'BX32DOTW4Q797ZF3')),
                    'version' => env('VERSION', config('installer.core.appVersion', '4.1')),
               ];

               Log::debug('Pushing config to Node service', [
                    'domain' => $domain,
                    'hasPurchaseKey' => !empty($purchaseKey),
                    'hasEnvatoUsername' => !empty($envatoUsername),
               ]);

               $response = Http::timeout(15)
                    ->post($this->getBaseUrl() . '/config/update', $config);

               if ($response->status() === 200) {
                    Log::info('WhatsApp Node service configuration updated successfully');
                    Cache::put('wp_node_configured', true, now()->addDay());
                    return true;
               }

               Log::error('Failed to update WhatsApp Node configuration', [
                    'status' => $response->status(),
                    'body' => $response->body(),
               ]);
               return false;

          } catch (\Exception $e) {
               Log::error('Error pushing config to WhatsApp Node service: ' . $e->getMessage());
               return false;
          }
     }

     /**
      * Get purchase key from database or env
      *
      * @return string
      */
     private function getPurchaseKey(): string
     {
          // Try database first
          $fromDb = site_settings('purchase_key');
          if (!empty($fromDb)) {
               return $fromDb;
          }

          // Fallback to env
          return env('PURCHASE_KEY', '');
     }

     /**
      * Get Envato username from database or env
      *
      * @return string
      */
     private function getEnvatoUsername(): string
     {
          // Try database first
          $fromDb = site_settings('envato_username');
          if (!empty($fromDb)) {
               return $fromDb;
          }

          // Fallback to env
          return env('ENVATO_USERNAME', '');
     }

     /**
      * Ensure Node service is configured
      * Call this before any operation
      *
      * @return void
      */
     private function ensureConfigured(): void
     {
          if (!Cache::get('wp_node_configured')) {
               $this->pushConfigToNode();
          }
     }

     /**
      * Check Node service health
      * Returns health status from /health endpoint
      *
      * @return array [isHealthy, data, error]
      */
     public function checkHealth(): array
     {
          try {
               $response = Http::timeout(5)
                    ->get($this->getBaseUrl() . '/health');

               if ($response->status() === 200) {
                    $body = json_decode($response->body());

                    // Also check config sync status
                    $syncStatus = $this->checkConfigSync();

                    return [
                         'healthy' => true,
                         'data' => $body, // Return full response as data
                         'message' => 'Service is healthy',
                         'error' => null,
                         'configSynced' => $syncStatus['synced'],
                         'syncMessage' => $syncStatus['message'],
                    ];
               }

               return [
                    'healthy' => false,
                    'data' => null,
                    'message' => 'Service returned error',
                    'error' => 'HTTP ' . $response->status(),
                    'configSynced' => false,
                    'syncMessage' => 'Cannot check - service offline',
               ];

          } catch (\Exception $e) {
               return [
                    'healthy' => false,
                    'data' => null,
                    'message' => 'Cannot connect to Node service',
                    'error' => $e->getMessage(),
                    'configSynced' => false,
                    'syncMessage' => 'Cannot check - service offline',
               ];
          }
     }

     /**
      * Check if Laravel and Node configurations are in sync
      *
      * @return array
      */
     public function checkConfigSync(): array
     {
          try {
               // Get both config status and env values
               $configResponse = Http::timeout(5)->get($this->getBaseUrl() . '/config/status');
               $envResponse = Http::timeout(5)->get($this->getBaseUrl() . '/config/env');

               if ($configResponse->status() !== 200) {
                    return [
                         'synced' => false,
                         'message' => 'Cannot check Node config status',
                    ];
               }

               $nodeConfig = json_decode($configResponse->body(), true);
               $nodeData = $nodeConfig['data'] ?? [];

               $nodeEnv = [];
               if ($envResponse->status() === 200) {
                    $envData = json_decode($envResponse->body(), true);
                    $nodeEnv = $envData['data'] ?? [];
               }

               $issues = [];

               // Check if Node has been configured by Laravel
               if (!($nodeData['configured'] ?? false)) {
                    $issues[] = 'Node not configured by Laravel yet';
               }

               // Check if API key is set on Node
               if (!($nodeData['hasApiKey'] ?? false)) {
                    $issues[] = 'Node API key not set';
               }

               // Check if purchase key is set
               if (!($nodeData['hasPurchaseKey'] ?? false)) {
                    $issues[] = 'License key not synced';
               }

               // Check domain
               $laravelDomain = $this->domain();
               $nodeDomain = $nodeData['domain'] ?? '';
               if (!empty($laravelDomain) && $nodeDomain !== $laravelDomain) {
                    $issues[] = 'Domain mismatch';
               }

               // Check if Node .env matches Laravel .env (HOST/PORT)
               $laravelHost = env('WP_SERVER_HOST', '127.0.0.1');
               $laravelPort = env('WP_SERVER_PORT', '3001');
               $nodeHost = $nodeEnv['serverHost'] ?? '';
               $nodePort = $nodeEnv['serverPort'] ?? '';

               if (!empty($nodeHost) && $nodeHost !== $laravelHost) {
                    $issues[] = "Host mismatch (Laravel: {$laravelHost}, Node: {$nodeHost})";
               }
               if (!empty($nodePort) && $nodePort !== $laravelPort) {
                    $issues[] = "Port mismatch (Laravel: {$laravelPort}, Node: {$nodePort})";
               }

               if (empty($issues)) {
                    return [
                         'synced' => true,
                         'message' => 'Configuration synchronized',
                    ];
               }

               return [
                    'synced' => false,
                    'message' => implode(', ', $issues),
               ];

          } catch (\Exception $e) {
               return [
                    'synced' => false,
                    'message' => 'Cannot connect to Node: ' . $e->getMessage(),
               ];
          }
     }

     /**
      * Generate a secure API key (32 characters)
      * Used by both installation and admin panel
      *
      * @return string
      */
     public static function generateApiKey(): string
     {
          return Str::random(32);
     }

     public function sessionStatusUpdate(Gateway $whatsapp, string $value) {

          $status  = false;
          $message = translate("Something went wrong");
          
          switch ($value) {
  
              case 'connected':
  
                  $session = $this->sessionStatus($whatsapp->name);
                  if ($session->status() == 200) {
  
                      $status = true;
                      $message = translate("Successfully whatsapp sessions reconnect");
                  } else {
                      
                      $this->sessionDelete($whatsapp->name);
                      $message = translate("Successfully whatsapp sessions disconnected");
                  }
                  break;
  
              case 'disconnected':
  
                  $session = $this->sessionDelete($whatsapp->name);
                  
                  if ($session->status() == 200) {
  
                      $message = translate('Whatsapp Device successfully Deleted');
                  } else {
  
                      $message = translate('Opps! Something went wrong, try again');
                  }
                  break;
  
              default:
  
                  $session = $this->sessionDelete($whatsapp->name);
                  if ($session->status() == 200) {
  
                      $message = translate('Whatsapp Device successfully Deleted');
                  } else {
  
                      $message = translate('Opps! Something went wrong, try again');
                  }
                  break;
          }
  
          $whatsapp->status = $status ? Status::ACTIVE : Status::INACTIVE;
  
          return [
              $whatsapp,
              $message
          ];
      }

     /**
      * generateQr
      *
      * @param Request $request
      * 
      * @return JsonResponse
      */
     public function generateQr(Request $request, ?User $user = null): JsonResponse {

          $gateway = Gateway::when($user, fn(Builder $q): Builder =>
                                        $q->where("user_id", $user->id), 
                                             fn(Builder $q): Builder =>
                                                  $q->whereNull("user_id"))
                                    ->select(["id", "name", "meta_data"])
                                    ->where("channel", ChannelTypeEnum::WHATSAPP)
                                    ->where("type", WhatsAppGatewayTypeEnum::NODE)
                                    ->where('id', $request->input('id'))
                                    ->first();
          if(!$gateway) throw new ApplicationException("Invalid whatsapp device", HttpResponse::HTTP_NOT_FOUND);

          list($response, $responseBody) = $this->sessionCreate($gateway);

          $data = [];
          if ($response->status() === 200) {

               // Check if session reconnected via saved credentials (no QR needed)
               if (!empty($responseBody->data->connected)) {
                    $data['status']  = 301; // Signal "already connected" to frontend JS
                    $data['qr']      = '';
                    $data['message'] = $responseBody->message;
               } else {
                    $data['status']  = $response->status();
                    $data['qr']      = $responseBody->data->qr ?? '';
                    $data['message'] = $responseBody->message;
               }

          } else {

               $msg = $response->status() === 500 ? "Invalid Software License" : $responseBody->message;
               $data['status']  = $response->status();
               $data['qr']      = '';
               $data['message'] = $msg;
          }

          $response = [
               'response' => $gateway,
               'data' => $data
          ];
          return response()->json($response);
     }

     /**
      * Reconnect a disconnected WhatsApp device using saved credentials
      *
      * @param Request $request
      * @param User|null $user
      *
      * @return JsonResponse
      */
     public function reconnectDevice(Request $request, ?User $user = null): JsonResponse {

          $gateway = Gateway::when($user, fn(Builder $q): Builder =>
                                        $q->where("user_id", $user->id),
                                             fn(Builder $q): Builder =>
                                                  $q->whereNull("user_id"))
                                    ->select(["id", "name"])
                                    ->where("channel", ChannelTypeEnum::WHATSAPP)
                                    ->where("type", WhatsAppGatewayTypeEnum::NODE)
                                    ->where('id', $request->input('id'))
                                    ->first();
          if(!$gateway) throw new ApplicationException("Invalid whatsapp device", HttpResponse::HTTP_NOT_FOUND);

          $result = $this->reconnectSession($gateway->name);

          $data = [
               'success' => $result['success'],
               'message' => $result['message'],
               'gateway' => $gateway,
          ];

          return response()->json($data, $result['success'] ? 200 : 400);
     }

     /**
      * confirmDeviceConnection
      *
      * @param Request $request
      * @param User|null $user
      *
      * @return JsonResponse
      */
     public function confirmDeviceConnection(Request $request, ?User $user = null): JsonResponse {

          $gateway = Gateway::when($user, fn(Builder $q): Builder =>
                                   $q->where("user_id", $user->id), 
                                        fn(Builder $q): Builder =>
                                             $q->whereNull("user_id"))
                              ->select(["id", "name", "meta_data", "status"])
                              ->where("channel", ChannelTypeEnum::WHATSAPP)
                              ->where("type", WhatsAppGatewayTypeEnum::NODE)
                              ->where('id', $request->input('id'))
                              ->first();
          if(!$gateway) throw new ApplicationException("Invalid whatsapp device", HttpResponse::HTTP_NOT_FOUND);

          $metaData = $gateway->meta_data;
          $data = [];

          $checkConnection = $this->sessionStatus($gateway->name);
          $responseBody = json_decode($checkConnection->body());

          // Check if session is connected
          if ($checkConnection->status() === 200 && isset($responseBody->data->isSession) && $responseBody->data->isSession) {

               $gateway->status = Status::ACTIVE;

               // Extract WhatsApp number
               if (isset($responseBody->data->wpInfo->id)) {
                    $wpNumber = str_replace('@s.whatsapp.net', '', $responseBody->data->wpInfo->id);
                    $wpNumber = explode(':', $wpNumber);
                    $wpNumber = Arr::get($wpNumber, 0, Arr::get($metaData, "number", ""));
                    $metaData = Arr::set($metaData, "number", $wpNumber);
                    $gateway->meta_data = $metaData;
               }

               $gateway->save();

               // Return success status
               $data['status']  = 301;
               $data['qr']      = asset('assets/file/dashboard/image/done.gif');
               $data['message'] = translate('Successfully connected WhatsApp device');

          } else {
               // Still connecting or not found
               $data['status']  = 200;
               $data['qr']      = '';
               $data['message'] = translate('Connecting... Please wait');
          }

          $response = [
               'response' => $gateway,
               'data' => $data
          ];

          return response()->json($response);
     }

     /**
      * updateNodeServer
      *
      * @param array $data
      *
      * @return RedirectResponse
      */
     public function updateNodeServer(array $data): RedirectResponse{

          $result = $this->updateNodeServerWithResult($data);

          if ($result['synced']) {
               $notify[] = ["success", translate("Server configuration updated and synced successfully")];
          } else {
               $notify[] = ["warning", translate("Server configuration saved but failed to sync with Node service. Please click Reinitialize.")];
          }

          return back()->withNotify($notify);
     }

     /**
      * updateNodeServerWithResult
      * Returns array result instead of RedirectResponse (for AJAX requests)
      *
      * @param array $data
      *
      * @return array
      */
     public function updateNodeServerWithResult(array $data): array {

          $updated_env   = $this->updateEnvParam($data);
          $path          = app()->environmentFilePath();
          foreach ($updated_env as $key => $value) {

               $escaped = preg_quote('='.env($key), '/');

               file_put_contents($path, preg_replace(
                   "/^{$key}{$escaped}/m",
                   "{$key}={$value}",
                   file_get_contents($path)
               ));
          }

          // Clear config cache so new values take effect immediately
          try {
               \Illuminate\Support\Facades\Artisan::call('config:clear');
          } catch (\Exception $e) {
               // Silently fail - might not have artisan access
          }

          // Clear the Node configured flag to force fresh push
          Cache::forget('wp_node_configured');

          // Get values from form data
          $serverHost = Arr::get($data, 'server_host', '127.0.0.1');
          $serverPort = Arr::get($data, 'server_port', '3001');
          $apiKey = Arr::get($data, 'wp_api_key', '');

          // Get CURRENT Node service URL (before update) to call the running service
          $currentUrl = $this->getBaseUrl();

          // Build NEW base URL with potentially new host/port
          $newBaseUrl = "http://{$serverHost}:{$serverPort}";

          $pushResult = false;
          $envUpdateResult = false;
          $restartRequired = false;
          $baseUrl = $currentUrl; // Start with current URL

          try {
              // Step 1: Try to update Node's .env file at CURRENT URL first
              // This handles the case where port hasn't changed yet
              $envResponse = Http::timeout(10)
                   ->post($currentUrl . '/config/update-env', [
                        'serverHost' => $serverHost,
                        'serverPort' => $serverPort,
                        'apiKey' => $apiKey,
                   ]);

              // If current URL fails and it's different from new URL, try new URL
              if ($envResponse->status() !== 200 && $currentUrl !== $newBaseUrl) {
                   Log::info('Current Node URL failed, trying new URL', [
                        'current' => $currentUrl,
                        'new' => $newBaseUrl,
                   ]);
                   $envResponse = Http::timeout(10)
                        ->post($newBaseUrl . '/config/update-env', [
                             'serverHost' => $serverHost,
                             'serverPort' => $serverPort,
                             'apiKey' => $apiKey,
                        ]);
                   if ($envResponse->status() === 200) {
                        $baseUrl = $newBaseUrl; // Use new URL for subsequent calls
                   }
              }

              if ($envResponse->status() === 200) {
                   $envData = $envResponse->json();
                   $envUpdateResult = true;
                   $restartRequired = $envData['data']['restartRequired'] ?? false;
                   Log::info('Node .env file updated successfully', [
                        'updated' => $envData['data']['updated'] ?? [],
                        'restartRequired' => $restartRequired,
                   ]);
              }

              // Step 2: Push runtime config (API key, license, etc.)
              if (!empty($apiKey)) {
                   $response = Http::timeout(15)
                        ->post($baseUrl . '/config/update', [
                             'apiKey' => $apiKey,
                             'allowedOrigins' => explode(',', env('WP_ALLOWED_ORIGINS', env('APP_URL', '*'))),
                             'domain' => $this->domain(),
                             'purchaseKey' => $this->getPurchaseKey(),
                             'envatoUsername' => $this->getEnvatoUsername(),
                             'softwareId' => env('SOFTWARE_ID', config('installer.software_id', 'BX32DOTW4Q797ZF3')),
                             'version' => env('VERSION', config('installer.core.appVersion', '4.1')),
                        ]);

                   $pushResult = $response->status() === 200;

                   if ($pushResult) {
                        Cache::put('wp_node_configured', true, now()->addDay());
                        Log::info('WhatsApp Node service configuration synced with new API key');
                   }
              } else {
                   // No new API key, use regular push
                   $pushResult = $this->pushConfigToNode();
              }
          } catch (\Exception $e) {
              Log::warning('Failed to sync config with Node service: ' . $e->getMessage());
          }

          // Determine overall success and message
          $synced = $pushResult && $envUpdateResult;

          if ($synced && $restartRequired) {
               $message = translate("Configuration saved and synced. Node service restart required for HOST/PORT changes to take effect.");
          } elseif ($synced) {
               $message = translate("Server configuration updated and synced successfully.");
          } elseif ($envUpdateResult) {
               $message = translate("Node .env updated but runtime sync failed. Please click Reinitialize.");
          } else {
               $message = translate("Configuration saved to Laravel but failed to sync with Node service. Please restart Node service or click Reinitialize.");
          }

          return [
               'success' => true,
               'synced' => $synced,
               'envUpdated' => $envUpdateResult,
               'restartRequired' => $restartRequired,
               'message' => $message,
          ];
     }
     /**
      * updateEnvParam
      *
      * @param array $request
      *
      * @return array
      */
     public function updateEnvParam(array $data): array {

          $serverHost         = Arr::get($data, "server_host", "127.0.0.1");
          $serverPort         = Arr::get($data, "server_port", "3001");
          $apiKey             = Arr::get($data, "wp_api_key", "");

          $envData = [
              'WP_SERVER_URL'      => "http://$serverHost:$serverPort",
              'WP_SERVER_HOST'     => $serverHost,
              'WP_SERVER_PORT'     => $serverPort,
          ];

          // Add API key if provided
          if (!empty($apiKey)) {
              $envData['WP_API_KEY'] = $apiKey;
          }

          // Allowed origins are auto-configured from APP_URL, no need to update
          // WP_ALLOWED_ORIGINS will use APP_URL by default

          return $envData;
     }

     /**
      * domain
      *
      * @return string
      */
     public function domain(): string {
          // Return full URL (e.g., "https://example.com") not just hostname
          // License verification API expects full URL format
          return rtrim(request()->root(), '/');
     }

     /**
      * sessionInit
      *
      * @return array
      */
     public function sessionInit(): array {

          $this->ensureConfigured();

          $response = Http::withHeaders($this->getHeaders())
               ->timeout(30)
               ->post($this->getBaseUrl() . '/sessions/init', [
                    'domain' => $this->domain()
               ]);
          $responseBody = json_decode($response->body());
          return [$response, $responseBody];
     }

     /**
      * sessionCreate
      *
      * @param Gateway $gateway
      *
      * @return array
      */
     public function sessionCreate(Gateway $gateway): array {

          $this->ensureConfigured();

          $response = Http::withHeaders($this->getHeaders())
               ->timeout(60)
               ->post($this->getBaseUrl() . '/sessions/create', [
                    'id'       => $gateway->name,
                    'isLegacy' => Arr::get($gateway->meta_data, 'multidevice', false),
                    'domain'   => $this->domain()
               ]);

          $responseBody = json_decode($response->body());
          return [
              $response,
              $responseBody
          ];
     }

     /**
      * sessionStatus
      *
      * @param string $name
      *
      * @return Response
      */
     public function sessionStatus(string $name): Response {

          $this->ensureConfigured();

          return Http::withHeaders($this->getHeaders())
               ->timeout(15)
               ->get($this->getBaseUrl() . '/sessions/status/' . $name);
     }

     /**
      * checkServerStatus
      *
      * @return bool
      */
     public function checkServerStatus(): bool {

          $checkWhatsappServer = true;
          try {

              $this->ensureConfigured();

              // Check if Node service is reachable
              Http::withHeaders($this->getHeaders())
                   ->timeout(5)
                   ->get($this->getBaseUrl() . '/config/status');

              Gateway::where("channel", ChannelTypeEnum::WHATSAPP)
                    ->where("type", WhatsAppGatewayTypeEnum::NODE)
                    ->select(["id", "status", "name"])
                    ->lazyById()
                    ->each(function ($gateway) use (&$checkWhatsappServer) {

                         $sessions = $this->sessionStatus($gateway->name);
                         $gateway->status = Status::INACTIVE->value;

                         if ($sessions->status() === 200) {
                              $gateway->status = Status::ACTIVE->value;
                         }

                         $gateway->save();
                    });

          } catch (\Exception $e) {
               Log::error("Whatsapp Node Failed: ".$e->getMessage());
               $checkWhatsappServer = false;
          }
          return $checkWhatsappServer;
     }

     /**
      * sessionDelete
      *
      * @param mixed $name
      *
      * @return Response
      */
     public function sessionDelete($name): Response {

          $this->ensureConfigured();

          return Http::withHeaders($this->getHeaders())
               ->timeout(15)
               ->delete($this->getBaseUrl() . '/sessions/delete/' . $name);
     }

     // =====================================================
     // MESSAGE SENDING METHODS (NEW)
     // =====================================================

     /**
      * Send text message
      *
      * @param string $sessionId
      * @param string $receiver
      * @param string $text
      * @param int $delay
      *
      * @return Response
      */
     public function sendTextMessage(string $sessionId, string $receiver, string $text, int $delay = 0): Response
     {
          $this->ensureConfigured();

          return Http::withHeaders($this->getHeaders())
               ->timeout(30)
               ->post($this->getBaseUrl() . '/messages/send', [
                    'sessionId' => $sessionId,
                    'receiver' => $receiver,
                    'message' => [
                         'text' => $text,
                    ],
                    'delay' => $delay,
               ]);
     }

     /**
      * Send image message
      *
      * @param string $sessionId
      * @param string $receiver
      * @param string $imageUrl
      * @param string $caption
      * @param int $delay
      *
      * @return Response
      */
     public function sendImageMessage(string $sessionId, string $receiver, string $imageUrl, string $caption = '', int $delay = 0): Response
     {
          $this->ensureConfigured();

          return Http::withHeaders($this->getHeaders())
               ->timeout(30)
               ->post($this->getBaseUrl() . '/messages/image', [
                    'sessionId' => $sessionId,
                    'receiver' => $receiver,
                    'imageUrl' => $imageUrl,
                    'caption' => $caption,
                    'delay' => $delay,
               ]);
     }

     /**
      * Send video message
      *
      * @param string $sessionId
      * @param string $receiver
      * @param string $videoUrl
      * @param string $caption
      * @param int $delay
      *
      * @return Response
      */
     public function sendVideoMessage(string $sessionId, string $receiver, string $videoUrl, string $caption = '', int $delay = 0): Response
     {
          $this->ensureConfigured();

          return Http::withHeaders($this->getHeaders())
               ->timeout(30)
               ->post($this->getBaseUrl() . '/messages/video', [
                    'sessionId' => $sessionId,
                    'receiver' => $receiver,
                    'videoUrl' => $videoUrl,
                    'caption' => $caption,
                    'delay' => $delay,
               ]);
     }

     /**
      * Send document message
      *
      * @param string $sessionId
      * @param string $receiver
      * @param string $documentUrl
      * @param string $filename
      * @param string $mimetype
      * @param int $delay
      *
      * @return Response
      */
     public function sendDocumentMessage(string $sessionId, string $receiver, string $documentUrl, string $filename, string $mimetype = 'application/pdf', int $delay = 0): Response
     {
          $this->ensureConfigured();

          return Http::withHeaders($this->getHeaders())
               ->timeout(30)
               ->post($this->getBaseUrl() . '/messages/document', [
                    'sessionId' => $sessionId,
                    'receiver' => $receiver,
                    'documentUrl' => $documentUrl,
                    'filename' => $filename,
                    'mimetype' => $mimetype,
                    'delay' => $delay,
               ]);
     }

     /**
      * Send button message
      *
      * @param string $sessionId
      * @param string $receiver
      * @param string $text
      * @param array $buttons
      * @param string $footer
      * @param int $delay
      *
      * @return Response
      */
     public function sendButtonMessage(string $sessionId, string $receiver, string $text, array $buttons, string $footer = '', int $delay = 0): Response
     {
          $this->ensureConfigured();

          return Http::withHeaders($this->getHeaders())
               ->timeout(30)
               ->post($this->getBaseUrl() . '/messages/button', [
                    'sessionId' => $sessionId,
                    'receiver' => $receiver,
                    'text' => $text,
                    'buttons' => $buttons,
                    'footer' => $footer,
                    'delay' => $delay,
               ]);
     }

     /**
      * Send list message
      *
      * @param string $sessionId
      * @param string $receiver
      * @param string $title
      * @param string $text
      * @param string $buttonText
      * @param array $sections
      * @param string $footer
      * @param int $delay
      *
      * @return Response
      */
     public function sendListMessage(string $sessionId, string $receiver, string $title, string $text, string $buttonText, array $sections, string $footer = '', int $delay = 0): Response
     {
          $this->ensureConfigured();

          return Http::withHeaders($this->getHeaders())
               ->timeout(30)
               ->post($this->getBaseUrl() . '/messages/list', [
                    'sessionId' => $sessionId,
                    'receiver' => $receiver,
                    'title' => $title,
                    'text' => $text,
                    'buttonText' => $buttonText,
                    'sections' => $sections,
                    'footer' => $footer,
                    'delay' => $delay,
               ]);
     }

     /**
      * Send bulk messages
      *
      * @param string $sessionId
      * @param array $messages
      *
      * @return Response
      */
     public function sendBulkMessages(string $sessionId, array $messages): Response
     {
          $this->ensureConfigured();

          return Http::withHeaders($this->getHeaders())
               ->timeout(120) // Longer timeout for bulk operations
               ->post($this->getBaseUrl() . '/messages/bulk', [
                    'sessionId' => $sessionId,
                    'messages' => $messages,
               ]);
     }

     /**
      * Check if a number is registered on WhatsApp
      *
      * @param string $sessionId
      * @param string $number
      *
      * @return Response
      */
     public function checkNumber(string $sessionId, string $number): Response
     {
          $this->ensureConfigured();

          return Http::withHeaders($this->getHeaders())
               ->timeout(15)
               ->post($this->getBaseUrl() . '/messages/check-number', [
                    'sessionId' => $sessionId,
                    'number' => $number,
               ]);
     }

     /**
      * Get WhatsApp contact info (profile name, etc.)
      *
      * @param string $sessionId
      * @param string $number
      *
      * @return Response
      */
     public function getContactInfo(string $sessionId, string $number): Response
     {
          $this->ensureConfigured();

          return Http::withHeaders($this->getHeaders())
               ->timeout(10)
               ->post($this->getBaseUrl() . '/messages/get-contact', [
                    'sessionId' => $sessionId,
                    'number' => $number,
               ]);
     }

     // =====================================================
     // LICENSE VERIFICATION METHODS
     // =====================================================

     /**
      * Get license status from Node service
      *
      * @return array
      */
     public function getLicenseStatus(): array
     {
          try {
               $response = Http::timeout(10)
                    ->get($this->getBaseUrl() . '/config/license');

               if ($response->status() === 200) {
                    $body = json_decode($response->body(), true);
                    return [
                         'success' => true,
                         'licensed' => $body['data']['licensed'] ?? false,
                         'lastVerification' => $body['data']['lastVerification'] ?? null,
                         'integrity' => $body['data']['integrity'] ?? true,
                    ];
               }

               return [
                    'success' => false,
                    'licensed' => false,
                    'error' => 'Failed to get license status',
               ];

          } catch (\Exception $e) {
               Log::error('Failed to get license status: ' . $e->getMessage());
               return [
                    'success' => false,
                    'licensed' => false,
                    'error' => $e->getMessage(),
               ];
          }
     }

     /**
      * Force license re-verification on Node service
      *
      * @return array
      */
     public function verifyLicense(): array
     {
          try {
               // First push latest config to ensure Node has current license info
               $this->pushConfigToNode();

               $response = Http::timeout(30)
                    ->post($this->getBaseUrl() . '/config/verify-license');

               if ($response->status() === 200) {
                    $body = json_decode($response->body(), true);
                    Log::info('License verified successfully on Node service');
                    return [
                         'success' => true,
                         'licensed' => true,
                         'verifiedAt' => $body['data']['verifiedAt'] ?? now()->toISOString(),
                         'message' => 'License verified successfully',
                    ];
               }

               $body = json_decode($response->body(), true);
               Log::warning('License verification failed on Node service', ['response' => $body]);
               return [
                    'success' => false,
                    'licensed' => false,
                    'message' => $body['message'] ?? 'License verification failed',
                    'support' => $body['data']['support'] ?? 'https://codecanyon.net/user/igenteam',
               ];

          } catch (\Exception $e) {
               Log::error('License verification error: ' . $e->getMessage());
               return [
                    'success' => false,
                    'licensed' => false,
                    'error' => $e->getMessage(),
               ];
          }
     }

     /**
      * Check if Node service has valid license
      * Quick check without forcing re-verification
      *
      * @return bool
      */
     public function isLicensed(): bool
     {
          $status = $this->getLicenseStatus();
          return $status['success'] && $status['licensed'];
     }

     // =====================================================
     // ENTERPRISE HEALTH & MONITORING METHODS (v2.1.0)
     // =====================================================

     /**
      * Get comprehensive health report from Node service
      * Includes system, session, queue, and API metrics
      *
      * @return array
      */
     public function getHealthReport(): array
     {
          try {
               $response = Http::timeout(10)
                    ->get($this->getBaseUrl() . '/health');

               if ($response->status() === 200) {
                    $body = json_decode($response->body(), true);
                    return [
                         'healthy' => true,
                         'status' => $body['status'] ?? 'unknown',
                         'data' => (object) $body,
                    ];
               }

               return [
                    'healthy' => false,
                    'status' => 'error',
                    'error' => 'HTTP ' . $response->status(),
               ];

          } catch (\Exception $e) {
               return [
                    'healthy' => false,
                    'status' => 'offline',
                    'error' => $e->getMessage(),
               ];
          }
     }

     /**
      * Get system health metrics only
      *
      * @return array
      */
     public function getSystemHealth(): array
     {
          try {
               $response = Http::timeout(10)
                    ->get($this->getBaseUrl() . '/health/system');

               if ($response->status() === 200) {
                    return json_decode($response->body(), true);
               }

               return ['success' => false, 'error' => 'HTTP ' . $response->status()];

          } catch (\Exception $e) {
               return ['success' => false, 'error' => $e->getMessage()];
          }
     }

     /**
      * Get session health metrics
      *
      * @return array
      */
     public function getSessionHealth(): array
     {
          try {
               $response = Http::timeout(10)
                    ->get($this->getBaseUrl() . '/health/sessions');

               if ($response->status() === 200) {
                    return json_decode($response->body(), true);
               }

               return ['success' => false, 'error' => 'HTTP ' . $response->status()];

          } catch (\Exception $e) {
               return ['success' => false, 'error' => $e->getMessage()];
          }
     }

     /**
      * Check liveness (for monitoring)
      *
      * @return bool
      */
     public function isAlive(): bool
     {
          try {
               $response = Http::timeout(5)
                    ->get($this->getBaseUrl() . '/health/live');

               return $response->status() === 200;

          } catch (\Exception $e) {
               return false;
          }
     }

     /**
      * Check readiness (for load balancer)
      *
      * @return bool
      */
     public function isReady(): bool
     {
          try {
               $response = Http::timeout(5)
                    ->get($this->getBaseUrl() . '/health/ready');

               return $response->status() === 200;

          } catch (\Exception $e) {
               return false;
          }
     }

     // =====================================================
     // ENTERPRISE QUEUE MANAGEMENT METHODS (v2.1.0)
     // =====================================================

     /**
      * Get queue status for all sessions
      *
      * @return array
      */
     public function getQueueStatus(): array
     {
          try {
               $this->ensureConfigured();

               $response = Http::withHeaders($this->getHeaders())
                    ->timeout(10)
                    ->get($this->getBaseUrl() . '/queue/status');

               if ($response->status() === 200) {
                    return json_decode($response->body(), true);
               }

               return ['success' => false, 'error' => 'HTTP ' . $response->status()];

          } catch (\Exception $e) {
               return ['success' => false, 'error' => $e->getMessage()];
          }
     }

     /**
      * Get queue status for specific session
      *
      * @param string $sessionId
      * @return array
      */
     public function getSessionQueueStatus(string $sessionId): array
     {
          try {
               $this->ensureConfigured();

               $response = Http::withHeaders($this->getHeaders())
                    ->timeout(10)
                    ->get($this->getBaseUrl() . '/queue/status/' . $sessionId);

               if ($response->status() === 200) {
                    return json_decode($response->body(), true);
               }

               return ['success' => false, 'error' => 'HTTP ' . $response->status()];

          } catch (\Exception $e) {
               return ['success' => false, 'error' => $e->getMessage()];
          }
     }

     /**
      * Configure queue settings
      *
      * @param array $config
      * @return array
      */
     public function configureQueue(array $config): array
     {
          try {
               $this->ensureConfigured();

               $response = Http::withHeaders($this->getHeaders())
                    ->timeout(10)
                    ->post($this->getBaseUrl() . '/queue/configure', $config);

               if ($response->status() === 200) {
                    return json_decode($response->body(), true);
               }

               return ['success' => false, 'error' => 'HTTP ' . $response->status()];

          } catch (\Exception $e) {
               return ['success' => false, 'error' => $e->getMessage()];
          }
     }

     /**
      * Clear queue for a session
      *
      * @param string $sessionId
      * @return array
      */
     public function clearQueue(string $sessionId): array
     {
          try {
               $this->ensureConfigured();

               $response = Http::withHeaders($this->getHeaders())
                    ->timeout(10)
                    ->delete($this->getBaseUrl() . '/queue/clear/' . $sessionId);

               if ($response->status() === 200) {
                    return json_decode($response->body(), true);
               }

               return ['success' => false, 'error' => 'HTTP ' . $response->status()];

          } catch (\Exception $e) {
               return ['success' => false, 'error' => $e->getMessage()];
          }
     }

     /**
      * Get queue statistics
      *
      * @return array
      */
     public function getQueueStats(): array
     {
          try {
               $this->ensureConfigured();

               $response = Http::withHeaders($this->getHeaders())
                    ->timeout(10)
                    ->get($this->getBaseUrl() . '/queue/stats');

               if ($response->status() === 200) {
                    return json_decode($response->body(), true);
               }

               return ['success' => false, 'error' => 'HTTP ' . $response->status()];

          } catch (\Exception $e) {
               return ['success' => false, 'error' => $e->getMessage()];
          }
     }

     // =====================================================
     // ENTERPRISE SESSION REPAIR METHODS (v2.1.0)
     // =====================================================

     /**
      * Reconnect a disconnected session using saved credentials
      * No QR rescan needed if credentials are still valid
      *
      * @param string $sessionId
      * @return array
      */
     public function reconnectSession(string $sessionId): array
     {
          try {
               $this->ensureConfigured();

               $response = Http::withHeaders($this->getHeaders())
                    ->timeout(60)
                    ->post($this->getBaseUrl() . '/sessions/reconnect/' . $sessionId);

               if ($response->status() === 200) {
                    $body = json_decode($response->body(), true);
                    Log::info("Session reconnect initiated: {$sessionId}");
                    return [
                         'success' => true,
                         'message' => $body['message'] ?? 'Session reconnection initiated',
                         'data' => $body['data'] ?? null,
                    ];
               }

               $body = json_decode($response->body(), true);
               return [
                    'success' => false,
                    'message' => $body['message'] ?? 'Session reconnection failed',
                    'error' => 'HTTP ' . $response->status(),
               ];

          } catch (\Exception $e) {
               Log::error("Session reconnect failed: {$e->getMessage()}", ['sessionId' => $sessionId]);
               return [
                    'success' => false,
                    'message' => 'Cannot connect to Node service',
                    'error' => $e->getMessage(),
               ];
          }
     }

     /**
      * Repair a session with encryption issues (Bad MAC errors)
      *
      * @param string $sessionId
      * @return array
      */
     public function repairSession(string $sessionId): array
     {
          try {
               $this->ensureConfigured();

               $response = Http::withHeaders($this->getHeaders())
                    ->timeout(60)
                    ->post($this->getBaseUrl() . '/sessions/repair/' . $sessionId);

               if ($response->status() === 200) {
                    $body = json_decode($response->body(), true);
                    Log::info("Session repair successful: {$sessionId}");
                    return [
                         'success' => true,
                         'message' => $body['message'] ?? 'Session repaired successfully',
                         'data' => $body['data'] ?? null,
                    ];
               }

               $body = json_decode($response->body(), true);
               return [
                    'success' => false,
                    'message' => $body['message'] ?? 'Session repair failed',
                    'error' => 'HTTP ' . $response->status(),
               ];

          } catch (\Exception $e) {
               Log::error("Session repair failed: {$e->getMessage()}", ['sessionId' => $sessionId]);
               return [
                    'success' => false,
                    'message' => 'Cannot connect to Node service',
                    'error' => $e->getMessage(),
               ];
          }
     }

     /**
      * Clear Node service persisted configuration
      * Useful for troubleshooting API key or license issues
      *
      * @return array
      */
     public function clearNodeConfig(): array
     {
          try {
               $response = Http::timeout(10)
                    ->post($this->getBaseUrl() . '/config/clear');

               if ($response->status() === 200) {
                    // Also clear Laravel's cache flag
                    Cache::forget('wp_node_configured');

                    Log::info('Node service configuration cleared');
                    return [
                         'success' => true,
                         'message' => 'Node configuration cleared. Will re-sync on next request.',
                    ];
               }

               return [
                    'success' => false,
                    'message' => 'Failed to clear Node configuration',
                    'error' => 'HTTP ' . $response->status(),
               ];

          } catch (\Exception $e) {
               return [
                    'success' => false,
                    'message' => 'Cannot connect to Node service',
                    'error' => $e->getMessage(),
               ];
          }
     }

     /**
      * Force re-sync configuration to Node service
      * Clears cache and pushes fresh config
      *
      * @return bool
      */
     public function forceSyncConfig(): bool
     {
          // Clear Laravel cache
          Cache::forget('wp_node_configured');

          // Push fresh config
          return $this->pushConfigToNode();
     }

     // =====================================================
     // LOGS RETRIEVAL METHODS
     // =====================================================

     /**
      * Get logs from Node service
      *
      * @param string $level Log level filter (error, warn, info, debug, all)
      * @param int $lines Number of lines to retrieve
      * @return array
      */
     public function getLogs(string $level = 'info', int $lines = 100): array
     {
          try {
               $this->ensureConfigured();

               $response = Http::withHeaders($this->getHeaders())
                    ->timeout(15)
                    ->get($this->getBaseUrl() . '/logs', [
                         'level' => $level,
                         'lines' => min($lines, 1000), // Cap at 1000 lines
                    ]);

               if ($response->status() === 200) {
                    $data = json_decode($response->body(), true);
                    return $data['logs'] ?? [];
               }

               // If endpoint doesn't exist, return empty with message
               if ($response->status() === 404) {
                    throw new \Exception('Logs endpoint not available. Real-time log viewing requires Node service v2.1.0+');
               }

               throw new \Exception('HTTP ' . $response->status());

          } catch (\Exception $e) {
               Log::warning("Failed to fetch logs from Node service: {$e->getMessage()}");
               throw $e;
          }
     }

}