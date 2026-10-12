<?php

namespace App\Http\Controllers;

use App\Traits\InstallerManager;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\View\View;
use ZipArchive;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class SystemUpdateController extends Controller
{
    use InstallerManager;

    /**
     * Minimum recommended upload size for update packages (in bytes)
     */
    private const RECOMMENDED_UPLOAD_MIN = 128 * 1024 * 1024; // 128MB

    /**
     * Allowed ZIP filename patterns for manual upload detection
     */
    private const MANUAL_UPLOAD_PATTERNS = [
        'xsender_update_*.zip',
        'update*.zip',
        'xsender*.zip',
    ];

    public function __construct()
    {
    }

    public function init(): View
    {
        Session::put("menu_active", true);
        return view('admin.setting.system_update', [
            "title" => translate("Update System")
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    //  Server Environment Pre-Check
    // ─────────────────────────────────────────────────────────────

    /**
     * Return server environment info for the update page
     */
    public function serverCheck(): array
    {
        $uploadMax = $this->parseIniBytes(ini_get('upload_max_filesize'));
        $postMax = $this->parseIniBytes(ini_get('post_max_size'));
        $effectiveMax = min($uploadMax, $postMax);

        return [
            'success' => true,
            'data' => [
                'upload_max_filesize'   => ini_get('upload_max_filesize'),
                'upload_max_bytes'      => $uploadMax,
                'post_max_size'         => ini_get('post_max_size'),
                'post_max_bytes'        => $postMax,
                'effective_max_bytes'   => $effectiveMax,
                'effective_max_human'   => $this->formatBytes($effectiveMax),
                'max_execution_time'    => (int) ini_get('max_execution_time'),
                'memory_limit'          => ini_get('memory_limit'),
                'php_version'           => PHP_VERSION,
                'disk_free_space'       => @disk_free_space(base_path()) ?: 0,
                'disk_free_human'       => $this->formatBytes(@disk_free_space(base_path()) ?: 0),
                'zip_available'         => class_exists('ZipArchive'),
                'writable'              => is_writable(base_path()) && is_writable(storage_path()),
                'recommended_min_bytes' => self::RECOMMENDED_UPLOAD_MIN,
                'upload_ok'             => $effectiveMax >= self::RECOMMENDED_UPLOAD_MIN,
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────
    //  Manual Upload Detection (FTP / File Manager Fallback)
    // ─────────────────────────────────────────────────────────────

    /**
     * Scan project root for manually uploaded ZIP files
     */
    public function detectManualUpload(): array
    {
        $projectRoot = dirname(base_path());
        $appRoot = base_path();
        $found = [];

        $searchDirs = [$projectRoot, $appRoot];

        foreach ($searchDirs as $dir) {
            if (!is_dir($dir)) continue;

            foreach (self::MANUAL_UPLOAD_PATTERNS as $pattern) {
                $matches = glob($dir . '/' . $pattern);
                if ($matches) {
                    foreach ($matches as $filePath) {
                        $filename = basename($filePath);
                        // Prevent duplicates
                        if (isset($found[$filename])) continue;

                        $found[$filename] = [
                            'filename'  => $filename,
                            'path'      => $filePath,
                            'size'      => filesize($filePath),
                            'size_human'=> $this->formatBytes(filesize($filePath)),
                            'modified'  => date('Y-m-d H:i:s', filemtime($filePath)),
                            'location'  => ($dir === $projectRoot) ? 'project_root' : 'app_root',
                        ];
                    }
                }
            }
        }

        return [
            'success' => true,
            'data' => array_values($found),
            'message' => count($found) > 0
                ? translate('Found') . ' ' . count($found) . ' ' . translate('update package(s)')
                : translate('No update packages found. Upload a ZIP file to your project root directory.'),
        ];
    }

    /**
     * Install from a manually uploaded ZIP file at project root
     */
    public function installManualUpload(Request $request): array
    {
        $filename = $request->input('filename');

        if (empty($filename)) {
            return ['success' => false, 'message' => translate('No filename specified')];
        }

        // Security: sanitize filename — no path traversal
        $filename = basename($filename);
        if (!preg_match('/\.zip$/i', $filename)) {
            return ['success' => false, 'message' => translate('Invalid file type')];
        }

        // Find the file in allowed locations
        $projectRoot = dirname(base_path());
        $appRoot = base_path();
        $zipPath = null;

        foreach ([$projectRoot, $appRoot] as $dir) {
            $candidate = $dir . '/' . $filename;
            if (file_exists($candidate) && is_file($candidate)) {
                $zipPath = $candidate;
                break;
            }
        }

        if (!$zipPath) {
            return ['success' => false, 'message' => translate('Update file not found. Please ensure the ZIP file is in the project root directory.')];
        }

        \Log::info('Installing from manual upload', ['file' => $zipPath, 'size' => filesize($zipPath)]);

        $result = $this->processUpdatePackage($zipPath);

        // Delete the ZIP after successful install
        if ($result['success'] && file_exists($zipPath)) {
            @unlink($zipPath);
            \Log::info('Manual upload ZIP deleted after successful install', ['file' => $zipPath]);
        }

        return $result;
    }

    // ─────────────────────────────────────────────────────────────
    //  Remote Update Check & Install
    // ─────────────────────────────────────────────────────────────

    /**
     * Check for available updates via remote API
     */
    public function checkUpdate(): array
    {
        $params = [
            'domain'            => url('/'),
            'software_id'       => config('installer.software_id'),
            'version'           => config('installer.version'),
            'purchase_key'      => site_settings('purchase_key') ?: env('PURCHASE_KEY'),
            'envato_username'   => site_settings('envato_username') ?: env('ENVATO_USERNAME')
        ];

        try {
            $url = 'https://verifylicense.online/api/licence-verification/get-update-versions';
            $response = Http::withOptions([
                'timeout' => 30,
                'connect_timeout' => 15,
            ])->post($url, $params);
            $data = $response->json();

            if (!isset($data['success'], $data['code'], $data['message'])) {
                return ['success' => false, 'message' => 'Invalid API response structure'];
            }

            if ($data['success'] === true) {
                return [
                    'success' => true,
                    'message' => !empty($data['data']) ? 'Update available' : 'No updates available',
                    'data' => $data['data'] ?? [],
                ];
            }

            $errorMessage = $data['message'] ?? 'Unknown error';
            if (isset($data['data']['errors'])) {
                $errorMessage .= ': ' . json_encode($data['data']['errors']);
            }

            return ['success' => false, 'message' => $errorMessage];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Failed to connect to API: ' . $e->getMessage()];
        }
    }

    /**
     * Download and install update from remote API
     */
    public function installUpdate(Request $request)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_input_time', '300');
        ini_set('max_execution_time', '300');

        $params = [
            'domain'            => url('/'),
            'software_id'       => config('installer.software_id'),
            'version'           => $request->input('version'),
            'purchase_key'      => site_settings('purchase_key') ?: env('PURCHASE_KEY'),
            'envato_username'   => site_settings('envato_username') ?: env('ENVATO_USERNAME')
        ];

        try {
            $url = 'https://verifylicense.online/api/licence-verification/download-version';
            $response = Http::withOptions([
                'timeout' => 180,
                'connect_timeout' => 30,
            ])->post($url, $params);

            $contentType = $response->header('Content-Type');
            $isJsonResponse = str_contains($contentType ?? '', 'application/json');

            if ($isJsonResponse) {
                $data = $response->json();
                $errorMessage = $data['message'] ?? 'Unknown error';
                if (isset($data['data']['errors'])) {
                    $errorMessage .= ': ' . json_encode($data['data']['errors']);
                }
                if (isset($data['data']['error'])) {
                    $errorMessage = $data['data']['error'];
                }
                \Log::warning('Update API returned error', ['response' => $data]);
                return ['success' => false, 'message' => $errorMessage];
            }

            if ($response->successful() && !$isJsonResponse) {
                $basePath = base_path('/storage/app/public/temp_update/');
                if (!file_exists($basePath)) {
                    mkdir($basePath, 0777, true);
                }

                $filename = 'default_update.zip';
                if ($response->hasHeader('Content-Disposition')) {
                    $disposition = $response->header('Content-Disposition');
                    if (preg_match('/filename="(.+?)"/', $disposition, $matches)) {
                        $filename = $matches[1];
                    }
                }

                $filePath = $basePath . '/' . $filename;
                file_put_contents($filePath, $response->body());
                \Log::info('Update file downloaded', ['path' => $filePath, 'size' => filesize($filePath)]);

                return $this->processUpdatePackage($filePath);
            }

            $errorMessage = translate('Failed to download update from server. Status: ') . $response->status();
            \Log::error('Update download failed', ['status' => $response->status()]);
            return ['success' => false, 'message' => $errorMessage];

        } catch (\Exception $e) {
            \Log::error('Update exception', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  Form Upload (Manual ZIP via browser)
    // ─────────────────────────────────────────────────────────────

    /**
     * Handle ZIP file upload via browser form
     */
    public function update(Request $request): RedirectResponse
    {
        ini_set('memory_limit', '-1');
        ini_set('max_input_time', '300');
        ini_set('max_execution_time', '300');

        $request->validate([
            'updateFile' => ['required', 'mimes:zip'],
        ], [
            'updateFile.required' => translate('File field is required')
        ]);

        $response = [];

        try {
            if ($request->hasFile('updateFile')) {
                $zipFile = $request->file('updateFile');
                $basePath = base_path('/storage/app/public/temp_update/');

                if (!file_exists($basePath)) {
                    mkdir($basePath, 0777, true);
                }

                $originalFilename = $zipFile->getClientOriginalName();
                $zipFile->move($basePath, $originalFilename);
                \Log::info('Update file uploaded', ['filename' => $originalFilename]);

                $result = $this->processUpdatePackage($basePath . $originalFilename);

                if ($result['success']) {
                    $response[] = response_status($result['message']);
                } else {
                    $response[] = response_status($result['message'], 'error');
                }
            } else {
                $response[] = response_status(translate('No update file was uploaded.'), 'error');
            }
        } catch (\Exception $ex) {
            $response = [];
            $response[] = response_status(translate('Update failed: ') . strip_tags($ex->getMessage()), 'error');
            \Log::error('Manual update exception', ['error' => $ex->getMessage(), 'trace' => $ex->getTraceAsString()]);
        }

        return redirect()->back()->withNotify($response);
    }

    // ─────────────────────────────────────────────────────────────
    //  Core Update Processing (shared by all update methods)
    // ─────────────────────────────────────────────────────────────

    /**
     * Process an update package from a ZIP file path
     *
     * Shared logic used by: form upload, remote install, and manual FTP upload.
     * Handles: extraction, validation, file copy, vendor replacement, migrations, cache clear.
     *
     * @param string $zipPath Absolute path to the ZIP file
     * @return array{success: bool, message: string, details?: array}
     */
    private function processUpdatePackage(string $zipPath): array
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '300');

        $basePath = base_path('/storage/app/public/temp_update/');
        $details = ['migrations' => null, 'seeders' => null, 'vendor' => false, 'cache_cleared' => false];

        try {
            // Create temp directory
            if (!file_exists($basePath)) {
                mkdir($basePath, 0777, true);
            }

            // If the ZIP is NOT already in the temp directory, copy it there
            $zipInTemp = str_starts_with(realpath($zipPath) ?: $zipPath, realpath($basePath) ?: $basePath);
            if (!$zipInTemp) {
                $tempZip = $basePath . basename($zipPath);
                copy($zipPath, $tempZip);
                $zipPath = $tempZip;
            }

            // ── Step 1: Extract ZIP ──
            $zip = new ZipArchive;
            $res = $zip->open($zipPath);

            if ($res !== true) {
                $this->deleteDirectory($basePath);
                \Log::error('Failed to open update zip file', ['path' => $zipPath]);
                return ['success' => false, 'message' => translate('Error! Could not open update file. The file may be corrupted.')];
            }

            $zip->extractTo($basePath);
            $zip->close();

            // ── Step 2: Validate config.json ──
            $configFilePath = $basePath . 'config.json';

            if (!file_exists($configFilePath)) {
                $this->deleteDirectory($basePath);
                \Log::error('Config.json not found in update package');
                return ['success' => false, 'message' => translate('Error! Invalid update package - no config.json file found.')];
            }

            $configJson = json_decode(file_get_contents($configFilePath), true);

            if (empty($configJson) || empty($configJson['version'])) {
                $this->deleteDirectory($basePath);
                \Log::error('Invalid config.json in update package', ['config' => $configJson]);
                return ['success' => false, 'message' => translate('Error! Invalid configuration file in update package.')];
            }

            // ── Step 3: Version check (using version_compare for proper semantic versioning) ──
            $newVersion = trim($configJson['version']);
            $currentVersion = trim((string) (@site_settings("app_version") ?? '1.1'));

            \Log::info('Update version check', ['current' => $currentVersion, 'new' => $newVersion]);

            // Sanity check: refuse packages with implausible version numbers (test/garbage
            // values like "999.0" would otherwise pin app_version above any future legitimate
            // release and brick the update flow forever).
            if (!preg_match('/^[0-9]+(\.[0-9]+){1,2}$/', $newVersion)) {
                $this->deleteDirectory($basePath);
                return ['success' => false, 'message' => translate("Invalid version format in update package: '{$newVersion}'.")];
            }
            $majorPart = (int) explode('.', $newVersion)[0];
            if ($majorPart < 1 || $majorPart > 99) {
                $this->deleteDirectory($basePath);
                return ['success' => false, 'message' => translate("Refusing update package with major version {$majorPart}. Expected 1–99. Likely a test or corrupt build.")];
            }

            if (version_compare($newVersion, $currentVersion, '<=')) {
                $this->deleteDirectory($basePath);
                return ['success' => true, 'message' => translate('Your system is already running the latest version or newer.')];
            }

            // ── Step 4: Copy new files (with protected file exclusion) ──
            $src = $basePath;
            $dst = dirname(base_path());

            if (!$this->copyDirectory($src, $dst, $this->getProtectedFiles())) {
                \Log::error('Failed to copy update files');
                $this->deleteDirectory($basePath);
                return ['success' => false, 'message' => translate('Failed to copy update files. Please check file permissions.')];
            }

            \Log::info('Update files copied successfully');

            // ── Step 5: Vendor replacement ──
            $includeVendor = $configJson['include_vendor'] ?? false;
            if ($includeVendor) {
                $details['vendor'] = $this->handleVendorUpdate($basePath);
            }

            // ── Step 6: Run migrations ──
            $migrationResults = $this->_runMigrations($configJson);
            $details['migrations'] = $migrationResults;

            // ── Step 7: Run seeders ──
            $seederResults = $this->_runSeeder($configJson);
            $details['seeders'] = $seederResults;

            // ── Step 7.5: Wipe bootstrap/cache BEFORE autoload regen ──
            // composer dump-autoload --no-dev strips dev-only packages (debugbar, ignition) from
            // the classmap. Stale entries in bootstrap/cache/packages.php and services.php still
            // reference those classes and crash the next request with "Class not found". Always
            // wipe these caches so Laravel rebuilds them based on whatever the autoloader exposes.
            $bootstrapCache = base_path('bootstrap/cache');
            if (is_dir($bootstrapCache)) {
                foreach (glob($bootstrapCache . '/*.php') as $cacheFile) {
                    @unlink($cacheFile);
                }
                \Log::info('[SystemUpdate] bootstrap/cache wiped');
            }

            // ── Step 7.6: Regenerate autoloader (handles new classes without composer install) ──
            $this->regenerateAutoloader();

            // ── Step 8: Update version in database (safe — handles missing unique constraint) ──
            DB::table('settings')->updateOrInsert(
                ['key' => 'app_version'],
                ['value' => $newVersion, 'updated_at' => Carbon::now()]
            );
            DB::table('settings')->updateOrInsert(
                ['key' => 'system_installed_at'],
                ['value' => Carbon::now(), 'updated_at' => Carbon::now()]
            );

            // ── Step 9: Clear all caches ──
            try {
                if (function_exists('optimize_clear')) {
                    optimize_clear();
                }
                clearstatcache(true);
                $details['cache_cleared'] = true;
                \Log::info('Cache cleared after update');
            } catch (\Exception $e) {
                \Log::warning('Cache clear warning: ' . $e->getMessage());
            }

            // Build success message
            $message = translate('Your system updated successfully to version') . ' ' . $newVersion;

            if (!empty($migrationResults['errors'])) {
                $message .= '. ' . translate('Some migrations had issues - please check the logs.');
            }
            if (!empty($seederResults['errors'])) {
                $message .= '. ' . translate('Some seeders had issues - please check the logs.');
            }

            \Log::info('Update completed successfully', [
                'version' => $newVersion,
                'vendor_updated' => $details['vendor'],
                'migration_success' => $migrationResults['success'] ?? true,
            ]);

            return ['success' => true, 'message' => $message, 'details' => $details];

        } catch (\Exception $e) {
            \Log::error('Update process exception', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return ['success' => false, 'message' => translate('Update failed: ') . strip_tags($e->getMessage())];
        } finally {
            // Always clean up temp directory
            if (file_exists($basePath)) {
                $this->deleteDirectory($basePath);
            }
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  Vendor Directory Handling
    // ─────────────────────────────────────────────────────────────

    /**
     * Replace vendor directory from update package
     *
     * For shared hosting users who cannot run composer install.
     * The update package includes a pre-built vendor directory.
     *
     * @param string $extractedPath Path where update package was extracted
     * @return bool Whether vendor was replaced
     */
    private function handleVendorUpdate(string $extractedPath): bool
    {
        $vendorSrc = $extractedPath . 'src/vendor';
        $vendorDst = base_path('vendor');

        // Also check without src/ prefix (depends on ZIP structure)
        if (!is_dir($vendorSrc)) {
            $vendorSrc = $extractedPath . 'vendor';
        }

        if (!is_dir($vendorSrc)) {
            \Log::info('Vendor directory not found in update package, skipping vendor replacement');
            return false;
        }

        // Verify the source has autoload.php
        if (!file_exists($vendorSrc . '/autoload.php')) {
            \Log::warning('Vendor directory in update package is missing autoload.php, skipping');
            return false;
        }

        \Log::info('Starting vendor directory replacement', [
            'source' => $vendorSrc,
            'destination' => $vendorDst,
        ]);

        try {
            // Delete existing vendor directory
            if (is_dir($vendorDst)) {
                // Use File facade for more reliable recursive delete
                File::deleteDirectory($vendorDst);

                // Fallback if File facade didn't fully remove it
                if (is_dir($vendorDst)) {
                    $this->deleteDirectory($vendorDst);
                }
            }

            // Copy new vendor
            $copied = $this->copyDirectory($vendorSrc, $vendorDst);

            if (!$copied) {
                \Log::error('Failed to copy vendor directory');
                return false;
            }

            // Verify autoloader exists after copy
            if (!file_exists($vendorDst . '/autoload.php')) {
                \Log::error('Vendor autoload.php missing after replacement');
                return false;
            }

            \Log::info('Vendor directory replaced successfully');
            return true;

        } catch (\Exception $e) {
            \Log::error('Vendor replacement failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  Migrations & Seeders
    // ─────────────────────────────────────────────────────────────

    private function _runMigrations(array $json): array
    {
        $results = ['success' => true, 'errors' => []];

        try {
            $runner = new \App\Services\Core\SafeMigrationRunner();
            $migrationResults = $runner->runFromConfig($json);

            $results['success'] = $runner->isSuccessful();
            $results['errors'] = array_map(function ($item) {
                return is_array($item) ? ($item['file'] . ': ' . ($item['error'] ?? 'Unknown error')) : $item;
            }, $migrationResults['failed'] ?? []);

            \Log::info("[SystemUpdate] Migration summary", [
                'success_count' => count($migrationResults['success'] ?? []),
                'skipped_count' => count($migrationResults['skipped'] ?? []),
                'failed_count' => count($migrationResults['failed'] ?? []),
                'warnings_count' => count($migrationResults['warnings'] ?? []),
            ]);

            // Also run default migrations as fallback
            try {
                Artisan::call('migrate', ['--force' => true]);
                \Log::info("Default migrations executed successfully");
            } catch (\Exception $e) {
                \Log::info("Default migration note: " . $e->getMessage());
            }

        } catch (\Exception $e) {
            $results['success'] = false;
            $results['errors'][] = "Migration system error: " . $e->getMessage();
            \Log::error("Migration system error", ['error' => $e->getMessage()]);

            try {
                Artisan::call('migrate', ['--force' => true]);
            } catch (\Exception $fallbackError) {
                \Log::warning("Fallback migration failed: " . $fallbackError->getMessage());
            }
        }

        return $results;
    }

    /**
     * Run seeders for versions newer than current.
     * Note: SafeMigrationRunner::runFromConfig() already runs seeders internally,
     * so this method only runs seeders that weren't already handled there.
     * We use _getFormattedFiles() which filters by version_compare.
     */
    private function _runSeeder(array $json): array
    {
        // Seeders are already run inside SafeMigrationRunner::runFromConfig()
        // This is kept as a safety net — _getFormattedFiles deduplicates via array_unique
        $results = ['success' => true, 'errors' => []];
        $seeders = Arr::get($json, 'seeder', []);

        if (count($seeders) > 0) {
            $seederFiles = $this->_getFormattedFiles($seeders);
            foreach ($seederFiles as $seeder) {
                try {
                    Artisan::call('db:seed', [
                        '--class' => $seeder,
                        '--force' => true,
                    ]);
                    \Log::info("Seeder executed successfully: {$seeder}");
                } catch (\Exception $e) {
                    $results['success'] = false;
                    $results['errors'][] = "Seeder failed ({$seeder}): " . $e->getMessage();
                    \Log::error("Seeder failed: {$seeder}", ['error' => $e->getMessage()]);
                }
            }
        }

        return $results;
    }

    private function _getFormattedFiles(array $files): array
    {
        $currentVersion = trim((string) site_settings(key: "app_version", default: '1.1'));
        $formattedFiles = [];
        foreach ($files as $version => $file) {
            if (version_compare((string) $version, $currentVersion, '>')) {
                $formattedFiles[] = $file;
            }
        }

        return array_unique(Arr::collapse($formattedFiles));
    }

    // ─────────────────────────────────────────────────────────────
    //  File System Utilities
    // ─────────────────────────────────────────────────────────────

    /**
     * Copy directory recursively with file protection
     *
     * @param string $src Source directory
     * @param string $dst Destination directory
     * @param array $protected Files/directories to never overwrite
     * @return bool
     */
    public function copyDirectory(string $src, string $dst, array $protected = []): bool
    {
        try {
            $dir = opendir($src);
            @mkdir($dst);
            while (false !== ($file = readdir($dir))) {
                if (($file != '.') && ($file != '..')) {
                    // Skip protected files and directories
                    if (in_array($file, $protected)) {
                        \Log::info("[SystemUpdate] Skipped protected file: {$file}");
                        continue;
                    }

                    if (is_dir($src . '/' . $file)) {
                        $this->copyDirectory($src . '/' . $file, $dst . '/' . $file, $protected);
                    } else {
                        copy($src . '/' . $file, $dst . '/' . $file);
                    }
                }
            }
            closedir($dir);
        } catch (\Exception $e) {
            \Log::error("[SystemUpdate] Copy failed: " . $e->getMessage());
            return false;
        }

        return true;
    }

    /**
     * Files and directories that must NEVER be overwritten during update
     */
    private function getProtectedFiles(): array
    {
        return [
            '.env',
            '.license',
            '.runtime-config.json',
            'storage',       // Protect user uploads and framework cache
            'vendor',         // Vendor handled separately via handleVendorUpdate
        ];
    }

    /**
     * Regenerate Composer autoloader without requiring full composer install
     *
     * This handles the case where new PHP classes are added in the update
     * but the autoloader doesn't know about them yet.
     * Works on shared hosting (cPanel) without terminal access.
     */
    private function regenerateAutoloader(): void
    {
        try {
            // Method 1: Try composer dump-autoload if available
            $composerPaths = [
                base_path('composer.phar'),
                '/usr/local/bin/composer',
                '/usr/bin/composer',
                'composer',
            ];

            $composerBin = null;
            foreach ($composerPaths as $path) {
                if (file_exists($path) || $path === 'composer') {
                    $composerBin = $path;
                    break;
                }
            }

            if ($composerBin) {
                $command = $composerBin === 'composer'
                    ? 'composer dump-autoload -o --no-dev 2>&1'
                    : "php {$composerBin} dump-autoload -o --no-dev 2>&1";

                $cwd = getcwd();
                chdir(base_path());
                $output = @shell_exec($command);
                chdir($cwd);

                if ($output && str_contains($output, 'Generating optimized autoload files')) {
                    \Log::info('[SystemUpdate] Composer autoload regenerated successfully');
                    return;
                }
            }

            // Method 2: Fallback — use Laravel's package:discover + clear compiled
            try {
                Artisan::call('package:discover');
                \Log::info('[SystemUpdate] Package discovery completed');
            } catch (\Exception $e) {
                \Log::info('[SystemUpdate] Package discovery note: ' . $e->getMessage());
            }

            // Method 3: Clear compiled classes so Laravel rebuilds from scratch
            $compiledPath = base_path('bootstrap/cache/packages.php');
            $servicesPath = base_path('bootstrap/cache/services.php');

            foreach ([$compiledPath, $servicesPath] as $cachePath) {
                if (file_exists($cachePath)) {
                    @unlink($cachePath);
                }
            }

            \Log::info('[SystemUpdate] Bootstrap cache cleared for autoloader refresh');

        } catch (\Exception $e) {
            \Log::warning('[SystemUpdate] Autoloader regeneration note: ' . $e->getMessage());
            // Non-fatal — the app may still work if classes exist in vendor/composer/autoload_classmap
        }
    }

    /**
     * Delete directory recursively
     */
    public function deleteDirectory(string $dirname): bool
    {
        try {
            if (!is_dir($dirname)) {
                return false;
            }
            $dir_handle = opendir($dirname);
            if (!$dir_handle) return false;

            while ($file = readdir($dir_handle)) {
                if ($file != "." && $file != "..") {
                    if (!is_dir($dirname . "/" . $file))
                        unlink($dirname . "/" . $file);
                    else
                        $this->deleteDirectory($dirname . '/' . $file);
                }
            }
            closedir($dir_handle);
            rmdir($dirname);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function removeDirectory($basePath)
    {
        if (File::exists($basePath)) {
            File::deleteDirectory($basePath);
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  Helper Methods
    // ─────────────────────────────────────────────────────────────

    /**
     * Parse PHP ini size values (e.g., "128M", "1G") to bytes
     */
    private function parseIniBytes(string $value): int
    {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1] ?? '');
        $numericValue = (int) $value;

        switch ($last) {
            case 'g': $numericValue *= 1024 * 1024 * 1024; break;
            case 'm': $numericValue *= 1024 * 1024; break;
            case 'k': $numericValue *= 1024; break;
        }

        return $numericValue;
    }

    /**
     * Format bytes to human-readable string
     */
    private function formatBytes(int|float $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    // ─────────────────────────────────────────────────────────────
    //  Database Health Check
    // ─────────────────────────────────────────────────────────────

    /**
     * Show database health check page — compares actual DB against expected schema
     */
    public function databaseHealth()
    {
        $title = translate('Database Health Check');
        $reconciler = new \App\Services\Core\SchemaReconciler();

        $snapshotPath = database_path('schema_snapshot.json');
        $hasSnapshot = file_exists($snapshotPath);

        $issues = [
            'missing_tables'  => [],
            'missing_columns' => [],
            'type_mismatches' => [],
            'missing_indexes' => [],
        ];

        if ($hasSnapshot) {
            $expected = json_decode(file_get_contents($snapshotPath), true);
            $expectedTables = $expected['tables'] ?? [];

            foreach ($expectedTables as $tableName => $tableSchema) {
                if (!\Illuminate\Support\Facades\Schema::hasTable($tableName)) {
                    $issues['missing_tables'][] = $tableName;
                    continue;
                }

                $actualColumns = \Illuminate\Support\Facades\DB::select(
                    "SELECT COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION",
                    [config('database.connections.mysql.database'), $tableName]
                );
                $actualMap = collect($actualColumns)->keyBy('COLUMN_NAME');

                foreach ($tableSchema['columns'] ?? [] as $colName => $colDef) {
                    if (!$actualMap->has($colName)) {
                        $issues['missing_columns'][] = ['table' => $tableName, 'column' => $colName, 'type' => $colDef['type']];
                    } else {
                        $expType = preg_replace('/\b(int|bigint|smallint|tinyint|mediumint)\(\d+\)/', '$1', strtolower(trim($colDef['type'])));
                        $actType = preg_replace('/\b(int|bigint|smallint|tinyint|mediumint)\(\d+\)/', '$1', strtolower(trim($actualMap[$colName]->COLUMN_TYPE)));
                        if ($expType !== $actType) {
                            $issues['type_mismatches'][] = [
                                'table'    => $tableName,
                                'column'   => $colName,
                                'expected' => $colDef['type'],
                                'actual'   => $actualMap[$colName]->COLUMN_TYPE,
                            ];
                        }
                    }
                }
            }
        }

        $totalIssues = count($issues['missing_tables']) + count($issues['missing_columns'])
                     + count($issues['type_mismatches']) + count($issues['missing_indexes']);

        return view('admin.setting.database_health', compact('title', 'issues', 'totalIssues', 'hasSnapshot'));
    }

    /**
     * Run database health fix (schema reconciliation)
     */
    public function databaseHealthFix()
    {
        try {
            $reconciler = new \App\Services\Core\SchemaReconciler();
            $results = $reconciler->reconcile();

            $message = $reconciler->getSummaryMessage();
            $notify[] = ['success', $message];

            \Log::info('[DatabaseHealthFix] ' . $message, $results);

            return back()->withNotify($notify);
        } catch (\Exception $e) {
            $notify[] = ['error', translate('Fix failed: ') . $e->getMessage()];
            return back()->withNotify($notify);
        }
    }
}
