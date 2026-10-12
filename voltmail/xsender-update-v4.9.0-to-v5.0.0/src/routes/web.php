<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebController;
use App\Http\Controllers\CronController;
use App\Http\Controllers\MetaController;
use App\Http\Controllers\CoreController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\QueueController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\FrontendController;
use App\Http\Controllers\User\HomeController;
use App\Http\Controllers\User\Auth\LoginController;
use App\Http\Controllers\User\Auth\PasswordController;
use App\Http\Controllers\User\Auth\RegisterController;
use App\Http\Controllers\Admin\Core\GlobalWorldController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

/**
 * EMERGENCY CACHE CLEAR ROUTE
 * Use this when the system throws 500 errors after an update
 * Access: /clear-cache?key=YOUR_PURCHASE_KEY
 *
 * This route has NO middleware dependencies to ensure it works
 * even when the application is in a broken state
 */
Route::get('clear-cache', function () {
    // Security: Require purchase key or app key for access
    $providedKey = request()->query('key');
    $purchaseKey = site_settings('purchase_key') ?: env('PURCHASE_KEY', '');
    $appKey = config('app.key', '');

    // Allow access with purchase key, app key, or special emergency key
    $validKeys = array_filter([$purchaseKey, $appKey, 'xsender-emergency-' . date('Y-m-d')]);

    if (empty($providedKey) || !in_array($providedKey, $validKeys)) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized. Please provide valid key as query parameter: ?key=YOUR_PURCHASE_KEY'
        ], 401);
    }

    $results = ['cleared' => [], 'errors' => []];

    // 1. Clear view cache
    try {
        $viewPath = storage_path('framework/views');
        if (is_dir($viewPath)) {
            $files = glob($viewPath . '/*');
            foreach ($files as $file) {
                if (is_file($file) && basename($file) !== '.gitignore') {
                    @unlink($file);
                }
            }
            $results['cleared'][] = 'views';
        }
    } catch (\Exception $e) {
        $results['errors'][] = 'views: ' . $e->getMessage();
    }

    // 2. Clear cache files
    try {
        $cachePath = storage_path('framework/cache/data');
        if (is_dir($cachePath)) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($cachePath, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $file) {
                if ($file->isFile() && $file->getFilename() !== '.gitignore') {
                    @unlink($file->getRealPath());
                } elseif ($file->isDir()) {
                    @rmdir($file->getRealPath());
                }
            }
            $results['cleared'][] = 'cache';
        }
    } catch (\Exception $e) {
        $results['errors'][] = 'cache: ' . $e->getMessage();
    }

    // 3. Clear bootstrap cache
    try {
        $bootstrapPath = base_path('bootstrap/cache');
        $bootstrapFiles = [
            'config.php',
            'routes-v7.php',
            'services.php',
            'packages.php',
            'events.php',
        ];
        foreach ($bootstrapFiles as $file) {
            $filePath = $bootstrapPath . '/' . $file;
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }
        $results['cleared'][] = 'bootstrap';
    } catch (\Exception $e) {
        $results['errors'][] = 'bootstrap: ' . $e->getMessage();
    }

    // 4. Clear OPcache
    try {
        if (function_exists('opcache_reset')) {
            @opcache_reset();
            $results['cleared'][] = 'opcache';
        }
    } catch (\Exception $e) {
        $results['errors'][] = 'opcache: ' . $e->getMessage();
    }

    // 5. Clear Laravel cache
    try {
        Cache::flush();
        $results['cleared'][] = 'laravel_cache';
    } catch (\Exception $e) {
        $results['errors'][] = 'laravel_cache: ' . $e->getMessage();
    }

    // 6. Try Artisan commands
    try {
        Artisan::call('config:clear');
        Artisan::call('cache:clear');
        Artisan::call('view:clear');
        Artisan::call('route:clear');
        $results['cleared'][] = 'artisan';
    } catch (\Exception $e) {
        $results['errors'][] = 'artisan: ' . $e->getMessage();
    }

    // 7. Clear application caches
    try {
        if (function_exists('clear_app_caches')) {
            clear_app_caches();
            $results['cleared'][] = 'app_caches';
        }
    } catch (\Exception $e) {
        $results['errors'][] = 'app_caches: ' . $e->getMessage();
    }

    // 8. Restore critical caches to prevent install/license redirect
    try {
        if (function_exists('restore_critical_caches')) {
            restore_critical_caches();
            $results['cleared'][] = 'critical_caches_restored';
        }
    } catch (\Exception $e) {
        $results['errors'][] = 'restore_caches: ' . $e->getMessage();
    }

    $success = count($results['cleared']) > 0;

    return response()->json([
        'success' => $success,
        'message' => $success ? 'Cache cleared successfully! Please refresh the page.' : 'Cache clearing had issues',
        'details' => $results,
        'next_step' => 'Please refresh your browser or visit the homepage'
    ]);
})->withoutMiddleware(['web', 'auth', 'domain.verified', 'check.domain', 'verified', 'installed'])->name('emergency.cache.clear');

/**
 * SYSTEM SELF-HEAL ROUTE
 * One-click fix for common post-update issues:
 * - Runs pending database migrations
 * - Clears all caches (views, config, routes, opcache)
 * - Verifies critical tables exist
 * - Returns diagnostic report
 *
 * Access: /system/self-heal?key=YOUR_PURCHASE_KEY
 */
Route::get('system/self-heal', function () {
    ini_set('memory_limit', '-1');
    ini_set('max_execution_time', '300');

    // Security: Require purchase key or app key
    $providedKey = request()->query('key');
    $purchaseKey = '';
    $appKey = config('app.key', '');

    try {
        if (\Illuminate\Support\Facades\Schema::hasTable('settings')) {
            $purchaseKey = \Illuminate\Support\Facades\DB::table('settings')
                ->where('key', 'purchase_key')->value('value') ?? '';
        }
    } catch (\Exception $e) {
        // DB not available yet
    }

    if (empty($purchaseKey)) {
        $purchaseKey = env('PURCHASE_KEY', '');
    }

    $validKeys = array_filter([$purchaseKey, $appKey]);

    if (empty($providedKey) || !in_array($providedKey, $validKeys)) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized. Usage: /system/self-heal?key=YOUR_PURCHASE_KEY'
        ], 401);
    }

    $report = [
        'php_version' => PHP_VERSION,
        'code_version' => config('installer.version', 'unknown'),
        'db_version' => null,
        'steps' => [],
    ];

    // Step 1: Check database connection
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        $report['steps'][] = ['step' => 'Database connection', 'status' => 'ok'];
    } catch (\Exception $e) {
        $report['steps'][] = ['step' => 'Database connection', 'status' => 'error', 'message' => $e->getMessage()];
        return response()->json(['success' => false, 'report' => $report]);
    }

    // Step 2: Check migrations table
    $hasMigrationsTable = \Illuminate\Support\Facades\Schema::hasTable('migrations');
    if (!$hasMigrationsTable) {
        $report['steps'][] = ['step' => 'Migrations table', 'status' => 'error', 'message' => 'migrations table missing — run installer first'];
        return response()->json(['success' => false, 'report' => $report]);
    }

    // Step 3: Get DB version
    try {
        $report['db_version'] = \Illuminate\Support\Facades\DB::table('settings')
            ->where('key', 'app_version')->value('value') ?? 'unknown';
    } catch (\Exception $e) {
        $report['db_version'] = 'unknown';
    }

    // Step 4: Run pending migrations
    try {
        $runner = new \App\Services\Core\SafeMigrationRunner();
        $results = $runner->runAllPending();

        $successCount = count($results['success'] ?? []);
        $skippedCount = count($results['skipped'] ?? []);
        $failedCount = count($results['failed'] ?? []);

        $migrationStatus = $failedCount === 0 ? 'ok' : 'warning';
        $report['steps'][] = [
            'step' => 'Database migrations',
            'status' => $migrationStatus,
            'message' => "Success: {$successCount}, Skipped: {$skippedCount}, Failed: {$failedCount}",
            'details' => $results,
        ];
    } catch (\Exception $e) {
        // Fallback: try artisan migrate directly
        try {
            Artisan::call('migrate', ['--force' => true]);
            $report['steps'][] = ['step' => 'Database migrations (fallback)', 'status' => 'ok', 'message' => Artisan::output()];
        } catch (\Exception $e2) {
            $report['steps'][] = ['step' => 'Database migrations', 'status' => 'error', 'message' => $e2->getMessage()];
        }
    }

    // Step 4b: Run seeders from config.json
    try {
        $configJsonPath = dirname(base_path()) . '/config.json';
        if (!file_exists($configJsonPath)) {
            $configJsonPath = base_path('../config.json');
        }

        if (file_exists($configJsonPath)) {
            $configData = json_decode(file_get_contents($configJsonPath), true);
            $seeders = $configData['seeder'] ?? [];
            $dbVersion = $report['db_version'] ?? '1.0';
            $seederResults = [];

            foreach ($seeders as $version => $seederList) {
                if (version_compare((string)$version, (string)$dbVersion, '>')) {
                    if (is_array($seederList)) {
                        foreach ($seederList as $seeder) {
                            try {
                                Artisan::call('db:seed', ['--class' => $seeder, '--force' => true]);
                                $seederResults[] = "{$seeder} (v{$version})";
                            } catch (\Exception $se) {
                                // Don't fail for seeder issues
                                Log::warning("[self-heal] Seeder warning: {$seeder} - " . $se->getMessage());
                            }
                        }
                    }
                }
            }

            if (!empty($seederResults)) {
                $report['steps'][] = ['step' => 'Database seeders', 'status' => 'ok', 'message' => 'Ran: ' . implode(', ', $seederResults)];
            } else {
                $report['steps'][] = ['step' => 'Database seeders', 'status' => 'ok', 'message' => 'No pending seeders'];
            }
        }
    } catch (\Exception $e) {
        $report['steps'][] = ['step' => 'Database seeders', 'status' => 'warning', 'message' => $e->getMessage()];
    }

    // Step 5: Verify critical tables & auto-create missing ones
    $criticalTables = [
        'users', 'admins', 'settings', 'languages', 'gateways', 'templates',
        'contacts', 'contact_groups', 'campaigns', 'campaign_unsubscribes',
        'communication_logs', 'dispatch_logs', 'messages', 'files',
        'pricing_plans', 'subscriptions', 'credit_logs', 'jobs', 'failed_jobs',
    ];
    $missingTables = [];
    foreach ($criticalTables as $table) {
        if (!\Illuminate\Support\Facades\Schema::hasTable($table)) {
            $missingTables[] = $table;
        }
    }

    // Auto-recover: find and run CREATE migrations for missing tables
    if (!empty($missingTables)) {
        $recoveredTables = [];
        $migrationDir = database_path('migrations');
        $migrationFiles = glob($migrationDir . '/*.php');
        sort($migrationFiles);

        try {
            \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=0');
        } catch (\Exception $e) {}

        foreach ($missingTables as $missingTable) {
            // Find the CREATE migration for this table
            foreach ($migrationFiles as $migFile) {
                $basename = basename($migFile);
                // Match pattern: create_{table_name}_table.php
                if (preg_match('/create_' . preg_quote($missingTable, '/') . '_table\.php$/', $basename)) {
                    try {
                        $migrationName = pathinfo($basename, PATHINFO_FILENAME);
                        // Remove from migrations table if wrongly marked
                        \Illuminate\Support\Facades\DB::table('migrations')
                            ->where('migration', $migrationName)->delete();

                        Artisan::call('migrate', [
                            '--path' => 'database/migrations/' . $basename,
                            '--force' => true,
                        ]);
                        $recoveredTables[] = $missingTable;
                        Log::info("[self-heal] Recovered missing table: {$missingTable}");
                    } catch (\Exception $e) {
                        Log::warning("[self-heal] Could not recover table {$missingTable}: " . $e->getMessage());
                    }
                    break;
                }
            }
        }

        try {
            \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=1');
        } catch (\Exception $e) {}

        // Re-check missing tables
        $stillMissing = [];
        foreach ($missingTables as $table) {
            if (!\Illuminate\Support\Facades\Schema::hasTable($table)) {
                $stillMissing[] = $table;
            }
        }

        if (empty($stillMissing)) {
            $report['steps'][] = [
                'step' => 'Critical tables check',
                'status' => 'ok',
                'message' => 'All tables exist. Recovered: ' . implode(', ', $recoveredTables),
            ];
        } else {
            $report['steps'][] = [
                'step' => 'Critical tables check',
                'status' => 'warning',
                'message' => 'Still missing: ' . implode(', ', $stillMissing)
                    . (!empty($recoveredTables) ? '. Recovered: ' . implode(', ', $recoveredTables) : ''),
            ];
        }
    } else {
        $report['steps'][] = ['step' => 'Critical tables check', 'status' => 'ok', 'message' => 'All critical tables exist'];
    }

    // Step 6: Update DB version to match code version
    $codeVersion = config('installer.version', '1.0');
    try {
        if (\Illuminate\Support\Facades\Schema::hasTable('settings')) {
            \Illuminate\Support\Facades\DB::table('settings')->updateOrInsert(
                ['key' => 'app_version'],
                ['value' => $codeVersion, 'updated_at' => now()]
            );
            $report['steps'][] = ['step' => 'Version sync', 'status' => 'ok', 'message' => "DB version updated to {$codeVersion}"];
        }
    } catch (\Exception $e) {
        $report['steps'][] = ['step' => 'Version sync', 'status' => 'error', 'message' => $e->getMessage()];
    }

    // Step 7: Clear all caches
    try {
        // Clear compiled views
        $viewPath = storage_path('framework/views');
        if (is_dir($viewPath)) {
            foreach (glob($viewPath . '/*') as $file) {
                if (is_file($file) && basename($file) !== '.gitignore') @unlink($file);
            }
        }

        // Clear bootstrap cache
        $bootstrapFiles = ['config.php', 'routes-v7.php', 'services.php', 'packages.php', 'events.php'];
        foreach ($bootstrapFiles as $file) {
            $path = base_path('bootstrap/cache/' . $file);
            if (file_exists($path)) @unlink($path);
        }

        // Artisan cache clearing
        try { Artisan::call('config:clear'); } catch (\Exception $e) {}
        try { Artisan::call('cache:clear'); } catch (\Exception $e) {}
        try { Artisan::call('view:clear'); } catch (\Exception $e) {}
        try { Artisan::call('route:clear'); } catch (\Exception $e) {}

        // OPcache
        if (function_exists('opcache_reset')) @opcache_reset();
        clearstatcache(true);

        // Laravel cache flush
        try { Cache::flush(); } catch (\Exception $e) {}

        // Restore critical caches
        if (function_exists('restore_critical_caches')) {
            restore_critical_caches();
        }

        // Remove old migration flags so auto-migrate re-checks
        foreach (glob(storage_path('framework/.db_migrated_*')) as $flag) {
            @unlink($flag);
        }
        // Remove env check flag so env variables get re-checked
        @unlink(storage_path('framework/cache/.env_checked'));

        $report['steps'][] = ['step' => 'Cache clearing', 'status' => 'ok', 'message' => 'All caches cleared'];
    } catch (\Exception $e) {
        $report['steps'][] = ['step' => 'Cache clearing', 'status' => 'warning', 'message' => $e->getMessage()];
    }

    // Step 8: Verify file permissions
    $writableChecks = [
        'storage' => storage_path(),
        'storage/logs' => storage_path('logs'),
        'storage/framework' => storage_path('framework'),
        'bootstrap/cache' => base_path('bootstrap/cache'),
    ];
    $permissionIssues = [];
    foreach ($writableChecks as $label => $path) {
        if (is_dir($path) && !is_writable($path)) {
            $permissionIssues[] = $label;
        }
    }
    if (empty($permissionIssues)) {
        $report['steps'][] = ['step' => 'File permissions', 'status' => 'ok'];
    } else {
        $report['steps'][] = ['step' => 'File permissions', 'status' => 'warning', 'message' => 'Not writable: ' . implode(', ', $permissionIssues)];
    }

    $hasErrors = collect($report['steps'])->contains(fn($s) => $s['status'] === 'error');
    $hasWarnings = collect($report['steps'])->contains(fn($s) => $s['status'] === 'warning');

    $report['overall'] = $hasErrors ? 'error' : ($hasWarnings ? 'warning' : 'ok');
    $report['message'] = match($report['overall']) {
        'ok' => 'System self-heal completed successfully! All issues resolved. Please refresh your browser.',
        'warning' => 'Self-heal completed with warnings. Check the steps above for details.',
        'error' => 'Self-heal completed but some critical issues remain. Please contact support.',
    };

    return response()->json(['success' => !$hasErrors, 'report' => $report]);
})->withoutMiddleware(['web', 'auth', 'domain.verified', 'check.domain', 'verified', 'installed'])->name('system.self-heal');

Route::get('queue-work', [QueueController::class, 'processAllQueues'])
    ->name('queue.work');

Route::get('queue-work/default', [QueueController::class, 'processDefault'])
    ->name('queue.work.default');
Route::get('queue-work/dispatch-logs', [QueueController::class, 'processDispatchlogs'])
    ->name('queue.work.dispatch-logs');
Route::get('queue-work/regular-sms', [QueueController::class, 'processRegularSms'])
    ->name('queue.work.regular-sms');
Route::get('queue-work/regular-email', [QueueController::class, 'processRegularEmail'])
    ->name('queue.work.regular-email');
Route::get('queue-work/regular-whatsapp', [QueueController::class, 'processRegularWhatsapp'])
    ->name('queue.work.regular-whatsapp');
Route::get('queue-work/campaign-sms', [QueueController::class, 'processCampaignSms'])
    ->name('queue.work.campaign-sms');
Route::get('queue-work/campaign-email', [QueueController::class, 'processCampaignEmail'])
    ->name('queue.work.campaign-email');
Route::get('queue-work/campaign-whatsapp', [QueueController::class, 'processCampaignWhatsapp'])
    ->name('queue.work.campaign-whatsapp');
Route::get('queue-work/chat-whatsapp', [QueueController::class, 'processChatWhatsapp'])
    ->name('queue.work.chat-whatsapp');
Route::get('queue-work/import-contacts', [QueueController::class, 'processContactImport'])
    ->name('queue.work.import-contacts');
Route::get('queue-work/verify-email', [QueueController::class, 'processEmailVerify'])
    ->name('queue.work.verify-email');

Route::get('cron/run', [CronController::class, 'run'])->name('cron.run');

// Email Tracking Routes (no auth, minimal middleware)
Route::get('t/o/{token}', [TrackingController::class, 'trackOpen'])->name('email.track.open');
Route::get('t/c/{token}', [TrackingController::class, 'trackClick'])->name('email.track.click');

// Enterprise Automation Routes (Recommended)
Route::prefix('automation')->name('automation.')->group(function () {
    // Main automation endpoint - single URL for all tasks
    Route::get('run', [CronController::class, 'automation'])->name('run');

    // Health check endpoint
    Route::get('health', [CronController::class, 'health'])->name('health');

    // Failed jobs management
    Route::get('retry-failed', [CronController::class, 'retryFailed'])->name('retry-failed');
    Route::get('clear-failed', [CronController::class, 'clearFailed'])->name('clear-failed');
});

// v4.9 — Public status endpoint for external monitoring (UptimeRobot,
// StatusCake, Better Uptime, etc.). Same JSON feed the admin System Health
// page consumes — one aggregator, three consumers.
Route::get('/status', function (\App\Services\System\SystemHealthService $svc) {
    $report = $svc->diagnose();
    $status = ($report['overall']['healthy'] ?? false) ? 200 : 503;
    return response()->json($report, $status);
})->name('public.status');

// v5.0-S2 — Inbound email webhook for the Unified Inbox. Provider-agnostic
// (?provider=sendgrid|mailgun|postmark|generic). Auth is via the HMAC token
// embedded in the Reply-To local-part + optional shared secret query param.
// CSRF disabled here because providers don't send tokens; safety comes from
// the HMAC signature on the Reply-To address.
Route::post('/webhooks/inbound-email', \App\Http\Controllers\Webhook\InboundEmailController::class)
    ->name('webhooks.inbound-email')
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);

// v5.0-S3 — Inbound SMS webhook for the Unified Inbox. Provider-agnostic
// (?provider=twilio|vonage|infobip|messagebird|generic). Auth: Twilio
// signature check for the twilio adapter, optional INBOUND_SMS_SHARED_SECRET
// via ?secret= query for others. CSRF disabled (webhook POST).
Route::post('/webhooks/inbound-sms', \App\Http\Controllers\Webhook\InboundSmsController::class)
    ->name('webhooks.inbound-sms')
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);

Route::middleware([
    'guest',
    'maintenance'
])->group(function () { 

    ## ------------------------------- ##
    ## Authentication Route Declartion ##
    ## ------------------------------- ##

    Route::controller(RegisterController::class)
            ->middleware('registration')
            ->group(function() {

        Route::get('register/{uid?}', 'register')->name('register');
        Route::post('register/{uid?}', 'store')->name('register.store');
    });
 
    Route::middleware('login')
            ->group(function() {
 
        Route::controller(LoginController::class)
                ->group(function() {
            
            Route::get('/login', 'create')->name('login');
            Route::post('login', 'store')->name('login.store');
        });

        Route::controller(PasswordController::class)
                ->name('password.')
                ->group(function() {

            Route::post('forgot-password', 'store')->name('email');
            Route::get('forgot-password', 'create')->name('request');
            Route::get('password/resend/code', 'resendCode')->name('resend.code');
            Route::post('reset-password', 'updatePassword')->name('update');
            Route::get('reset-password/{token}', 'resetPassword')->name('reset');
            Route::get('password/code/verify', 'passwordResetCodeVerify')->name('verify.code');
            Route::post('password/code/verify', 'emailVerificationCode')->name('email.verify.code');
        });
    });

    Route::controller(WebController::class)->middleware(['redirect.to.login'])->group(function () {
        Route::get('/', 'index')->name('home');
        Route::get('service/{type?}', 'service')->name('service');
        Route::get('blog/search', 'blogSearch')->name('blog.search');
        Route::get('blog/{uid?}', 'blog')->name('blog');
        Route::get('about/', 'about')->name('about');
        Route::get('pricing/', 'pricing')->name('pricing');
        Route::get('contact/', 'contact')->name('contact');
        Route::post('contact/', 'getInTouch')->name('contact.get_in_touch');
        Route::get('/pages/{key}/{id}', 'pages')->name('page');
    });
    
    
    Route::controller(FrontendController::class)->group(function() {
    
        Route::get('/default/image/{size}', 'defaultImageCreate')->name('default.image');
        Route::get('email/contact/demo/file', 'demoImportFile')->name('email.contact.demo.import');
        Route::get('sms/demo/import/file', 'demoImportFilesms')->name('phone.book.demo.import.file');
        Route::get('demo/file/download/{extension}/{type}', 'demoFileDownloader')->name('demo.file.download');  
        Route::get('api/document', 'apiDocumentation')->name('api.document');
    });
    
    Route::get('/default-captcha/{randCode}', [HomeController::class, 'defaultCaptcha'])->name('captcha.genarate');
    Route::any('/webhook', [WebhookController::class, 'postWebhook'])->name('webhook');
    Route::any('/facebook/login', [MetaController::class, 'facebookLogin'])->name('facebook.login');

      Route::get('/api/translations/{lang_code}', [GlobalWorldController::class, 'getTranslations'])->name('api.translations');
    Route::get('/language/change/{lang?}', [GlobalWorldController::class, 'languageChange'])->name('language.change');
    
    Route::get('/unsubscribe', [HomeController::class, 'unsubscribe'])->name('unsubscribe');
    // RFC 8058 One-Click POST. Gmail/Yahoo mail servers POST here with body
    // `List-Unsubscribe=One-Click` and expect a 2xx text response, NOT the
    // GET redirect flow. Registered as a distinct handler so CSRF exemption
    // and the plain-text 200 response don't leak into the browser-facing GET.
    Route::post('/unsubscribe', [HomeController::class, 'unsubscribePost'])->name('unsubscribe.post');
    Route::get('/unsubscribe/success', [HomeController::class, 'unsubscribeSuccess'])->name('unsubscribe.success');
    
    Route::get('/domain-unverified', [CoreController::class, 'domainNotVerified'])->name('domain.unverified')->withoutMiddleware(['domain.verified' , 'check.domain']);
    
    Route::post('/check-license', [CoreController::class, 'checkLicense'])->name('check.license.key')->withoutMiddleware(['domain.verified' , 'check.domain']);
});




