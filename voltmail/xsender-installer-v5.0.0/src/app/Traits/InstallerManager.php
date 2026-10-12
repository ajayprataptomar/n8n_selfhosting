<?php

namespace App\Traits;

use App\Enums\StatusEnum;
use App\Models\Setting;
use App\Services\System\Communication\NodeService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

trait InstallerManager
{

    private function _isPurchased() :bool{

        $purchaseKey = site_settings('purchase_key');
        $userName    = site_settings('envato_username');

        $licenseData = Cache::get('software_license');

         if (empty($purchaseKey) || empty($userName)) {
             // If site_settings() returned null (DB unavailable after cache clear),
             // try reading from .env as fallback
             if ($purchaseKey === null) {
                 $purchaseKey = env('PURCHASE_KEY', '');
             }
             if ($userName === null) {
                 $userName = env('ENVATO_USERNAME', '');
             }

             if (empty($purchaseKey) || empty($userName)) {
                 return false;
             }
         }

         if (!$licenseData) {
             if (!$this->_registerDomain() || !$this->_validatePurchaseKey($purchaseKey , $userName)) {
                 return false;
             }
             // Cache license for 1 day instead of 1 hour to reduce
             // dependency on external verification server
             Cache::put('software_license', true, now()->addDay());
         }

         return true;

     }



     /**
      * Check if application is installed
      * The marker file (storage/_filecacheing) is the source of truth.
      * Cache is used for performance but always validated against the marker file.
      *
      * IMPORTANT: If the marker file exists, the app IS installed — period.
      * A temporary DB outage should NOT cause the app to redirect to the installer.
      * The installer should only show when the marker file is absent.
      *
      * @return bool
      */
     public function is_installed(): bool
     {
        try {
            $cacheKey = 'app_installation_status';

            // Fast path: check cache first
            $cachedStatus = Cache::get($cacheKey);
            if ($cachedStatus === true) {
                return true;
            }

            // Resolve marker file path
            $cacheFileConfig = config('installer.cacheFile');
            if (empty($cacheFileConfig)) {
                // Config not loaded yet (can happen right after config:clear)
                // Fall back to the known value
                $cacheFileConfig = 'X2ZpbGVjYWNoZWluZw==';
            }
            $logFile = storage_path(base64_decode($cacheFileConfig));

            // Marker file is the SOLE source of truth for installation status
            // If it exists, the app is installed regardless of DB status
            if (!file_exists($logFile)) {
                Cache::forget($cacheKey);
                return false;
            }

            // Check .env has a database configured (empty = fresh install)
            $dbName = env('DB_DATABASE') ?: config('database.connections.mysql.database');
            if (empty($dbName)) {
                return false;
            }

            // Marker file exists + DB configured = installed
            // Cache this status (try-catch because cache driver might be unavailable)
            try {
                Cache::put($cacheKey, true, now()->addDays(7));
            } catch (\Exception $e) {
                // Cache write failed, but the app IS installed
            }

            return true;
        } catch (\Exception $ex) {
            // Even if an exception occurs, check the marker file as a last resort
            // This prevents false "not installed" during temporary issues
            try {
                $logFile = storage_path(base64_decode('X2ZpbGVjYWNoZWluZw=='));
                return file_exists($logFile);
            } catch (\Exception $e) {
                return false;
            }
        }
    }


    public function checkRequirements(array $requirements) :array{


        $results = [];

        foreach ($requirements as $type => $requirement) {
            switch ($type) {

                case 'php':
                    foreach ($requirements[$type] as $requirement) {
                        $results['requirements'][$type][$requirement] = true;

                        if (! extension_loaded($requirement)) {
                            $results['requirements'][$type][$requirement] = false;

                            $results['errors'] = true;
                        }
                    }
                    break;
                case 'apache':
                    foreach ($requirements[$type] as $requirement) {
                        if (function_exists('apache_get_modules')) {
                            $results['requirements'][$type][$requirement] = true;

                            if (! in_array($requirement, apache_get_modules())) {
                                $results['requirements'][$type][$requirement] = false;

                                $results['errors'] = true;
                            }
                        }
                    }
                    break;
            }
        }

        return $results;

    }




    /**
     * Get current Php version information.
     *
     * @return array
     */
    private static function getPhpVersionInfo()
    {
        $currentVersionFull = PHP_VERSION;
        preg_match("#^\d+(\.\d+)*#", $currentVersionFull, $filtered);
        $currentVersion = $filtered[0];

        return [
            'full'    => $currentVersionFull,
            'version' => $currentVersion,
        ];
    }




    /**
     * Check PHP version requirement.
     *
     * @return array
     */
    public function checkPHPversion(string $minPhpVersion) :array
    {
        $minVersionPhp = $minPhpVersion;
        $currentPhpVersion = $this->getPhpVersionInfo();
        $supported = false;

        if (version_compare($currentPhpVersion['version'], $minVersionPhp) >= 0) {
            $supported = true;
        }

        $phpStatus = [
            'full'       => $currentPhpVersion['full'],
            'current'    => $currentPhpVersion['version'],
            'minimum'    => $minVersionPhp,
            'supported'  => $supported,
        ];

        return $phpStatus;
    }



    public function permissionsCheck(array $folders) :array{

        foreach ($folders as $folder => $permission) {
            if (! ($this->getPermission($folder) >= $permission)) {
                $permissions [] =  $this->addFileAndSetErrors($folder, $permission, false);
            } else {
                $permissions [] =  $this->addFile($folder, $permission, true);
            }
        }

        return $permissions;


    }


    /**
     * Get a folder permission.
     *
     * @param $folder
     * @return string
     */
    private function getPermission($folder)
    {
        return substr(sprintf('%o', fileperms(base_path($folder))), -4);
    }


    /**
     * Add the file and set the errors.
     *
     * @param $folder
     * @param $permission
     * @param $isSet
     */
    private function addFileAndSetErrors($folder, $permission, $isSet) :array
    {
        return $this->addFile($folder, $permission, $isSet);
    }


    /**
     * Add the file to the list of results.
     *
     * @param $folder
     * @param $permission
     * @param $isSet
     */
    private function addFile($folder, $permission, $isSet) :array
    {
        return [
            'folder' => $folder,
            'permission' => $permission,
            'isSet' => $isSet,
        ];

    }



    private function _envatoVerification(Request $request) : mixed {

        // Must register domain first before validating purchase key
        // The verification server requires domain registration before accepting purchase validation
        if (!$this->_registerDomain()) {
            Log::warning('Domain registration failed during envato verification');
            // Continue anyway - domain might already be registered
        }

        return $this->_validatePurchaseKey($request->input(base64_decode('cHVyY2hhc2VfY29kZQ==')) , $request->input(base64_decode('dXNlcm5hbWU=')));
    }



    private function _registerDomain()
    {
        try {
            $params = [
                'domain'        => url('/'),
                'software_id'   => config('installer.software_id') ?? 'BX32DOTW4Q797ZF3',
                'version'       => config('installer.version') ?? '4.1'
            ];

            $url = 'https://verifylicense.online/api/licence-verification/register-domain';

            Log::info('Register domain request', ['url' => $url, 'params' => $params]);

            $response = $this->makeVerificationRequest($url, $params);

            if (!$response) {
                return false;
            }

            $data = $response->json();
            Log::info('Register domain response', ['data' => $data]);

            if (!isset($data['success'], $data['code'], $data['message'])) {
                Log::warning('Invalid register domain response structure', ['data' => $data]);
                return false;
            }

            return $data['success'];

        } catch (\Exception $e) {
            Log::error('Register domain exception: ' . $e->getMessage());
            return false;
        }
    }

    private function _validatePurchaseKey(string $key, string $username): mixed
    {
        $params = [
            'domain'            => url('/'),
            'software_id'       => config('installer.software_id') ?? 'BX32DOTW4Q797ZF3',
            'version'           => config('installer.version') ?? '4.1',
            'purchase_key'      => $key,
            'envato_username'   => $username,
        ];

        try {
            $url = 'https://verifylicense.online/api/licence-verification/verify-purchase';

            Log::info('Verify purchase request', [
                'url' => $url,
                'domain' => $params['domain'],
                'software_id' => $params['software_id'],
                'username' => $username,
                'key_length' => strlen($key),
            ]);

            $response = $this->makeVerificationRequest($url, $params);

            if (!$response) {
                Log::error('Verification request failed - no response');
                return false;
            }

            $data = $response->json();
            Log::info('Verify purchase response', ['data' => $data]);

            if (!isset($data['success'], $data['code'], $data['message'])) {
                Log::warning('Invalid verification response structure', ['data' => $data]);
                return false;
            }

            if (!$data['success']) {
                Log::warning('Verification failed', ['message' => $data['message'] ?? 'Unknown']);
            }

            return $data['success'];

        } catch (\Exception $e) {
            Log::error('Verify purchase exception: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Make HTTP request to verification server with proper error handling
     * Handles SSL issues, timeouts, and provides detailed logging
     * Compatible with Laravel 8.x and newer versions
     *
     * @param string $url
     * @param array $params
     * @return \Illuminate\Http\Client\Response|null
     */
    private function makeVerificationRequest(string $url, array $params)
    {
        $maxRetries = 3;
        $lastError = null;

        // Use a browser-like User-Agent (some APIs block non-browser agents)
        $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                Log::debug("Verification request attempt {$attempt}/{$maxRetries}", [
                    'url' => $url,
                ]);

                // Build the HTTP client with proper settings
                $response = Http::withOptions([
                        'timeout' => 30,
                        'connect_timeout' => 15,
                        'verify' => $attempt === 1, // Disable SSL on retry attempts
                    ])
                    ->withHeaders([
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                        'User-Agent' => $userAgent,
                        'Cache-Control' => 'no-cache',
                    ])
                    ->post($url, $params);

                if ($response->successful()) {
                    Log::debug("Verification request successful on attempt {$attempt}");
                    return $response;
                }

                Log::warning("Verification request failed", [
                    'attempt' => $attempt,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                // For 4xx errors (client errors), return the response
                // But continue retrying for 401/403 with SSL disabled
                if ($response->status() >= 400 && $response->status() < 500) {
                    if (($response->status() === 401 || $response->status() === 403) && $attempt < $maxRetries) {
                        Log::info("Got {$response->status()}, retrying with different SSL settings");
                        sleep(1);
                        continue;
                    }
                    return $response;
                }

            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                $lastError = $e->getMessage();
                Log::warning("Connection exception on attempt {$attempt}: " . $lastError);

            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                Log::error("Verification request exception on attempt {$attempt}: " . $lastError);
            }

            // Wait before retry
            if ($attempt < $maxRetries) {
                sleep($attempt);
            }
        }

        Log::error("All verification request attempts failed", ['last_error' => $lastError]);
        return null;
    }


    /**
     * v4.9 — canonical name. `_chekcDbConnection` (typo'd) is kept as an
     * alias below so any downstream code still calling it keeps working
     * until the next major release. New callers should use this method.
     */
    private function checkDbConnection(Request $request): bool
    {
        return $this->_chekcDbConnectionImpl($request);
    }

    /**
     * @deprecated v4.9 — misspelled alias. Use checkDbConnection().
     */
    private function _chekcDbConnection(Request $request): bool
    {
        return $this->_chekcDbConnectionImpl($request);
    }

    /**
     * Actual implementation — private so both aliases share one body.
     */
    private function _chekcDbConnectionImpl(Request $request): bool
    {
        $host = trim($request->input('db_host', 'localhost'));
        $username = trim($request->input('db_username', ''));
        $password = $request->input('db_password', ''); // Don't trim password - spaces may be intentional
        $database = trim($request->input('db_database', ''));
        $port = (int) $request->input('db_port', 3306);

        Log::info('Attempting database connection', [
            'host' => $host,
            'database' => $database,
            'username' => $username,
            'port' => $port,
            'password_length' => strlen($password)
        ]);

        // Try multiple connection methods for compatibility

        // Method 1: Try with mysqli (most common)
        try {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

            $connection = @new \mysqli($host, $username, $password, $database, $port);

            if ($connection->connect_error) {
                Log::warning('mysqli connection failed', [
                    'error' => $connection->connect_error,
                    'errno' => $connection->connect_errno
                ]);
            } else {
                $result = $connection->query("SELECT 1");
                $connection->close();
                if ($result) {
                    Log::info('Database connection successful via mysqli');
                    return true;
                }
            }
        } catch (\mysqli_sql_exception $e) {
            Log::warning('mysqli exception', ['error' => $e->getMessage(), 'code' => $e->getCode()]);
        } catch (\Exception $e) {
            Log::warning('mysqli error', ['error' => $e->getMessage()]);
        }

        // Method 2: Try with PDO (alternative method)
        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$database}";
            $pdo = new \PDO($dsn, $username, $password, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 10
            ]);
            $pdo->query("SELECT 1");
            $pdo = null;
            Log::info('Database connection successful via PDO');
            return true;
        } catch (\PDOException $e) {
            Log::warning('PDO connection failed', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
        }

        // Method 3: Try localhost alternatives (common on shared hosting)
        $alternativeHosts = [];
        if ($host === 'localhost') {
            $alternativeHosts = ['127.0.0.1', 'localhost:' . $port];
        } elseif ($host === '127.0.0.1') {
            $alternativeHosts = ['localhost'];
        }

        foreach ($alternativeHosts as $altHost) {
            try {
                $dsn = "mysql:host={$altHost};dbname={$database}";
                $pdo = new \PDO($dsn, $username, $password, [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_TIMEOUT => 10
                ]);
                $pdo->query("SELECT 1");
                $pdo = null;
                Log::info("Database connection successful via alternative host: {$altHost}");
                return true;
            } catch (\PDOException $e) {
                Log::debug("Alternative host {$altHost} failed: " . $e->getMessage());
            }
        }

        Log::error('All database connection methods failed', [
            'host' => $host,
            'database' => $database,
            'username' => $username,
            'port' => $port
        ]);

        return false;
    }



    private function _isDbEmpty(): bool
    {
        try {
            $host = env('DB_HOST', 'localhost');
            $username = env('DB_USERNAME', '');
            $password = env('DB_PASSWORD', '');
            $database = env('DB_DATABASE', '');
            $port = (int) env('DB_PORT', 3306);

            $conn = new \mysqli($host, $username, $password, $database, $port);

            if ($conn->connect_error) {
                Log::warning('_isDbEmpty: Connection failed', ['error' => $conn->connect_error]);
                return false;
            }

            $result = $conn->query("SHOW TABLES");
            $tableCount = $result ? $result->num_rows : 0;
            $conn->close();

            Log::debug('_isDbEmpty check', ['table_count' => $tableCount]);

            return $tableCount === 0;

        } catch (\Exception $e) {
            Log::error('_isDbEmpty exception', ['error' => $e->getMessage()]);
            return false;
        }
    }



    private  function _envConfig(Request $request) :mixed {

        try {
            $key = base64_encode(random_bytes(32));
            $appName = config('installer.app_name', 'xSender');
            $demoConfig = config('installer.demo_config', []);
            $appUrl = URL::to('/');
            $softwareId = config('installer.software_id', 'BX32DOTW4Q797ZF3');
            $version = config('installer.version', '4.1');

            // Escape database password for special characters
            $dbPassword = $request->input("db_password", "");
            if (preg_match('/[#$&()\\s]/', $dbPassword)) {
                $dbPassword = '"' . $dbPassword . '"';
            }

            $output = <<<ENV
# ============================================
# xSender Environment Configuration
# Generated at: {$this->_getCurrentTimestamp()}
# ============================================

# Application Settings
APP_NAME={$appName}
APP_ENV=production
APP_KEY=base64:{$key}
APP_DEBUG=false
APP_INSTALL=true
APP_LOG_LEVEL=error
APP_MODE=live
APP_URL={$appUrl}

# Database Configuration
DB_CONNECTION=mysql
DB_HOST={$request->input("db_host", "localhost")}
DB_PORT={$request->input("db_port", "3306")}
DB_DATABASE={$request->input("db_database")}
DB_USERNAME={$request->input("db_username")}
DB_PASSWORD={$dbPassword}

# Cache & Session (file-based for maximum compatibility)
BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
SESSION_DRIVER=file
SESSION_LIFETIME=120

# Queue Configuration (database for reliability)
QUEUE_CONNECTION=database

# Redis Configuration (optional - for advanced setups)
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Demo Credentials (used during seeding)
APP_ADMIN_USERNAME={$this->_getConfigValue($demoConfig, "admin.username", "admin")}
APP_ADMIN_PASSWORD={$this->_getConfigValue($demoConfig, "admin.password", "admin")}
APP_USER_EMAIL={$this->_getConfigValue($demoConfig, "user.email", "demo@test.com")}
APP_USER_PASSWORD={$this->_getConfigValue($demoConfig, "user.password", "12345678")}

# Pusher Configuration (optional)
PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_APP_CLUSTER=mt1

# License Information
PURCHASE_KEY={$this->_getSessionValue("cHVyY2hhc2VfY29kZQ==")}
ENVATO_USERNAME="{$this->_getSessionValue("dXNlcm5hbWU=")}"

# WhatsApp Node Service Configuration
WP_SERVER_URL=http://127.0.0.1:3001
WP_SERVER_HOST=127.0.0.1
WP_SERVER_PORT=3001
WP_API_KEY={$this->_generateApiKey()}
WP_ALLOWED_ORIGINS="\${APP_URL}"

# Software Information
SOFTWARE_ID={$softwareId}
VERSION={$version}

# WhatsApp Connection Settings
MAX_RETRIES=5
RECONNECT_INTERVAL=5000
NODE_TLS_REJECT_UNAUTHORIZED=1

# Mail Configuration (configure after installation)
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="{$appName}"

ENV;

            // Write .env file
            $envPath = base_path('.env');
            $result = file_put_contents($envPath, $output);

            if ($result === false) {
                return false;
            }

            // Set proper file permissions — 0666 so downstream installer steps
            // and post-install `update_env()` calls can rewrite the file when
            // PHP-FPM runs under a different user than the file owner (common
            // on shared cPanel, PHP-FPM per-user, and reverse-proxy setups).
            // 0644 was too restrictive: the installer WRITES to .env again in
            // later steps (PURCHASE_KEY, ENVATO_USERNAME, GATEWAY tokens),
            // which silently failed under some host configurations. Matches
            // the value declared in config('installer.permissions.env').
            @chmod($envPath, 0666);

            // Sync API key to WhatsApp Node service .env
            // This ensures both services use the same key from the start
            $this->_syncApiKeyToNodeEnv($envPath);

            return file_exists($envPath);

        } catch (\Throwable $th) {
            return false;
        }
    }

    /**
     * Get current timestamp for .env header
     */
    private function _getCurrentTimestamp(): string
    {
        return Carbon::now()->format('Y-m-d H:i:s');
    }

    /**
     * Safely get config value with default
     */
    private function _getConfigValue(array $config, string $key, string $default): string
    {
        return Arr::get($config, $key, $default) ?: $default;
    }

    /**
     * Safely get session value (base64 encoded key)
     */
    private function _getSessionValue(string $encodedKey): string
    {
        return session()->get(base64_decode($encodedKey), '') ?: '';
    }

    /**
     * Generate a secure API key for WhatsApp Node service
     * Uses the same method as NodeService::generateApiKey()
     *
     * @return string
     */
    private function _generateApiKey(): string
    {
        return \Illuminate\Support\Str::random(32);
    }

    /**
     * Sync the WP_API_KEY from Laravel's .env to the Node service .env
     * Tries common Node service directory locations
     *
     * @param string $laravelEnvPath
     * @return void
     */
    private function _syncApiKeyToNodeEnv(string $laravelEnvPath): void
    {
        try {
            // Read the API key we just wrote to Laravel's .env
            $laravelEnv = file_get_contents($laravelEnvPath);
            if (!preg_match('/^WP_API_KEY=(.+)$/m', $laravelEnv, $matches)) {
                return;
            }
            $apiKey = trim($matches[1]);
            if (empty($apiKey)) {
                return;
            }

            // Try common Node service .env locations
            $possiblePaths = [
                base_path('../xsender-whatsapp-service/.env'),
                base_path('../../xsender-whatsapp-service/.env'),
            ];

            foreach ($possiblePaths as $nodeEnvPath) {
                $nodeEnvPath = realpath(dirname($nodeEnvPath)) . DIRECTORY_SEPARATOR . basename($nodeEnvPath);
                if (!file_exists($nodeEnvPath)) {
                    // Check if the directory exists (file might not exist yet)
                    $dir = dirname($nodeEnvPath);
                    if (!is_dir($dir)) {
                        continue;
                    }
                }

                if (file_exists($nodeEnvPath)) {
                    $content = file_get_contents($nodeEnvPath);
                    // Update existing API_KEY
                    if (preg_match('/^API_KEY=.*$/m', $content)) {
                        $content = preg_replace('/^API_KEY=.*$/m', 'API_KEY=' . $apiKey, $content);
                    } else {
                        $content .= PHP_EOL . 'API_KEY=' . $apiKey;
                    }
                    file_put_contents($nodeEnvPath, $content);
                }

                break; // Only sync to the first found location
            }
        } catch (\Throwable $e) {
            // Silently fail - Node will read from Laravel's .env as fallback
        }
    }

    private function _dbMigrate(mixed $forceImport) :void{
        
        if($forceImport == StatusEnum::TRUE->status()){
            Artisan::call('db:wipe', ['--force' => true]);
        }
        ini_set('max_execution_time', 0);
        Artisan::call('migrate:fresh', ['--force' => true]);
    }
    private function _dbSeed() :void{
        ini_set('max_execution_time', 0);
        Artisan::call('db:seed', ['--force' => true]);
    }


    private function _systemInstalled(?string $purchaseKey = null, ?string $envatoUsername = null): void
    {
        $this->_updateSetting($purchaseKey, $envatoUsername);

        $message = "INSTALLED_AT:" . Carbon::now();
        $logFile = storage_path(base64_decode(config('installer.cacheFile')));

        if (file_exists($logFile)) {
            unlink($logFile);
        }
        file_put_contents($logFile, $message);

        // Clear reinstall flag if it was set
        session()->forget('allow_reinstall');

        // Clear all Laravel caches
        optimize_clear();

        // Clear all application-specific caches
        if (function_exists('clear_app_caches')) {
            clear_app_caches();
        }

        // Cache installation status for 7 days
        Cache::put('app_installation_status', true, now()->addDays(7));

        // Mark domain as verified (installation verified it)
        Cache::put('domain_verification_status', 'verified', now()->addDays(7));

        // Mark software license as valid
        Cache::put('software_license', true, now()->addDay());

        // Initialize WhatsApp Node service with fresh config
        $this->_initializeNodeService();

        Log::info('System installation completed', [
            'installed_at' => Carbon::now()->toDateTimeString(),
            'version' => config('installer.version')
        ]);
    }

    /**
     * Initialize WhatsApp Node service after installation
     * Pushes configuration including API key, domain, and license info
     */
    private function _initializeNodeService(): void
    {
        try {
            $nodeService = app(NodeService::class);

            // Push configuration to Node service
            $result = $nodeService->pushConfigToNode();

            if ($result) {
                Log::info('WhatsApp Node service configured successfully after installation');
            } else {
                Log::warning('WhatsApp Node service configuration failed - will retry on first use');
            }
        } catch (Exception $e) {
            // Don't fail installation if Node service is not available
            Log::warning('Could not initialize WhatsApp Node service: ' . $e->getMessage());
        }
    }


    private function _updateSetting(?string $purchaseKey = null, ?string $envatoUsername = null): void {

        $data = [
            ['key' => "app_version", 'value' => Arr::get(config("installer.core"), 'appVersion', null)],
            ['key' => "system_installed_at", 'value' => Carbon::now()->toDateTimeString()],
            ['key' => "is_domain_verified", 'value' => StatusEnum::TRUE->status()],
            ['key' => "next_verification", 'value' => Carbon::now()->addDays(3)->toDateTimeString()],
        ];

        // ALWAYS store purchase_key and envato_username in database
        // This is critical for WhatsApp Node service license verification
        // Use null coalescing to handle null/empty cases
        $data[] = ['key' => "purchase_key", 'value' => $purchaseKey ?? ''];
        $data[] = ['key' => "envato_username", 'value' => $envatoUsername ?? ''];

        foreach ($data as $item) {
            try {
                // Use updateOrInsert with explicit values
                Setting::updateOrInsert(
                    ['key' => $item['key']],
                    [
                        'key' => $item['key'],
                        'value' => $item['value'],
                        'uid' => $item['uid'] ?? str_unique(),
                        'updated_at' => now(),
                    ]
                );
            } catch (\Exception $e) {
                // Log but don't fail installation
                Log::error('Failed to insert setting: ' . $item['key'] . ' - ' . $e->getMessage());
            }
        }

        // Log what was saved for debugging
        Log::info('Installation settings saved', [
            'purchase_key_length' => strlen($purchaseKey ?? ''),
            'envato_username_length' => strlen($envatoUsername ?? ''),
        ]);
    }


}
