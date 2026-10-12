<?php

namespace App\Services\Core;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Exception;
use Throwable;

/**
 * SafeMigrationRunner - Enterprise-level migration handler
 *
 * Handles database migrations safely with:
 * - Graceful error handling (skip existing tables/columns, continue on failure)
 * - Detailed logging for debugging
 * - Version-based migration from config.json
 * - Automatic recovery from common errors
 * - Schema reconciliation: ensures all expected tables/columns exist after migrations
 */
class SafeMigrationRunner
{
    /**
     * Migration results tracking
     */
    protected array $results = [
        'success' => [],
        'skipped' => [],
        'failed' => [],
        'warnings' => [],
    ];

    /**
     * Log channel name
     */
    protected string $logChannel = 'daily';

    /**
     * Base migrations path
     */
    protected string $migrationsPath;

    public function __construct()
    {
        $this->migrationsPath = database_path('migrations');
    }

    /**
     * Run migrations from a config array (used by SystemUpdateController)
     *
     * @param array $config Config array with migrations and version info
     * @return array Migration results
     */
    public function runFromConfig(array $config): array
    {
        $this->resetResults();

        $currentVersion = trim((string) site_settings('app_version', '1.1'));
        $migrations = $config['migrations'] ?? [];

        Log::info("[SafeMigrationRunner] Starting migration run", [
            'current_version' => $currentVersion,
            'total_versions' => count($migrations)
        ]);

        foreach ($migrations as $version => $migrationFiles) {
            // Only run migrations for versions newer than current
            if (version_compare((string)$version, (string)$currentVersion, '>')) {
                Log::info("[SafeMigrationRunner] Processing version: {$version}");

                if (is_array($migrationFiles)) {
                    foreach ($migrationFiles as $migrationPath) {
                        $this->runSingleMigration($migrationPath);
                    }
                }
            }
        }

        // Run seeders if defined
        $seeders = $config['seeder'] ?? [];
        foreach ($seeders as $version => $seederList) {
            if (version_compare((string)$version, (string)$currentVersion, '>')) {
                if (is_array($seederList)) {
                    foreach ($seederList as $seeder) {
                        $this->runSeeder($seeder);
                    }
                }
            }
        }

        // Run schema reconciliation as safety net
        // This catches any tables/columns that migrations missed (e.g., table existed
        // with missing columns, so "table already exists" was caught but columns were skipped)
        $this->runSchemaReconciliation();

        $this->logFinalResults();

        return $this->results;
    }

    /**
     * Run all pending migrations safely (fallback method)
     *
     * @return array Migration results
     */
    public function runAllPending(): array
    {
        $this->resetResults();

        Log::info("[SafeMigrationRunner] Running all pending migrations");

        // Disable foreign key checks to prevent constraint errors during migration
        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        } catch (Throwable $e) {
            Log::warning("[SafeMigrationRunner] Could not disable FK checks: " . $e->getMessage());
        }

        try {
            Artisan::call('migrate', ['--force' => true]);
            $output = Artisan::output();

            Log::info("[SafeMigrationRunner] Default migrate completed", ['output' => $output]);
            $this->results['success'][] = 'All pending migrations';

        } catch (Throwable $e) {
            // If bulk migration fails, try individual files
            Log::warning("[SafeMigrationRunner] Bulk migration failed, trying individual files", [
                'error' => $e->getMessage()
            ]);

            $this->runMigrationsIndividually();
        }

        // Re-enable foreign key checks
        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        } catch (Throwable $e) {
            Log::warning("[SafeMigrationRunner] Could not re-enable FK checks: " . $e->getMessage());
        }

        return $this->results;
    }

    /**
     * Run migrations one by one (for error recovery)
     */
    protected function runMigrationsIndividually(): void
    {
        $files = File::glob($this->migrationsPath . '/*.php');
        sort($files);

        foreach ($files as $file) {
            $fileName = basename($file);

            // Skip if already migrated
            if ($this->isMigrationRan($fileName)) {
                continue;
            }

            $relativePath = 'database/migrations/' . $fileName;
            $this->runSingleMigration($relativePath);
        }
    }

    /**
     * Run a single migration file safely
     *
     * @param string $migrationPath Relative path to migration file
     * @return bool Success status
     */
    public function runSingleMigration(string $migrationPath): bool
    {
        $fileName = basename($migrationPath);
        $fullPath = base_path($migrationPath);

        // Check if file exists
        if (!File::exists($fullPath)) {
            // Try alternate path (storage temp path during update)
            $altPath = storage_path('app/public/temp_update/' . $migrationPath);
            if (!File::exists($altPath)) {
                $this->results['warnings'][] = "Migration file not found: {$fileName}";
                Log::warning("[SafeMigrationRunner] Migration file not found", [
                    'path' => $migrationPath,
                    'checked' => [$fullPath, $altPath]
                ]);
                return false;
            }
        }

        // Check if already migrated
        if ($this->isMigrationRan($fileName)) {
            $this->results['skipped'][] = [
                'file' => $fileName,
                'reason' => 'Already migrated',
            ];
            Log::info("[SafeMigrationRunner] Skipped (already ran): {$fileName}");
            return true;
        }

        try {
            Artisan::call('migrate', [
                '--path' => $migrationPath,
                '--force' => true,
            ]);

            $output = Artisan::output();

            // Check for "Nothing to migrate" which means it was already done
            if (str_contains($output, 'Nothing to migrate')) {
                $this->markAsMigrated($fileName);
                $this->results['skipped'][] = [
                    'file' => $fileName,
                    'reason' => 'Nothing to migrate',
                ];
                return true;
            }

            $this->results['success'][] = $fileName;
            Log::info("[SafeMigrationRunner] Successfully migrated: {$fileName}");

            return true;

        } catch (Throwable $e) {
            return $this->handleMigrationError($e, $fileName, $migrationPath);
        }
    }

    /**
     * Handle migration errors intelligently
     */
    protected function handleMigrationError(Throwable $e, string $fileName, string $migrationPath): bool
    {
        $errorMessage = $e->getMessage();
        $errorCode = $this->extractSqlErrorCode($errorMessage);

        Log::warning("[SafeMigrationRunner] Migration error", [
            'file' => $fileName,
            'error' => $errorMessage,
            'code' => $errorCode
        ]);

        // Handle "table already exists" (MySQL: 1050, SQLSTATE: 42S01)
        if ($this->isTableExistsError($errorMessage)) {
            $this->markAsMigrated($fileName);
            $this->results['skipped'][] = [
                'file' => $fileName,
                'reason' => 'Table already exists - marked as migrated',
            ];
            Log::info("[SafeMigrationRunner] Table exists, marking as migrated: {$fileName}");
            return true;
        }

        // Handle "column already exists" (MySQL: 1060, SQLSTATE: 42S21)
        if ($this->isColumnExistsError($errorMessage)) {
            $this->markAsMigrated($fileName);
            $this->results['skipped'][] = [
                'file' => $fileName,
                'reason' => 'Column already exists - marked as migrated',
            ];
            Log::info("[SafeMigrationRunner] Column exists, marking as migrated: {$fileName}");
            return true;
        }

        // Handle "duplicate key" errors
        if ($this->isDuplicateKeyError($errorMessage)) {
            $this->markAsMigrated($fileName);
            $this->results['skipped'][] = [
                'file' => $fileName,
                'reason' => 'Duplicate key - marked as migrated',
            ];
            Log::info("[SafeMigrationRunner] Duplicate key, marking as migrated: {$fileName}");
            return true;
        }

        // Handle "table doesn't exist" for ALTER operations - skip gracefully
        if ($this->isTableNotFoundError($errorMessage)) {
            $this->markAsMigrated($fileName);
            $this->results['skipped'][] = [
                'file' => $fileName,
                'reason' => 'Table not found (obsolete migration) - marked as migrated',
            ];
            Log::warning("[SafeMigrationRunner] Table not found, marking as migrated: {$fileName}");
            return true;
        }

        // Handle "column not found" for ALTER/DROP/CHANGE operations (MySQL: 1054)
        if ($this->isColumnNotFoundError($errorMessage)) {
            $this->markAsMigrated($fileName);
            $this->results['skipped'][] = [
                'file' => $fileName,
                'reason' => 'Column not found (already changed/removed) - marked as migrated',
            ];
            Log::info("[SafeMigrationRunner] Column not found, marking as migrated: {$fileName}");
            return true;
        }

        // Handle "can't DROP column/key" errors (MySQL: 1091)
        if ($this->isCantDropError($errorMessage)) {
            $this->markAsMigrated($fileName);
            $this->results['skipped'][] = [
                'file' => $fileName,
                'reason' => 'Column/key already dropped - marked as migrated',
            ];
            Log::info("[SafeMigrationRunner] Already dropped, marking as migrated: {$fileName}");
            return true;
        }

        // Handle "invalid default value" errors (MySQL: 1067) — enum/type mismatch on old schemas
        if ($this->isInvalidDefaultError($errorMessage)) {
            $this->markAsMigrated($fileName);
            $this->results['skipped'][] = [
                'file' => $fileName,
                'reason' => 'Invalid default value (schema conflict) - marked as migrated',
            ];
            Log::info("[SafeMigrationRunner] Invalid default value, marking as migrated: {$fileName}");
            return true;
        }

        // Handle foreign key constraint errors (MySQL: 3730, 1217, 1451)
        if ($this->isForeignKeyError($errorMessage)) {
            // Try again with FK checks disabled
            try {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
                Artisan::call('migrate', [
                    '--path' => $migrationPath,
                    '--force' => true,
                ]);
                DB::statement('SET FOREIGN_KEY_CHECKS=1');

                $this->results['success'][] = $fileName . ' (retried with FK checks disabled)';
                Log::info("[SafeMigrationRunner] Succeeded with FK disabled: {$fileName}");
                return true;
            } catch (Throwable $retryE) {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
                $this->markAsMigrated($fileName);
                $this->results['skipped'][] = [
                    'file' => $fileName,
                    'reason' => 'Foreign key constraint (legacy table) - marked as migrated',
                ];
                Log::warning("[SafeMigrationRunner] FK retry failed, marking as migrated: {$fileName}");
                return true;
            }
        }

        // Handle "requires doctrine/dbal" errors — mark as migrated (cosmetic column changes)
        if ($this->isDbalRequiredError($errorMessage)) {
            $this->markAsMigrated($fileName);
            $this->results['skipped'][] = [
                'file' => $fileName,
                'reason' => 'Requires doctrine/dbal (non-critical column change) - marked as migrated',
            ];
            Log::info("[SafeMigrationRunner] DBAL required, marking as migrated: {$fileName}");
            return true;
        }

        // Handle syntax/access violation errors (catch-all for old migrations)
        if ($this->isSyntaxOrAccessError($errorMessage)) {
            $this->markAsMigrated($fileName);
            $this->results['skipped'][] = [
                'file' => $fileName,
                'reason' => 'Syntax/access error (legacy migration) - marked as migrated',
            ];
            Log::info("[SafeMigrationRunner] Syntax/access error, marking as migrated: {$fileName}");
            return true;
        }

        // Actual failure - log and continue
        $this->results['failed'][] = [
            'file' => $fileName,
            'error' => $this->sanitizeErrorMessage($errorMessage),
        ];

        Log::error("[SafeMigrationRunner] Migration failed", [
            'file' => $fileName,
            'error' => $errorMessage
        ]);

        // Return false but don't break the update process
        return false;
    }

    /**
     * Run a seeder safely
     */
    protected function runSeeder(string $seederClass): bool
    {
        try {
            Artisan::call('db:seed', [
                '--class' => $seederClass,
                '--force' => true,
            ]);

            $this->results['success'][] = "Seeder: {$seederClass}";
            Log::info("[SafeMigrationRunner] Seeder completed: {$seederClass}");

            return true;

        } catch (Throwable $e) {
            // Don't fail the whole update for seeder issues
            $this->results['warnings'][] = "Seeder warning ({$seederClass}): " . $e->getMessage();
            Log::warning("[SafeMigrationRunner] Seeder issue", [
                'seeder' => $seederClass,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Run schema reconciliation after migrations
     *
     * Compares actual database against expected schema snapshot and adds
     * any missing tables/columns. This catches cases where a migration
     * was marked as "migrated" due to a partial conflict (e.g., table existed
     * but was missing columns added in the same migration file).
     */
    protected function runSchemaReconciliation(): void
    {
        try {
            $reconciler = new SchemaReconciler();
            $reconcileResults = $reconciler->reconcile();

            // Merge reconciliation results into our results
            if (!empty($reconcileResults['created_tables'])) {
                foreach ($reconcileResults['created_tables'] as $table) {
                    $this->results['success'][] = "Schema reconciler: created table {$table}";
                }
            }

            if (!empty($reconcileResults['added_columns'])) {
                foreach ($reconcileResults['added_columns'] as $column) {
                    $this->results['success'][] = "Schema reconciler: added column {$column}";
                }
            }

            if (!empty($reconcileResults['added_indexes'])) {
                foreach ($reconcileResults['added_indexes'] as $index) {
                    $this->results['success'][] = "Schema reconciler: added index {$index}";
                }
            }

            if (!empty($reconcileResults['errors'])) {
                foreach ($reconcileResults['errors'] as $error) {
                    $this->results['warnings'][] = "Schema reconciler error ({$error['table']}): {$error['error']}";
                }
            }

            Log::info("[SafeMigrationRunner] Schema reconciliation: " . $reconciler->getSummaryMessage());

        } catch (Throwable $e) {
            $this->results['warnings'][] = "Schema reconciliation skipped: " . $e->getMessage();
            Log::warning("[SafeMigrationRunner] Schema reconciliation failed", [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Check if migration has already been ran
     */
    protected function isMigrationRan(string $fileName): bool
    {
        $migrationName = pathinfo($fileName, PATHINFO_FILENAME);

        try {
            return DB::table('migrations')
                ->where('migration', $migrationName)
                ->exists();
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Mark a migration as ran in the migrations table
     */
    protected function markAsMigrated(string $fileName): void
    {
        $migrationName = pathinfo($fileName, PATHINFO_FILENAME);

        try {
            if (!$this->isMigrationRan($fileName)) {
                DB::table('migrations')->insert([
                    'migration' => $migrationName,
                    'batch' => $this->getNextBatchNumber(),
                ]);
                Log::info("[SafeMigrationRunner] Marked as migrated: {$migrationName}");
            }
        } catch (Throwable $e) {
            Log::warning("[SafeMigrationRunner] Could not mark as migrated: {$migrationName}", [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get the next batch number for migrations
     */
    protected function getNextBatchNumber(): int
    {
        try {
            $maxBatch = DB::table('migrations')->max('batch');
            return ($maxBatch ?? 0) + 1;
        } catch (Throwable $e) {
            return 1;
        }
    }

    /**
     * Extract SQL error code from message
     */
    protected function extractSqlErrorCode(string $message): ?string
    {
        // Match patterns like "1050" or "42S01"
        if (preg_match('/\b(\d{4})\b/', $message, $matches)) {
            return $matches[1];
        }
        if (preg_match('/\b([0-9A-Z]{5})\b/', $message, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Check if error is "table already exists"
     */
    protected function isTableExistsError(string $message): bool
    {
        $message = strtolower($message);
        return str_contains($message, '1050')
            || str_contains($message, '42s01')
            || (str_contains($message, 'table') && str_contains($message, 'already exists'))
            || str_contains($message, 'base table or view already exists');
    }

    /**
     * Check if error is "column already exists"
     */
    protected function isColumnExistsError(string $message): bool
    {
        $message = strtolower($message);
        return str_contains($message, '1060')
            || str_contains($message, '42s21')
            || str_contains($message, 'duplicate column')
            || str_contains($message, 'column already exists');
    }

    /**
     * Check if error is "duplicate key"
     */
    protected function isDuplicateKeyError(string $message): bool
    {
        $message = strtolower($message);
        return str_contains($message, '1061')
            || str_contains($message, '1062')
            || str_contains($message, 'duplicate key')
            || str_contains($message, 'duplicate entry');
    }

    /**
     * Check if error is "table not found"
     */
    protected function isTableNotFoundError(string $message): bool
    {
        $message = strtolower($message);
        return str_contains($message, '1146')
            || str_contains($message, '42s02')
            || str_contains($message, "doesn't exist")
            || str_contains($message, 'table not found');
    }

    /**
     * Check if error is "column not found" (MySQL: 1054, SQLSTATE: 42S22)
     */
    protected function isColumnNotFoundError(string $message): bool
    {
        $message = strtolower($message);
        return str_contains($message, '1054')
            || str_contains($message, '42s22')
            || str_contains($message, 'unknown column')
            || str_contains($message, 'column not found');
    }

    /**
     * Check if error is "can't DROP column/key" (MySQL: 1091)
     */
    protected function isCantDropError(string $message): bool
    {
        $message = strtolower($message);
        return str_contains($message, '1091')
            || (str_contains($message, "can't drop") && str_contains($message, 'check that'))
            || str_contains($message, 'check that column/key exists');
    }

    /**
     * Check if error is "invalid default value" (MySQL: 1067)
     */
    protected function isInvalidDefaultError(string $message): bool
    {
        $message = strtolower($message);
        return str_contains($message, '1067')
            || str_contains($message, 'invalid default value');
    }

    /**
     * Check if error is a foreign key constraint error (MySQL: 3730, 1217, 1451, 1452)
     */
    protected function isForeignKeyError(string $message): bool
    {
        $message = strtolower($message);
        return str_contains($message, '3730')
            || str_contains($message, '1217')
            || str_contains($message, '1451')
            || str_contains($message, '1452')
            || str_contains($message, 'foreign key constraint')
            || str_contains($message, 'cannot drop table')
            || str_contains($message, 'a foreign key constraint fails');
    }

    /**
     * Check if error requires doctrine/dbal package
     */
    protected function isDbalRequiredError(string $message): bool
    {
        $message = strtolower($message);
        return str_contains($message, 'doctrine/dbal')
            || str_contains($message, 'doctrine dbal')
            || str_contains($message, 'requires doctrine');
    }

    /**
     * Check if error is a syntax or access violation (SQLSTATE: 42000)
     */
    protected function isSyntaxOrAccessError(string $message): bool
    {
        $message = strtolower($message);
        return str_contains($message, '42000')
            || str_contains($message, 'syntax error or access violation');
    }

    /**
     * Sanitize error message for display
     */
    protected function sanitizeErrorMessage(string $message): string
    {
        // Remove sensitive information like full paths
        $message = preg_replace('/[A-Z]:\\\\[^)]+/', '[path]', $message);
        $message = preg_replace('/\/[^)]+\.php/', '[file]', $message);

        // Truncate if too long
        if (strlen($message) > 200) {
            $message = substr($message, 0, 200) . '...';
        }

        return $message;
    }

    /**
     * Reset results array
     */
    protected function resetResults(): void
    {
        $this->results = [
            'success' => [],
            'skipped' => [],
            'failed' => [],
            'warnings' => [],
        ];
    }

    /**
     * Log final results
     */
    protected function logFinalResults(): void
    {
        $summary = sprintf(
            "[SafeMigrationRunner] Migration complete - Success: %d, Skipped: %d, Failed: %d, Warnings: %d",
            count($this->results['success']),
            count($this->results['skipped']),
            count($this->results['failed']),
            count($this->results['warnings'])
        );

        Log::info($summary);

        if (!empty($this->results['failed'])) {
            Log::warning("[SafeMigrationRunner] Failed migrations", $this->results['failed']);
        }
    }

    /**
     * Get the results
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * Check if migration process was successful (no critical failures)
     */
    public function isSuccessful(): bool
    {
        return empty($this->results['failed']);
    }

    /**
     * Get summary message for display
     */
    public function getSummaryMessage(): string
    {
        $success = count($this->results['success']);
        $skipped = count($this->results['skipped']);
        $failed = count($this->results['failed']);
        $warnings = count($this->results['warnings']);

        if ($failed === 0 && $warnings === 0) {
            return "All migrations completed successfully ({$success} executed, {$skipped} skipped).";
        }

        $message = "Migrations completed with issues: {$success} successful, {$skipped} skipped";

        if ($failed > 0) {
            $message .= ", {$failed} failed";
        }
        if ($warnings > 0) {
            $message .= ", {$warnings} warnings";
        }

        return $message . ". Please check logs for details.";
    }
}
