<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use ZipArchive;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

class GenerateUpdatePackage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:generate
                            {version : The version number (e.g., 4.3)}
                            {--previous= : Previous version to compare migrations (e.g., 4.2)}
                            {--changelog= : Changelog description for this version}
                            {--include-vendor : Include vendor directory in package for shared hosting users}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate an update package ZIP file for distribution to existing users';

    /**
     * Folders to include in the update package
     */
    protected array $includeFolders = [
        'src/app',
        'src/config',
        'src/database',
        'src/resources',
        'src/routes',
        'assets/theme/global/css',
        'assets/theme/admin/css',
        'xsender-whatsapp-service/src',
        'xsender-whatsapp-service/storage',
    ];

    /**
     * Individual files to include
     */
    protected array $includeFiles = [
        'src/config.json',
        'src/composer.json',
        'xsender-whatsapp-service/package.json',
        'xsender-whatsapp-service/package-lock.json',
        'xsender-whatsapp-service/ecosystem.config.cjs',
        'xsender-whatsapp-service/.env.example',
        'xsender-whatsapp-service/CPANEL-SETUP.md',
        'xsender-whatsapp-service/cpanel-start.cjs',
        'xsender-whatsapp-service/.htaccess',
        'xsender-whatsapp-service/.gitignore',
    ];

    /**
     * Folders/files to exclude
     */
    protected array $excludePatterns = [
        '.git',
        'node_modules',
        'vendor',
        'storage/logs',
        'storage/framework/cache',
        'storage/framework/sessions',
        'storage/framework/views',
        'storage/app',
        '.DS_Store',
        'Thumbs.db',
        '.editorconfig',
        '.npm-cache',
        'xsender-whatsapp-service/logs',
        'xsender-whatsapp-service/storage/sessions',
        '.runtime-config.json',
        '.license',
    ];

    /**
     * Extra exclude patterns when vendor is included
     * These reduce vendor ZIP size by removing unnecessary files
     */
    protected array $vendorExcludePatterns = [
        'vendor/.git',
        'vendor/*/tests',
        'vendor/*/Tests',
        'vendor/*/test',
        'vendor/*/docs',
        'vendor/*/*/tests',
        'vendor/*/*/Tests',
        'vendor/phpunit',
        'vendor/mockery',
        'vendor/fakerphp',
        '.github',
        'CHANGELOG',
        'UPGRADE',
        '.editorconfig',
        '.styleci',
        '.travis.yml',
        'phpunit.xml',
        'phpstan.neon',
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $newVersion = trim((string) $this->argument('version'));
        $previousVersion = $this->option('previous') ?? $this->detectPreviousVersion();
        $changelog = $this->option('changelog') ?? '';
        $includeVendor = $this->option('include-vendor');

        // Sanity-check version: prevent shipping a test/garbage value (e.g. "999.0", "0.0")
        // that would brick customer installs by pinning DB app_version above any future release.
        if (!preg_match('/^[0-9]+(\.[0-9]+){1,2}$/', $newVersion)) {
            $this->error("Refusing: version '{$newVersion}' is not in the expected N.N or N.N.N format.");
            return Command::FAILURE;
        }
        $majorPart = (int) explode('.', $newVersion)[0];
        if ($majorPart < 1 || $majorPart > 99) {
            $this->error("Refusing: version '{$newVersion}' has major component {$majorPart}. Expected 1–99. Did you accidentally pass a test value?");
            return Command::FAILURE;
        }
        if (version_compare($newVersion, $previousVersion, '<=')) {
            $this->error("Refusing: new version '{$newVersion}' is not greater than previous '{$previousVersion}'.");
            return Command::FAILURE;
        }

        $this->info("╔══════════════════════════════════════════════════════════╗");
        $this->info("║       XSENDER UPDATE PACKAGE GENERATOR                   ║");
        $this->info("╚══════════════════════════════════════════════════════════╝");
        $this->newLine();

        $this->info("📦 Generating update package...");
        $this->line("   New Version: {$newVersion}");
        $this->line("   Previous Version: {$previousVersion}");
        $this->line("   Include Vendor: " . ($includeVendor ? 'YES' : 'No'));
        $this->newLine();

        // Step 1: Detect new migrations
        $this->info("Step 1: Detecting new migrations...");
        $newMigrations = $this->detectNewMigrations($previousVersion, $newVersion);

        if (count($newMigrations) > 0) {
            $this->line("   Found " . count($newMigrations) . " new migration(s):");
            foreach ($newMigrations as $migration) {
                $this->line("   - {$migration}");
            }
        } else {
            $this->line("   No new migrations detected.");
        }
        $this->newLine();

        // Step 2: Update config.json
        $this->info("Step 2: Updating config.json...");
        $this->updateConfigJson($newVersion, $newMigrations, $includeVendor);
        $this->line("   ✓ config.json updated");
        $this->newLine();

        // Step 3: Update installer.php version
        $this->info("Step 3: Updating installer config version...");
        $this->updateInstallerVersion($newVersion);
        $this->line("   ✓ installer.php version updated to {$newVersion}");
        $this->newLine();

        // Step 3.5: Generate fresh schema snapshot
        $this->info("Step 3.5: Generating schema snapshot...");
        try {
            $reconciler = new \App\Services\Core\SchemaReconciler();
            $snapshot = $reconciler->generateSnapshot();
            $snapshot['version'] = $newVersion;
            $reconciler->saveSnapshot($snapshot);
            $tableCount = count($snapshot['tables'] ?? []);
            $this->line("   ✓ Schema snapshot generated ({$tableCount} tables)");
        } catch (\Throwable $e) {
            $this->warn("   ⚠ Schema snapshot failed: " . $e->getMessage());
            $this->warn("   Run `php artisan schema:snapshot` manually before packaging.");
        }
        $this->newLine();

        // Step 4: Create ZIP file
        $this->info("Step 4: Creating ZIP package...");
        $zipResult = $this->createZipPackage($newVersion, $includeVendor);

        if (!$zipResult['success']) {
            $this->error("   ✗ Failed to create ZIP: " . $zipResult['message']);
            return 1;
        }
        $this->newLine();

        // Summary
        $this->info("╔══════════════════════════════════════════════════════════╗");
        $this->info("║       ✅ UPDATE PACKAGE CREATED SUCCESSFULLY             ║");
        $this->info("╚══════════════════════════════════════════════════════════╝");
        $this->newLine();

        $summaryRows = [
            ['Version', $newVersion],
            ['Previous Version', $previousVersion],
            ['New Migrations', count($newMigrations)],
            ['Include Vendor', $includeVendor ? 'YES' : 'No'],
            ['Total Files', $zipResult['files']],
            ['Package Size', $this->formatBytes($zipResult['size'])],
            ['File Location', $zipResult['path']],
        ];

        $this->table(['Property', 'Value'], $summaryRows);

        $this->newLine();
        $this->info("📋 NEXT STEPS:");
        $this->line("   1. Test the update package on a staging server");
        $this->line("   2. Upload to your update server (verifylicense.online)");
        $this->line("   3. Users can update via Admin Panel > System Update");

        if ($includeVendor) {
            $this->newLine();
            $this->warn("⚠️  VENDOR INCLUDED:");
            $this->line("   This package includes the vendor directory for shared hosting users.");
            $this->line("   The package will be larger than usual. Consider creating two packages:");
            $this->line("   - Without vendor: for users with SSH/terminal access");
            $this->line("   - With vendor: for cPanel/shared hosting users");
        }

        $this->newLine();
        $this->info("🔐 LICENSE VERIFICATION:");
        $this->line("   ✓ Envato license verification is enabled");
        $this->line("   ✓ Users must have valid purchase key to download updates");
        $this->newLine();

        return 0;
    }

    /**
     * Detect the current/previous version from config.json
     */
    protected function detectPreviousVersion(): string
    {
        $configPath = base_path('config.json');
        if (file_exists($configPath)) {
            $config = json_decode(file_get_contents($configPath), true);
            return $config['version'] ?? '4.1';
        }
        return '4.1';
    }

    /**
     * Detect new migrations based on version dates
     */
    protected function detectNewMigrations(string $previousVersion, string $newVersion): array
    {
        $migrationsPath = base_path('database/migrations');
        $migrations = [];

        if (!is_dir($migrationsPath)) {
            return $migrations;
        }

        $files = scandir($migrationsPath);

        // Get existing migrations from config.json
        $configPath = base_path('config.json');
        $existingMigrations = [];

        if (file_exists($configPath)) {
            $config = json_decode(file_get_contents($configPath), true);
            foreach ($config['migrations'] ?? [] as $version => $versionMigrations) {
                foreach ($versionMigrations as $m) {
                    $existingMigrations[] = basename($m);
                }
            }
        }

        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
                continue;
            }

            if (in_array($file, $existingMigrations)) {
                continue;
            }

            if (preg_match('/^(\d{4})_(\d{2})_(\d{2})_/', $file, $matches)) {
                $migrationDate = "{$matches[1]}-{$matches[2]}-{$matches[3]}";
                $thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));

                if ($migrationDate >= $thirtyDaysAgo) {
                    $migrations[] = "database/migrations/{$file}";
                }
            }
        }

        return $migrations;
    }

    /**
     * Update config.json with new version, migrations, and vendor flag
     */
    protected function updateConfigJson(string $version, array $newMigrations, bool $includeVendor = false): void
    {
        $configPath = base_path('config.json');

        $config = [
            'version' => $version,
            'include_vendor' => false,
            'migrations' => [],
            'seeder' => [],
        ];

        // Load existing config
        if (file_exists($configPath)) {
            $existingConfig = json_decode(file_get_contents($configPath), true);
            $config['migrations'] = $existingConfig['migrations'] ?? [];
            $config['seeder'] = $existingConfig['seeder'] ?? [];
        }

        // Update version and vendor flag
        $config['version'] = $version;
        $config['include_vendor'] = $includeVendor;

        // Add new migrations for this version
        if (!isset($config['migrations'][$version])) {
            $config['migrations'][$version] = [];
        }

        foreach ($newMigrations as $migration) {
            if (!in_array($migration, $config['migrations'][$version])) {
                $config['migrations'][$version][] = $migration;
            }
        }

        // Ensure SettingsSeeder runs for this version
        if (!isset($config['seeder'][$version])) {
            $config['seeder'][$version] = ['SettingsSeeder'];
        }

        file_put_contents(
            $configPath,
            json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );
    }

    /**
     * Update installer.php version
     */
    protected function updateInstallerVersion(string $version): void
    {
        $installerPath = base_path('config/installer.php');

        if (!file_exists($installerPath)) {
            return;
        }

        $content = file_get_contents($installerPath);

        $content = preg_replace(
            "/'version'\s*=>\s*['\"][\d.]+['\"]/",
            "'version' => '{$version}'",
            $content
        );

        file_put_contents($installerPath, $content);
    }

    /**
     * Create the ZIP package
     */
    protected function createZipPackage(string $version, bool $includeVendor = false): array
    {
        $suffix = $includeVendor ? '_with_vendor' : '';
        $zipFileName = "xsender_update_v{$version}{$suffix}_" . date('Y-m-d_His') . '.zip';
        $zipPath = storage_path("app/{$zipFileName}");

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return ['success' => false, 'message' => "Cannot create zip file"];
        }

        $basePath = dirname(base_path()); // Parent of src directory
        $filesAdded = 0;

        // Add config.json to root of zip
        $zip->addFile(base_path('config.json'), 'config.json');
        $filesAdded++;

        // Temporarily remove 'vendor' from exclude if --include-vendor
        $originalExcludes = $this->excludePatterns;
        if ($includeVendor) {
            $this->excludePatterns = array_filter($this->excludePatterns, fn($p) => $p !== 'vendor');
            // Add vendor-specific excludes instead
            $this->excludePatterns = array_merge($this->excludePatterns, $this->vendorExcludePatterns);

            // Add vendor folder explicitly
            $vendorPath = $basePath . '/src/vendor';
            if (is_dir($vendorPath)) {
                $this->line("   Adding: src/vendor (this may take a while)...");
                $count = $this->addFolderToZip($zip, $vendorPath, 'src/vendor', $basePath);
                $filesAdded += $count;
                $this->line("   Added {$count} vendor files");
            } else {
                $this->warn("   ⚠️  Vendor directory not found at: {$vendorPath}");
            }

            // Restore original excludes for other folders (don't accidentally include vendor twice)
            $this->excludePatterns = $originalExcludes;
        }

        // Add standard folders
        foreach ($this->includeFolders as $folder) {
            $folderPath = $basePath . '/' . $folder;
            if (is_dir($folderPath)) {
                $this->line("   Adding: {$folder}...");
                $count = $this->addFolderToZip($zip, $folderPath, $folder, $basePath);
                $filesAdded += $count;
            }
        }

        // Add individual files
        foreach ($this->includeFiles as $file) {
            $filePath = $basePath . '/' . $file;
            if (file_exists($filePath) && $file !== 'src/config.json') {
                $zip->addFile($filePath, $file);
                $filesAdded++;
            }
        }

        $zip->close();

        return [
            'success' => true,
            'path' => $zipPath,
            'files' => $filesAdded,
            'size' => filesize($zipPath),
        ];
    }

    /**
     * Add folder to zip recursively
     */
    protected function addFolderToZip(ZipArchive $zip, string $folderPath, string $relativePath, string $basePath): int
    {
        $count = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($folderPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            $filePath = $file->getPathname();
            $localPath = $relativePath . '/' . $iterator->getSubPathname();

            if ($this->shouldExclude($localPath)) {
                continue;
            }

            if ($file->isDir()) {
                $zip->addEmptyDir($localPath);
            } else {
                $zip->addFile($filePath, $localPath);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Check if path should be excluded
     */
    protected function shouldExclude(string $path): bool
    {
        // Normalize path separators
        $normalizedPath = str_replace('\\', '/', $path);

        foreach ($this->excludePatterns as $pattern) {
            if (str_contains($normalizedPath, $pattern)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Format bytes to human readable
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
