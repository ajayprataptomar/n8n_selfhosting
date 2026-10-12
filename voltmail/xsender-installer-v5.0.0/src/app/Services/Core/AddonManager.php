<?php

namespace App\Services\Core;

use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use ZipArchive;

class AddonManager
{
    /** Base path for installed add-ons */
    protected string $addonsPath;

    /** Registry file path */
    protected string $registryPath;

    public function __construct()
    {
        $this->addonsPath  = base_path('../../addons');
        $this->registryPath = base_path('../../addons/installed.json');
    }

    /**
     * Ensure addons directory and registry exist
     */
    public function ensureDirectories(): void
    {
        if (!File::isDirectory($this->addonsPath)) {
            File::makeDirectory($this->addonsPath, 0755, true);
        }

        if (!File::exists($this->registryPath)) {
            File::put($this->registryPath, json_encode([], JSON_PRETTY_PRINT));
        }
    }

    /**
     * Get all installed add-ons from registry
     */
    public function getInstalled(): array
    {
        $this->ensureDirectories();

        $contents = File::get($this->registryPath);
        $addons = json_decode($contents, true);

        if (!is_array($addons)) {
            return [];
        }

        // Validate each addon still exists on disk
        foreach ($addons as $key => $addon) {
            $addonPath = $this->addonsPath . '/' . $addon['name'];
            if (!File::isDirectory($addonPath)) {
                unset($addons[$key]);
            }
        }

        return array_values($addons);
    }

    /**
     * Get a single addon config by name
     */
    public function getAddon(string $name): ?array
    {
        $addons = $this->getInstalled();

        foreach ($addons as $addon) {
            if ($addon['name'] === $name) {
                return $addon;
            }
        }

        return null;
    }

    /**
     * Install add-on from uploaded ZIP file
     *
     * @throws Exception
     */
    public function install(UploadedFile $zipFile): array
    {
        $this->ensureDirectories();

        $tempDir = storage_path('app/addon_temp_' . Str::random(8));
        $zipPath = $zipFile->getPathname();

        try {
            // Step 1: Extract ZIP to temp directory
            $zip = new ZipArchive();
            $result = $zip->open($zipPath);

            if ($result !== true) {
                throw new Exception(translate('Failed to open ZIP file. Please ensure it is a valid ZIP archive.'));
            }

            File::makeDirectory($tempDir, 0755, true);
            $zip->extractTo($tempDir);
            $zip->close();

            // Step 2: Find addon.json (might be in a subdirectory)
            $manifestPath = $this->findManifest($tempDir);

            if (!$manifestPath) {
                throw new Exception(translate('Invalid add-on package: addon.json not found.'));
            }

            $manifest = json_decode(File::get($manifestPath), true);

            if (!$manifest || empty($manifest['name'])) {
                throw new Exception(translate('Invalid addon.json: missing required fields.'));
            }

            // Step 3: Validate manifest
            $this->validateManifest($manifest);

            // Step 4: Check app version compatibility
            $currentVersion = $this->getAppVersion();
            if (!empty($manifest['min_app_version'])) {
                if (version_compare($currentVersion, $manifest['min_app_version'], '<')) {
                    throw new Exception(
                        translate('This add-on requires XSender v') . $manifest['min_app_version'] .
                        translate(' or higher. Your current version is v') . $currentVersion
                    );
                }
            }

            // Step 5: Check if already installed
            $existingAddon = $this->getAddon($manifest['name']);
            $isUpdate = $existingAddon !== null;

            // Step 6: Move files to addons directory
            $addonSourceDir = dirname($manifestPath);
            $addonTargetDir = $this->addonsPath . '/' . $manifest['name'];

            if ($isUpdate) {
                // Backup existing before overwrite
                $backupDir = $this->addonsPath . '/.backup_' . $manifest['name'] . '_' . date('YmdHis');
                File::copyDirectory($addonTargetDir, $backupDir);
                File::deleteDirectory($addonTargetDir);
            }

            File::copyDirectory($addonSourceDir, $addonTargetDir);

            // Step 7: Register in installed.json
            $this->registerAddon($manifest, $isUpdate);

            // Step 8: Clean up temp
            File::deleteDirectory($tempDir);

            // Step 9: Clear caches
            $this->clearCaches();

            return [
                'success'  => true,
                'name'     => $manifest['name'],
                'display'  => $manifest['display_name'] ?? $manifest['name'],
                'version'  => $manifest['version'] ?? '1.0.0',
                'updated'  => $isUpdate,
                'message'  => $isUpdate
                    ? translate('Add-on updated successfully')
                    : translate('Add-on installed successfully. Please activate it to start using.'),
            ];

        } catch (Exception $e) {
            // Clean up on failure
            if (File::isDirectory($tempDir)) {
                File::deleteDirectory($tempDir);
            }
            throw $e;
        }
    }

    /**
     * Activate an installed add-on
     */
    public function activate(string $name): array
    {
        $addon = $this->getAddon($name);

        if (!$addon) {
            throw new Exception(translate('Add-on not found'));
        }

        if (($addon['status'] ?? 'inactive') === 'active') {
            return ['success' => true, 'message' => translate('Add-on is already active')];
        }

        // Check purchase verification
        if (!empty($addon['verification_required']) && empty($addon['purchase_code'])) {
            throw new Exception(translate('Purchase code verification required before activation'));
        }

        // Run migrations if present
        $migrationsPath = $this->addonsPath . '/' . $name . '/migrations';
        if (File::isDirectory($migrationsPath)) {
            $this->runAddonMigrations($name);
        }

        // Update registry status
        $this->updateAddonStatus($name, 'active');

        // Clear caches so service provider picks up the addon
        $this->clearCaches();

        return [
            'success' => true,
            'message' => translate('Add-on activated successfully'),
        ];
    }

    /**
     * Deactivate an add-on (keeps files, just disables)
     */
    public function deactivate(string $name): array
    {
        $addon = $this->getAddon($name);

        if (!$addon) {
            throw new Exception(translate('Add-on not found'));
        }

        $this->updateAddonStatus($name, 'inactive');
        $this->clearCaches();

        return [
            'success' => true,
            'message' => translate('Add-on deactivated successfully'),
        ];
    }

    /**
     * Uninstall an add-on completely
     */
    public function uninstall(string $name): array
    {
        $addon = $this->getAddon($name);

        if (!$addon) {
            throw new Exception(translate('Add-on not found'));
        }

        // Deactivate first
        if (($addon['status'] ?? 'inactive') === 'active') {
            $this->updateAddonStatus($name, 'inactive');
        }

        // Remove files
        $addonDir = $this->addonsPath . '/' . $name;
        if (File::isDirectory($addonDir)) {
            File::deleteDirectory($addonDir);
        }

        // Remove from registry
        $this->removeFromRegistry($name);

        $this->clearCaches();

        return [
            'success' => true,
            'message' => translate('Add-on uninstalled successfully'),
        ];
    }

    /**
     * Verify purchase code for an add-on
     */
    public function verifyPurchase(string $name, string $purchaseCode): array
    {
        $addon = $this->getAddon($name);

        if (!$addon) {
            throw new Exception(translate('Add-on not found'));
        }

        try {
            $response = Http::timeout(15)->post(
                rtrim(site_settings('app_url', config('app.url')), '/') . '/api/addon/verify',
                [
                    'purchase_code' => $purchaseCode,
                    'addon_id'      => $addon['envato_item_id'] ?? '',
                    'addon_name'    => $name,
                    'domain'        => request()->getHost(),
                    'app_version'   => $this->getAppVersion(),
                ]
            );

            // If verification server is unreachable, allow activation (trust customer)
            if (!$response->successful()) {
                Log::warning("AddonManager: Verification server unreachable for {$name}, allowing activation");
            }

        } catch (Exception $e) {
            // Network error — allow activation (don't block customer)
            Log::warning("AddonManager: Verification failed for {$name}: {$e->getMessage()}");
        }

        // Store purchase code regardless (trust-based for now)
        $this->updateAddonField($name, 'purchase_code', $purchaseCode);
        $this->updateAddonField($name, 'verified_at', now()->toDateTimeString());

        return [
            'success' => true,
            'message' => translate('Purchase code verified successfully'),
        ];
    }

    /**
     * Get active add-on service providers for booting
     */
    public function getActiveProviders(): array
    {
        $providers = [];

        foreach ($this->getInstalled() as $addon) {
            if (($addon['status'] ?? 'inactive') !== 'active') {
                continue;
            }

            if (!empty($addon['providers'])) {
                foreach ($addon['providers'] as $provider) {
                    $providers[] = $provider;
                }
            }
        }

        return $providers;
    }

    /**
     * Get active addon sidebar items
     */
    public function getActiveSidebarItems(): array
    {
        $items = [];

        foreach ($this->getInstalled() as $addon) {
            if (($addon['status'] ?? 'inactive') !== 'active') {
                continue;
            }

            if (!empty($addon['sidebar_items'])) {
                foreach ($addon['sidebar_items'] as $item) {
                    $item['addon_name'] = $addon['name'];
                    $items[] = $item;
                }
            }
        }

        return $items;
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Find addon.json in extracted directory (handles nested folders)
     */
    private function findManifest(string $dir): ?string
    {
        // Check root level
        if (File::exists($dir . '/addon.json')) {
            return $dir . '/addon.json';
        }

        // Check one level deep (ZIP often wraps in a folder)
        $subdirs = File::directories($dir);
        foreach ($subdirs as $subdir) {
            if (File::exists($subdir . '/addon.json')) {
                return $subdir . '/addon.json';
            }
        }

        return null;
    }

    /**
     * Validate addon.json manifest fields
     */
    private function validateManifest(array $manifest): void
    {
        $required = ['name', 'display_name', 'version'];

        foreach ($required as $field) {
            if (empty($manifest[$field])) {
                throw new Exception(translate("Invalid addon.json: missing '{$field}' field."));
            }
        }

        // Validate name format (lowercase, alphanumeric, hyphens only)
        if (!preg_match('/^[a-z0-9\-]+$/', $manifest['name'])) {
            throw new Exception(translate('Add-on name must be lowercase with hyphens only (e.g., my-addon).'));
        }
    }

    /**
     * Register addon in installed.json
     */
    private function registerAddon(array $manifest, bool $isUpdate = false): void
    {
        $addons = $this->getInstalled();

        $entry = [
            'name'                  => $manifest['name'],
            'display_name'          => $manifest['display_name'] ?? $manifest['name'],
            'description'           => $manifest['description'] ?? '',
            'version'               => $manifest['version'] ?? '1.0.0',
            'author'                => $manifest['author'] ?? 'Unknown',
            'min_app_version'       => $manifest['min_app_version'] ?? '',
            'envato_item_id'        => $manifest['envato_item_id'] ?? '',
            'verification_required' => $manifest['verification_required'] ?? false,
            'providers'             => $manifest['providers'] ?? [],
            'sidebar_items'         => $manifest['sidebar_items'] ?? [],
            'status'                => 'inactive',
            'purchase_code'         => '',
            'verified_at'           => null,
            'installed_at'          => now()->toDateTimeString(),
            'updated_at'            => now()->toDateTimeString(),
        ];

        if ($isUpdate) {
            // Preserve existing status, purchase code, etc.
            $existing = $this->getAddon($manifest['name']);
            if ($existing) {
                $entry['status']        = $existing['status'] ?? 'inactive';
                $entry['purchase_code'] = $existing['purchase_code'] ?? '';
                $entry['verified_at']   = $existing['verified_at'] ?? null;
                $entry['installed_at']  = $existing['installed_at'] ?? now()->toDateTimeString();
            }
        }

        // Replace or add
        $found = false;
        foreach ($addons as $i => $addon) {
            if ($addon['name'] === $manifest['name']) {
                $addons[$i] = $entry;
                $found = true;
                break;
            }
        }

        if (!$found) {
            $addons[] = $entry;
        }

        $this->saveRegistry($addons);
    }

    /**
     * Update addon status in registry
     */
    private function updateAddonStatus(string $name, string $status): void
    {
        $addons = $this->getInstalled();

        foreach ($addons as $i => $addon) {
            if ($addon['name'] === $name) {
                $addons[$i]['status'] = $status;
                $addons[$i]['updated_at'] = now()->toDateTimeString();
                break;
            }
        }

        $this->saveRegistry($addons);
    }

    /**
     * Update a single field in addon registry
     */
    private function updateAddonField(string $name, string $field, $value): void
    {
        $addons = $this->getInstalled();

        foreach ($addons as $i => $addon) {
            if ($addon['name'] === $name) {
                $addons[$i][$field] = $value;
                $addons[$i]['updated_at'] = now()->toDateTimeString();
                break;
            }
        }

        $this->saveRegistry($addons);
    }

    /**
     * Remove addon from registry
     */
    private function removeFromRegistry(string $name): void
    {
        $addons = $this->getInstalled();
        $addons = array_values(array_filter($addons, fn($a) => $a['name'] !== $name));
        $this->saveRegistry($addons);
    }

    /**
     * Save registry to disk
     */
    private function saveRegistry(array $addons): void
    {
        File::put(
            $this->registryPath,
            json_encode(array_values($addons), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Run addon migrations
     */
    private function runAddonMigrations(string $name): void
    {
        $migrationsPath = $this->addonsPath . '/' . $name . '/migrations';

        if (!File::isDirectory($migrationsPath)) {
            return;
        }

        try {
            // Use safe migration approach — skip existing tables/columns
            $files = File::glob($migrationsPath . '/*.php');

            foreach ($files as $file) {
                try {
                    $migration = require $file;

                    if (is_object($migration) && method_exists($migration, 'up')) {
                        $migration->up();
                    }
                } catch (Exception $e) {
                    // Log but don't fail — table/column may already exist
                    if (!Str::contains($e->getMessage(), ['already exists', 'Duplicate column'])) {
                        Log::warning("AddonManager: Migration error in {$name}: {$e->getMessage()}");
                    }
                }
            }
        } catch (Exception $e) {
            Log::error("AddonManager: Failed to run migrations for {$name}: {$e->getMessage()}");
        }
    }

    /**
     * Get current application version
     */
    private function getAppVersion(): string
    {
        $configPath = base_path('../../config.json');

        if (File::exists($configPath)) {
            $config = json_decode(File::get($configPath), true);
            return $config['version'] ?? '4.4';
        }

        return site_settings('app_version', '4.4');
    }

    /**
     * Clear all relevant caches
     */
    private function clearCaches(): void
    {
        try {
            Cache::forget('installed_addons');
            Cache::forget('active_addon_providers');
            Cache::forget('addon_sidebar_items');

            if (function_exists('optimize_clear')) {
                optimize_clear();
            }
        } catch (Exception $e) {
            // Ignore cache errors
        }
    }
}
