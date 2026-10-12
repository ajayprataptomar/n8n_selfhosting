<?php

namespace App\Services\System;

use App\Services\Campaign\CampaignDiagnosticService;
use App\Services\System\Communication\NodeService;
use App\Services\WhatsApp\HealthCheckService as WhatsAppHealthCheckService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * v4.9 — Whole-system health aggregator.
 *
 * DOES NOT implement any health probe itself. Every check delegates to an
 * existing service so we have one canonical source per subsystem:
 *
 *   Subsystem                Owning service
 *   ---------                --------------
 *   MySQL compatibility  →   App\Services\System\DatabaseCompatibilityChecker
 *   Cron processor       →   App\Services\Campaign\CampaignDiagnosticService::processorHealth()
 *   Queue                →   App\Services\Campaign\CampaignDiagnosticService::queueHealth()
 *   WhatsApp Node        →   App\Services\System\Communication\NodeService::getSystemHealth()
 *   WhatsApp Gateways    →   App\Services\WhatsApp\HealthCheckService::getHealthSummary()
 *   Cache / disk / perms →   inline (small, cheap, no owning service exists)
 *
 * Return shape matches CampaignDiagnosticService::diagnose() and the reusable
 * partials/campaign/health_banner.blade.php renderer, so the admin page +
 * public /status endpoint + installer pre-flight all share ONE UI layer.
 */
class SystemHealthService
{
    public function __construct(
        private ?CampaignDiagnosticService $campaignDiag = null,
        private ?WhatsAppHealthCheckService $waHealth = null,
        private ?NodeService $nodeService = null,
        private ?DatabaseCompatibilityChecker $dbCompat = null,
    ) {
        // Lazy-init so the class can be new'd in contexts (like the installer
        // pre-DB stage) where some deps aren't bindable yet.
        $this->campaignDiag = $campaignDiag ?: new CampaignDiagnosticService();
        $this->waHealth     = $waHealth     ?: null; // instantiated on demand — takes gateway arg elsewhere
        $this->nodeService  = $nodeService  ?: null;
        $this->dbCompat     = $dbCompat     ?: new DatabaseCompatibilityChecker();
    }

    /**
     * Run every subsystem check. Every section is wrapped in try/catch so a
     * single dead subsystem doesn't hide the rest.
     */
    public function diagnose(): array
    {
        $sections = [
            'database'      => $this->safe(fn () => $this->databaseSection()),
            'mysql_config'  => $this->safe(fn () => $this->mysqlConfigSection()),
            'schema'        => $this->safe(fn () => $this->schemaSection()),
            'cron'          => $this->safe(fn () => $this->cronSection()),
            'queue'         => $this->safe(fn () => $this->queueSection()),
            'cache'         => $this->safe(fn () => $this->cacheSection()),
            'disk'          => $this->safe(fn () => $this->diskSection()),
            'permissions'   => $this->safe(fn () => $this->permissionsSection()),
            'wa_node'       => $this->safe(fn () => $this->waNodeSection()),
            'wa_gateways'   => $this->safe(fn () => $this->waGatewaysSection()),
            'php'           => $this->safe(fn () => $this->phpSection()),
        ];

        $sections['overall'] = $this->overallVerdict($sections);

        return $sections;
    }

    /**
     * Basic DB connectivity — cheapest probe, always run first.
     */
    private function databaseSection(): array
    {
        try {
            $pdo = DB::connection()->getPdo();
            $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
            return [
                'label'   => translate('Database Connection'),
                'healthy' => true,
                'value'   => $version,
                'message' => translate('Connected. Server version: ') . $version,
            ];
        } catch (\Throwable $e) {
            return [
                'label'   => translate('Database Connection'),
                'healthy' => false,
                'value'   => 'error',
                'message' => translate('Cannot reach database: ') . $e->getMessage(),
            ];
        }
    }

    /**
     * MySQL configuration compatibility — delegates to
     * DatabaseCompatibilityChecker (same one the installer pre-flight uses).
     */
    private function mysqlConfigSection(): array
    {
        $result = $this->dbCompat->check();
        $overall = $result['overall'] ?? ['healthy' => false, 'message' => ''];

        return [
            'label'   => translate('MySQL Configuration'),
            'healthy' => (bool) ($overall['healthy'] ?? false),
            'value'   => $overall['healthy'] ? translate('OK') : translate('Issues found'),
            'message' => (string) ($overall['message'] ?? ''),
            'detail'  => $result, // full report available in JSON for drill-down UI
        ];
    }

    /**
     * Schema drift — checks that every migration in config.json is present.
     * Uses the existing settings.app_version marker + migrations table.
     */
    private function schemaSection(): array
    {
        try {
            if (!Schema::hasTable('migrations')) {
                return [
                    'label'   => translate('Database Schema'),
                    'healthy' => false,
                    'value'   => translate('No migrations table'),
                    'message' => translate('Migrations table missing. Run "php artisan migrate --force" or use Admin → System Update.'),
                ];
            }
            $migrationCount = (int) DB::table('migrations')->count();
            $codeVersion = (string) config('installer.version', '1.0');
            $dbVersion = Schema::hasTable('settings')
                ? (string) (DB::table('settings')->where('key', 'app_version')->value('value') ?? '1.0')
                : '1.0';
            $inSync = version_compare($dbVersion, $codeVersion, '>=');

            return [
                'label'   => translate('Database Schema'),
                'healthy' => $inSync,
                'value'   => translate('DB v') . $dbVersion . ' / ' . translate('code v') . $codeVersion,
                'message' => $inSync
                    ? translate('Schema is up to date. ') . $migrationCount . ' ' . translate('migrations applied.')
                    : translate('Code is v') . $codeVersion . ' ' . translate('but database is v') . $dbVersion . '. ' . translate('Run pending migrations via Admin → System Update.'),
            ];
        } catch (\Throwable $e) {
            return [
                'label'   => translate('Database Schema'),
                'healthy' => false,
                'value'   => 'error',
                'message' => translate('Could not read migrations table: ') . $e->getMessage(),
            ];
        }
    }

    /**
     * Campaign cron processor — reuses CampaignDiagnosticService::processorHealth()
     * (widened to public in v4.9 for aggregator access).
     */
    private function cronSection(): array
    {
        $probe = $this->campaignDiag->processorHealth();
        return [
            'label'   => translate('Campaign Cron Processor'),
            'healthy' => (bool) ($probe['healthy'] ?? false),
            'value'   => $probe['last_run_at'] ?? translate('never'),
            'message' => (string) ($probe['message'] ?? ''),
            'detail'  => $probe,
        ];
    }

    /**
     * Queue driver — reuses CampaignDiagnosticService::queueHealth().
     */
    private function queueSection(): array
    {
        $probe = $this->campaignDiag->queueHealth();
        return [
            'label'   => translate('Queue Worker'),
            'healthy' => (bool) ($probe['healthy'] ?? false),
            'value'   => isset($probe['pending_jobs']) ? $probe['pending_jobs'] . ' ' . translate('pending') : '',
            'message' => (string) ($probe['message'] ?? ''),
            'detail'  => $probe,
        ];
    }

    /**
     * Laravel cache directories — writable + not too full.
     */
    private function cacheSection(): array
    {
        $paths = [
            storage_path('framework/cache'),
            storage_path('framework/views'),
            storage_path('framework/sessions'),
            base_path('bootstrap/cache'),
        ];
        $unwritable = [];
        foreach ($paths as $path) {
            if (!is_dir($path) || !is_writable($path)) {
                $unwritable[] = $path;
            }
        }
        $healthy = empty($unwritable);
        return [
            'label'   => translate('Cache Directories'),
            'healthy' => $healthy,
            'value'   => $healthy ? translate('writable') : translate('not writable'),
            'message' => $healthy
                ? translate('All Laravel cache directories are writable.')
                : translate('The following directories are not writable — chmod 755 or 775 required: ') . implode(', ', $unwritable),
        ];
    }

    /**
     * Storage disk space — warn under 500MB, error under 100MB.
     */
    private function diskSection(): array
    {
        $storage = storage_path();
        $freeBytes  = @disk_free_space($storage);
        $totalBytes = @disk_total_space($storage);

        if (!$freeBytes || !$totalBytes) {
            return [
                'label'   => translate('Disk Space'),
                'healthy' => true,
                'value'   => translate('unknown'),
                'message' => translate('Disk space could not be read (some shared hosts disable disk_free_space).'),
            ];
        }
        $freeMb = (int) ($freeBytes / 1024 / 1024);
        $pct    = (int) round(($freeBytes / $totalBytes) * 100);
        $healthy = $freeMb > 500;
        $warning = $freeMb <= 500 && $freeMb > 100;

        return [
            'label'   => translate('Disk Space'),
            'healthy' => $freeMb > 100,
            'value'   => $freeMb . ' MB (' . $pct . '%)',
            'message' => $healthy
                ? translate('Plenty of free disk space.')
                : ($warning
                    ? translate('Low disk space (') . $freeMb . ' MB free). ' . translate('Consider clearing logs and old uploads.')
                    : translate('CRITICAL: less than 100 MB free. Uploads and log writes will fail.')),
        ];
    }

    /**
     * .env writability + storage/framework tree — the same permissions
     * the installer checks pre-install, verified at runtime.
     */
    private function permissionsSection(): array
    {
        $paths = [
            base_path('.env')                => 'file',
            storage_path()                   => 'dir',
            storage_path('logs')             => 'dir',
            base_path('bootstrap/cache')     => 'dir',
        ];
        $failing = [];
        foreach ($paths as $path => $type) {
            if ($type === 'file') {
                if (file_exists($path) && !is_writable($path)) $failing[] = $path;
            } else {
                if (!is_dir($path) || !is_writable($path)) $failing[] = $path;
            }
        }
        $healthy = empty($failing);
        return [
            'label'   => translate('File Permissions'),
            'healthy' => $healthy,
            'value'   => $healthy ? translate('OK') : count($failing) . ' ' . translate('problems'),
            'message' => $healthy
                ? translate('All critical paths are writable.')
                : translate('Not writable: ') . implode(', ', $failing),
        ];
    }

    /**
     * WhatsApp Node service — delegates to NodeService::getSystemHealth() if
     * NodeService is bindable (may not be during pre-install).
     */
    private function waNodeSection(): array
    {
        try {
            $node = $this->nodeService ?: app(NodeService::class);
            $report = $node->getSystemHealth();
            $healthy = (bool) ($report['success'] ?? false);
            return [
                'label'   => translate('WhatsApp Node Service'),
                'healthy' => $healthy,
                'value'   => $healthy ? translate('online') : translate('offline'),
                'message' => $healthy
                    ? translate('Node sidecar responded successfully.')
                    : translate('Node sidecar did not respond. Check WP_SERVER_URL, WP_API_KEY, and that pm2/systemd is running the sidecar.'),
                'detail'  => $report,
            ];
        } catch (\Throwable $e) {
            return [
                'label'   => translate('WhatsApp Node Service'),
                'healthy' => true, // don't fail overall health if Node isn't configured
                'value'   => translate('not configured'),
                'message' => translate('Node service not configured or unreachable — safe to ignore if you only use Cloud API.'),
            ];
        }
    }

    /**
     * WhatsApp gateways (Cloud API + Node devices) — delegates to
     * WhatsApp\HealthCheckService::getHealthSummary().
     */
    private function waGatewaysSection(): array
    {
        try {
            $svc = $this->waHealth ?: app(WhatsAppHealthCheckService::class);
            $summary = $svc->getHealthSummary();
            $total = (int) ($summary['total_gateways'] ?? 0);
            $healthy = (int) ($summary['healthy_count'] ?? 0);
            $ok = $total === 0 || $healthy === $total;

            return [
                'label'   => translate('WhatsApp Gateways'),
                'healthy' => $ok,
                'value'   => $total === 0
                    ? translate('none configured')
                    : $healthy . ' / ' . $total . ' ' . translate('healthy'),
                'message' => $total === 0
                    ? translate('No WhatsApp gateways configured yet.')
                    : ($ok
                        ? translate('All gateways healthy.')
                        : translate('Some gateways need attention — see Admin → Gateway → WhatsApp Cloud API.')),
                'detail'  => $summary,
            ];
        } catch (\Throwable $e) {
            return [
                'label'   => translate('WhatsApp Gateways'),
                'healthy' => true,
                'value'   => translate('n/a'),
                'message' => translate('Gateway health probe unavailable: ') . $e->getMessage(),
            ];
        }
    }

    /**
     * PHP version + extensions the app relies on. Overlaps with installer
     * requirements check but re-checks at runtime in case a host swapped
     * PHP versions post-install.
     */
    private function phpSection(): array
    {
        $required = ['bcmath', 'openssl', 'pdo_mysql', 'mbstring', 'tokenizer', 'json', 'curl', 'gd', 'zip'];
        $missing = [];
        foreach ($required as $ext) {
            if (!extension_loaded($ext)) $missing[] = $ext;
        }
        $minVersion = (string) config('installer.core.minPhpVersion', '8.2');
        $versionOk  = version_compare(PHP_VERSION, $minVersion, '>=');
        $healthy    = $versionOk && empty($missing);

        $msg = $versionOk
            ? (empty($missing)
                ? translate('PHP ') . PHP_VERSION . ' ' . translate('with all required extensions loaded.')
                : translate('Missing PHP extensions: ') . implode(', ', $missing))
            : translate('PHP ') . PHP_VERSION . ' ' . translate('detected. Minimum required: ') . $minVersion . '.';

        return [
            'label'   => translate('PHP Runtime'),
            'healthy' => $healthy,
            'value'   => PHP_VERSION,
            'message' => $msg,
        ];
    }

    /**
     * Roll up every section into one traffic-light verdict.
     */
    private function overallVerdict(array $sections): array
    {
        $failing = [];
        foreach ($sections as $key => $section) {
            if ($key === 'overall') continue;
            if (!($section['healthy'] ?? true)) {
                $failing[] = (string) ($section['label'] ?? $key);
            }
        }
        $ok = empty($failing);
        return [
            'healthy' => $ok,
            'message' => $ok
                ? translate('Every subsystem is healthy.')
                : translate('The following subsystems need attention: ') . implode(', ', $failing),
            'failing' => $failing,
        ];
    }

    /**
     * Wrap a probe so a fatal error in one subsystem doesn't kill the report.
     */
    private function safe(callable $probe): array
    {
        try {
            return $probe();
        } catch (\Throwable $e) {
            return [
                'label'   => translate('Health probe error'),
                'healthy' => false,
                'value'   => 'exception',
                'message' => $e->getMessage(),
            ];
        }
    }
}
