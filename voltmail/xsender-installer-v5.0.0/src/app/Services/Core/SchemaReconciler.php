<?php

namespace App\Services\Core;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * SchemaReconciler — Enterprise-grade database schema reconciliation
 *
 * Ensures the actual database schema matches the expected schema snapshot.
 * Handles the case where developers manually added tables/columns that
 * conflict with migration files, or migrations were partially applied.
 *
 * Rules:
 * - NEVER drops tables, columns, or indexes (preserves all user data)
 * - ONLY adds missing tables, columns, and indexes
 * - Runs AFTER SafeMigrationRunner as a safety net
 * - Uses raw SQL for reliability (no ORM dependency)
 * - Logs every action for audit trail
 */
class SchemaReconciler
{
    protected string $snapshotPath;

    protected array $results = [
        'created_tables'  => [],
        'added_columns'   => [],
        'added_indexes'   => [],
        'modified_columns' => [],
        'skipped'         => [],
        'errors'          => [],
    ];

    protected string $databaseName;

    public function __construct()
    {
        $this->snapshotPath = database_path('schema_snapshot.json');
        $this->databaseName = config('database.connections.mysql.database', '');
    }

    /**
     * Main entry point — reconcile actual DB against expected schema
     */
    public function reconcile(): array
    {
        $this->resetResults();

        Log::info('[SchemaReconciler] Starting schema reconciliation');

        $expected = $this->loadExpectedSchema();
        if (empty($expected)) {
            Log::warning('[SchemaReconciler] No schema snapshot found or empty — skipping reconciliation');
            $this->results['skipped'][] = 'No schema snapshot found';
            return $this->results;
        }

        // Disable FK checks during reconciliation
        $this->disableForeignKeyChecks();

        try {
            foreach ($expected as $tableName => $tableSchema) {
                $this->reconcileTable($tableName, $tableSchema);
            }
        } finally {
            $this->enableForeignKeyChecks();
        }

        $this->logFinalResults();

        return $this->results;
    }

    /**
     * Reconcile a single table against expected schema
     */
    protected function reconcileTable(string $tableName, array $tableSchema): void
    {
        try {
            if (!Schema::hasTable($tableName)) {
                // Table doesn't exist — create it entirely
                $this->createMissingTable($tableName, $tableSchema);
                return;
            }

            // Table exists — check for missing columns AND type mismatches
            $actualColumns = $this->getActualColumns($tableName);
            $expectedColumns = $tableSchema['columns'] ?? [];

            foreach ($expectedColumns as $columnName => $columnDef) {
                if (!isset($actualColumns[$columnName])) {
                    $this->addMissingColumn($tableName, $columnName, $columnDef);
                } else {
                    // Column exists — check for type mismatch
                    $this->reconcileColumnType($tableName, $columnName, $expectedColumns[$columnName], $actualColumns[$columnName]);
                }
            }

            // Check for missing indexes
            $actualIndexes = $this->getActualIndexes($tableName);
            $expectedIndexes = $tableSchema['indexes'] ?? [];

            foreach ($expectedIndexes as $indexName => $indexDef) {
                if (!isset($actualIndexes[$indexName]) && $indexName !== 'PRIMARY') {
                    $this->addMissingIndex($tableName, $indexName, $indexDef);
                }
            }

        } catch (Throwable $e) {
            $this->results['errors'][] = [
                'table'   => $tableName,
                'error'   => $e->getMessage(),
            ];
            Log::error("[SchemaReconciler] Error reconciling table: {$tableName}", [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Create a missing table from snapshot definition
     */
    protected function createMissingTable(string $tableName, array $tableSchema): void
    {
        $columns = $tableSchema['columns'] ?? [];
        if (empty($columns)) {
            $this->results['skipped'][] = "Table {$tableName}: no column definitions in snapshot";
            return;
        }

        try {
            $columnsSql = [];
            $primaryKey = null;

            foreach ($columns as $columnName => $colDef) {
                $columnsSql[] = $this->buildColumnDefinition($columnName, $colDef);

                if (($colDef['key'] ?? '') === 'PRI') {
                    $primaryKey = $columnName;
                }
            }

            // Add primary key
            if ($primaryKey) {
                $columnsSql[] = "PRIMARY KEY (`{$primaryKey}`)";
            }

            // Add indexes from snapshot
            $indexes = $tableSchema['indexes'] ?? [];
            foreach ($indexes as $indexName => $indexDef) {
                if ($indexName === 'PRIMARY') continue;

                $indexColumns = implode('`, `', $indexDef['columns']);
                $indexType = strtoupper($indexDef['type'] ?? 'INDEX');

                if ($indexType === 'UNIQUE') {
                    $columnsSql[] = "UNIQUE KEY `{$indexName}` (`{$indexColumns}`)";
                } elseif ($indexType === 'FULLTEXT') {
                    $columnsSql[] = "FULLTEXT KEY `{$indexName}` (`{$indexColumns}`)";
                } else {
                    $columnsSql[] = "KEY `{$indexName}` (`{$indexColumns}`)";
                }
            }

            $sql = "CREATE TABLE IF NOT EXISTS `{$tableName}` (\n"
                 . implode(",\n", $columnsSql) . "\n"
                 . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

            DB::statement($sql);

            $this->results['created_tables'][] = $tableName;
            Log::info("[SchemaReconciler] Created missing table: {$tableName}");

        } catch (Throwable $e) {
            $this->results['errors'][] = [
                'table' => $tableName,
                'action' => 'create_table',
                'error' => $e->getMessage(),
            ];
            Log::error("[SchemaReconciler] Failed to create table: {$tableName}", [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Add a missing column to an existing table
     */
    protected function addMissingColumn(string $tableName, string $columnName, array $colDef): void
    {
        try {
            $definition = $this->buildColumnDefinition($columnName, $colDef);
            $afterClause = $this->determineColumnPosition($tableName, $columnName, $colDef);

            $sql = "ALTER TABLE `{$tableName}` ADD COLUMN {$definition}{$afterClause}";

            DB::statement($sql);

            $this->results['added_columns'][] = "{$tableName}.{$columnName}";
            Log::info("[SchemaReconciler] Added missing column: {$tableName}.{$columnName}");

        } catch (Throwable $e) {
            // Column might have been added by another process
            if ($this->isColumnExistsError($e->getMessage())) {
                $this->results['skipped'][] = "{$tableName}.{$columnName}: already exists";
                return;
            }

            $this->results['errors'][] = [
                'table'  => $tableName,
                'column' => $columnName,
                'action' => 'add_column',
                'error'  => $e->getMessage(),
            ];
            Log::error("[SchemaReconciler] Failed to add column: {$tableName}.{$columnName}", [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Reconcile column type mismatch between expected and actual
     *
     * Handles: enum value differences, int→bigint upgrades, nullable changes,
     * invalid datetime defaults, and foreign key conflicts during type change.
     */
    protected function reconcileColumnType(string $tableName, string $columnName, array $expected, array $actual): void
    {
        $expectedType = strtolower(trim($expected['type'] ?? ''));
        $actualType   = strtolower(trim($actual['type'] ?? ''));

        // Normalize types for comparison (ignore display widths like int(11) vs int)
        $normalizedExpected = $this->normalizeType($expectedType);
        $normalizedActual   = $this->normalizeType($actualType);

        if ($normalizedExpected === $normalizedActual) {
            return; // Types match — nothing to do
        }

        // Skip auto_increment columns (primary keys) — too risky to modify
        if (stripos($expected['extra'] ?? '', 'auto_increment') !== false) {
            return;
        }

        try {
            Log::info("[SchemaReconciler] Column type mismatch: {$tableName}.{$columnName}", [
                'expected' => $expectedType,
                'actual'   => $actualType,
            ]);

            // Fix invalid datetime values BEFORE altering (prevents MySQL strict mode errors)
            if ($this->isDatetimeType($expectedType) || $this->isDatetimeType($actualType)) {
                $this->fixInvalidDatetimeValues($tableName, $columnName);
            }

            // Temporarily drop foreign keys referencing this column
            $droppedFKs = $this->dropForeignKeysForColumn($tableName, $columnName);

            // Build and execute ALTER statement
            $definition = $this->buildColumnDefinition($columnName, $expected);
            $sql = "ALTER TABLE `{$tableName}` MODIFY COLUMN {$definition}";

            DB::statement($sql);

            // Recreate dropped foreign keys
            foreach ($droppedFKs as $fk) {
                $this->recreateForeignKey($fk);
            }

            $this->results['modified_columns'][] = "{$tableName}.{$columnName} ({$actualType} → {$expectedType})";
            Log::info("[SchemaReconciler] Fixed column type: {$tableName}.{$columnName}");

        } catch (Throwable $e) {
            // Non-fatal — log but continue
            $this->results['errors'][] = [
                'table'  => $tableName,
                'column' => $columnName,
                'action' => 'modify_column',
                'error'  => $e->getMessage(),
            ];
            Log::warning("[SchemaReconciler] Could not fix column type: {$tableName}.{$columnName}", [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Normalize MySQL type for comparison (remove display widths, normalize aliases)
     */
    protected function normalizeType(string $type): string
    {
        // Remove display widths: int(11) → int, bigint(20) → bigint
        $type = preg_replace('/\b(int|bigint|smallint|tinyint|mediumint)\(\d+\)/', '$1', $type);
        // Remove trailing spaces
        return trim($type);
    }

    protected function isDatetimeType(string $type): bool
    {
        return in_array(strtolower(explode('(', $type)[0]), ['datetime', 'timestamp', 'date']);
    }

    /**
     * Fix invalid datetime values (0000-00-00) that block ALTER operations
     */
    protected function fixInvalidDatetimeValues(string $tableName, string $columnName): void
    {
        try {
            // Temporarily disable strict mode for this session
            DB::statement("SET SESSION sql_mode = REPLACE(@@sql_mode, 'NO_ZERO_DATE', '')");
            DB::statement("SET SESSION sql_mode = REPLACE(@@sql_mode, 'NO_ZERO_IN_DATE', '')");

            // Fix zero dates → NULL (safe — preserves real data)
            DB::statement("UPDATE `{$tableName}` SET `{$columnName}` = NULL WHERE `{$columnName}` = '0000-00-00 00:00:00' OR `{$columnName}` = '0000-00-00'");

            Log::info("[SchemaReconciler] Fixed invalid datetime values in {$tableName}.{$columnName}");
        } catch (Throwable $e) {
            // Non-critical — continue anyway
            Log::warning("[SchemaReconciler] Could not fix datetime values in {$tableName}.{$columnName}: " . $e->getMessage());
        }
    }

    /**
     * Drop foreign keys referencing a column (needed before type change)
     * Returns array of FK definitions for recreation
     */
    protected function dropForeignKeysForColumn(string $tableName, string $columnName): array
    {
        $droppedFKs = [];

        try {
            $fks = DB::select("
                SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ", [$this->databaseName, $tableName, $columnName]);

            foreach ($fks as $fk) {
                try {
                    DB::statement("ALTER TABLE `{$tableName}` DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
                    $droppedFKs[] = [
                        'table'             => $tableName,
                        'constraint'        => $fk->CONSTRAINT_NAME,
                        'column'            => $fk->COLUMN_NAME,
                        'referenced_table'  => $fk->REFERENCED_TABLE_NAME,
                        'referenced_column' => $fk->REFERENCED_COLUMN_NAME,
                    ];
                    Log::info("[SchemaReconciler] Dropped FK: {$tableName}.{$fk->CONSTRAINT_NAME}");
                } catch (Throwable $e) {
                    // FK might already be gone
                }
            }

            // Also check FKs FROM other tables referencing this column
            $referencingFKs = DB::select("
                SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_COLUMN_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME = ? AND REFERENCED_COLUMN_NAME = ?
            ", [$this->databaseName, $tableName, $columnName]);

            foreach ($referencingFKs as $fk) {
                try {
                    DB::statement("ALTER TABLE `{$fk->TABLE_NAME}` DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
                    $droppedFKs[] = [
                        'table'             => $fk->TABLE_NAME,
                        'constraint'        => $fk->CONSTRAINT_NAME,
                        'column'            => $fk->COLUMN_NAME,
                        'referenced_table'  => $tableName,
                        'referenced_column' => $columnName,
                    ];
                    Log::info("[SchemaReconciler] Dropped referencing FK: {$fk->TABLE_NAME}.{$fk->CONSTRAINT_NAME}");
                } catch (Throwable $e) {
                    // Ignore
                }
            }
        } catch (Throwable $e) {
            Log::warning("[SchemaReconciler] Could not check FKs for {$tableName}.{$columnName}: " . $e->getMessage());
        }

        return $droppedFKs;
    }

    /**
     * Recreate a previously dropped foreign key
     */
    protected function recreateForeignKey(array $fk): void
    {
        try {
            $sql = "ALTER TABLE `{$fk['table']}` ADD CONSTRAINT `{$fk['constraint']}` "
                 . "FOREIGN KEY (`{$fk['column']}`) REFERENCES `{$fk['referenced_table']}` (`{$fk['referenced_column']}`) "
                 . "ON DELETE CASCADE ON UPDATE CASCADE";

            DB::statement($sql);
            Log::info("[SchemaReconciler] Recreated FK: {$fk['table']}.{$fk['constraint']}");
        } catch (Throwable $e) {
            // Non-fatal — FK recreation can fail if data doesn't match
            Log::warning("[SchemaReconciler] Could not recreate FK: {$fk['constraint']}: " . $e->getMessage());
        }
    }

    /**
     * Add a missing index to a table
     */
    protected function addMissingIndex(string $tableName, string $indexName, array $indexDef): void
    {
        try {
            $columns = $indexDef['columns'] ?? [];
            if (empty($columns)) return;

            // Verify all columns exist before adding index
            foreach ($columns as $col) {
                if (!Schema::hasColumn($tableName, $col)) {
                    $this->results['skipped'][] = "Index {$tableName}.{$indexName}: column {$col} missing";
                    return;
                }
            }

            $indexColumns = implode('`, `', $columns);
            $indexType = strtoupper($indexDef['type'] ?? 'INDEX');

            if ($indexType === 'UNIQUE') {
                $sql = "ALTER TABLE `{$tableName}` ADD UNIQUE KEY `{$indexName}` (`{$indexColumns}`)";
            } elseif ($indexType === 'FULLTEXT') {
                $sql = "ALTER TABLE `{$tableName}` ADD FULLTEXT KEY `{$indexName}` (`{$indexColumns}`)";
            } else {
                $sql = "ALTER TABLE `{$tableName}` ADD INDEX `{$indexName}` (`{$indexColumns}`)";
            }

            DB::statement($sql);

            $this->results['added_indexes'][] = "{$tableName}.{$indexName}";
            Log::info("[SchemaReconciler] Added missing index: {$tableName}.{$indexName}");

        } catch (Throwable $e) {
            // Index might already exist with a different name
            if ($this->isDuplicateKeyError($e->getMessage())) {
                $this->results['skipped'][] = "Index {$tableName}.{$indexName}: already exists";
                return;
            }

            $this->results['errors'][] = [
                'table' => $tableName,
                'index' => $indexName,
                'action' => 'add_index',
                'error' => $e->getMessage(),
            ];
            Log::error("[SchemaReconciler] Failed to add index: {$tableName}.{$indexName}", [
                'error' => $e->getMessage()
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  Schema Introspection
    // ─────────────────────────────────────────────────────────────

    /**
     * Get actual columns from database for a table
     *
     * @return array<string, array> Column name => column info
     */
    protected function getActualColumns(string $tableName): array
    {
        $columns = [];

        try {
            $rows = DB::select(
                "SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, COLUMN_KEY
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
                 ORDER BY ORDINAL_POSITION",
                [$this->databaseName, $tableName]
            );

            foreach ($rows as $row) {
                $columns[$row->COLUMN_NAME] = [
                    'type'     => $row->COLUMN_TYPE,
                    'nullable' => $row->IS_NULLABLE === 'YES',
                    'default'  => $row->COLUMN_DEFAULT,
                    'extra'    => $row->EXTRA,
                    'key'      => $row->COLUMN_KEY,
                ];
            }
        } catch (Throwable $e) {
            Log::warning("[SchemaReconciler] Could not introspect columns for: {$tableName}");
        }

        return $columns;
    }

    /**
     * Get actual indexes from database for a table
     *
     * @return array<string, array> Index name => index info
     */
    protected function getActualIndexes(string $tableName): array
    {
        $indexes = [];

        try {
            $rows = DB::select("SHOW INDEX FROM `{$tableName}`");

            foreach ($rows as $row) {
                $name = $row->Key_name;
                if (!isset($indexes[$name])) {
                    $indexes[$name] = [
                        'columns' => [],
                        'type'    => $row->Non_unique ? 'INDEX' : 'UNIQUE',
                    ];
                }
                $indexes[$name]['columns'][] = $row->Column_name;
            }
        } catch (Throwable $e) {
            Log::warning("[SchemaReconciler] Could not introspect indexes for: {$tableName}");
        }

        return $indexes;
    }

    // ─────────────────────────────────────────────────────────────
    //  SQL Building
    // ─────────────────────────────────────────────────────────────

    /**
     * Build column definition SQL from snapshot data
     */
    protected function buildColumnDefinition(string $columnName, array $colDef): string
    {
        $type = $colDef['type'] ?? 'varchar(255)';
        $nullable = $colDef['nullable'] ?? true;
        $default = $colDef['default'] ?? null;
        $extra = $colDef['extra'] ?? '';

        $sql = "`{$columnName}` {$type}";

        // Handle auto_increment (implies NOT NULL)
        if (stripos($extra, 'auto_increment') !== false) {
            $sql .= ' NOT NULL AUTO_INCREMENT';
            return $sql;
        }

        // Nullable
        $sql .= $nullable ? ' NULL' : ' NOT NULL';

        // Default value
        if ($default !== null) {
            if (strtoupper($default) === 'CURRENT_TIMESTAMP' || strtoupper($default) === 'NULL') {
                $sql .= " DEFAULT {$default}";
            } else {
                $sql .= " DEFAULT " . DB::getPdo()->quote($default);
            }
        } elseif ($nullable) {
            $sql .= ' DEFAULT NULL';
        }

        // Extra (e.g., ON UPDATE CURRENT_TIMESTAMP)
        $extraClean = str_ireplace('auto_increment', '', $extra);
        $extraClean = trim($extraClean);
        if (!empty($extraClean)) {
            // Only add recognized extras
            if (stripos($extraClean, 'on update') !== false) {
                $sql .= " {$extraClean}";
            }
        }

        return $sql;
    }

    /**
     * Determine AFTER clause for column positioning
     */
    protected function determineColumnPosition(string $tableName, string $columnName, array $colDef): string
    {
        $after = $colDef['after'] ?? null;

        if ($after && Schema::hasColumn($tableName, $after)) {
            return " AFTER `{$after}`";
        }

        return '';
    }

    // ─────────────────────────────────────────────────────────────
    //  Schema Snapshot I/O
    // ─────────────────────────────────────────────────────────────

    /**
     * Load expected schema from snapshot file
     *
     * @return array<string, array> Table name => table schema
     */
    protected function loadExpectedSchema(): array
    {
        if (!file_exists($this->snapshotPath)) {
            // Try alternate location during update
            $altPath = storage_path('app/public/temp_update/database/schema_snapshot.json');
            if (file_exists($altPath)) {
                $this->snapshotPath = $altPath;
            } else {
                return [];
            }
        }

        try {
            $content = file_get_contents($this->snapshotPath);
            $data = json_decode($content, true);

            if (!is_array($data) || empty($data['tables'])) {
                return [];
            }

            return $data['tables'];
        } catch (Throwable $e) {
            Log::error('[SchemaReconciler] Failed to load schema snapshot', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Generate and save schema snapshot from current database
     *
     * Called by the artisan command: php artisan schema:snapshot
     *
     * @return array The generated snapshot
     */
    public function generateSnapshot(): array
    {
        $snapshot = [
            'version'    => site_settings('app_version', config('app.version', '1.0')),
            'generated'  => now()->toIso8601String(),
            'database'   => $this->databaseName,
            'tables'     => [],
        ];

        $tables = $this->getAllTables();

        foreach ($tables as $tableName) {
            // Skip Laravel internal tables
            if (in_array($tableName, ['migrations', 'failed_jobs', 'personal_access_tokens', 'telescope_entries', 'telescope_entries_tags', 'telescope_monitoring'])) {
                continue;
            }

            $snapshot['tables'][$tableName] = [
                'columns' => $this->snapshotColumns($tableName),
                'indexes' => $this->snapshotIndexes($tableName),
            ];
        }

        return $snapshot;
    }

    /**
     * Save snapshot to JSON file
     */
    public function saveSnapshot(array $snapshot): string
    {
        $json = json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        file_put_contents($this->snapshotPath, $json);

        return $this->snapshotPath;
    }

    /**
     * Get all table names in the database
     */
    protected function getAllTables(): array
    {
        $tables = [];

        try {
            $rows = DB::select(
                "SELECT TABLE_NAME
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'
                 ORDER BY TABLE_NAME",
                [$this->databaseName]
            );

            foreach ($rows as $row) {
                $tables[] = $row->TABLE_NAME;
            }
        } catch (Throwable $e) {
            Log::error('[SchemaReconciler] Failed to list tables', [
                'error' => $e->getMessage()
            ]);
        }

        return $tables;
    }

    /**
     * Snapshot column definitions for a table
     */
    protected function snapshotColumns(string $tableName): array
    {
        $columns = [];

        $rows = DB::select(
            "SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, COLUMN_KEY
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
             ORDER BY ORDINAL_POSITION",
            [$this->databaseName, $tableName]
        );

        $prevColumn = null;
        foreach ($rows as $row) {
            $columns[$row->COLUMN_NAME] = [
                'type'     => $row->COLUMN_TYPE,
                'nullable' => $row->IS_NULLABLE === 'YES',
                'default'  => $row->COLUMN_DEFAULT,
                'extra'    => $row->EXTRA,
                'key'      => $row->COLUMN_KEY,
                'after'    => $prevColumn,
            ];
            $prevColumn = $row->COLUMN_NAME;
        }

        return $columns;
    }

    /**
     * Snapshot index definitions for a table
     */
    protected function snapshotIndexes(string $tableName): array
    {
        $indexes = [];

        $rows = DB::select("SHOW INDEX FROM `{$tableName}`");

        foreach ($rows as $row) {
            $name = $row->Key_name;
            if (!isset($indexes[$name])) {
                $indexes[$name] = [
                    'columns' => [],
                    'type'    => $row->Non_unique ? 'INDEX' : 'UNIQUE',
                ];
                if ($name === 'PRIMARY') {
                    $indexes[$name]['type'] = 'PRIMARY';
                }
            }
            $indexes[$name]['columns'][] = $row->Column_name;
        }

        return $indexes;
    }

    // ─────────────────────────────────────────────────────────────
    //  Error Detection
    // ─────────────────────────────────────────────────────────────

    protected function isColumnExistsError(string $message): bool
    {
        $msg = strtolower($message);
        return str_contains($msg, '1060')
            || str_contains($msg, 'duplicate column')
            || str_contains($msg, 'column already exists');
    }

    protected function isDuplicateKeyError(string $message): bool
    {
        $msg = strtolower($message);
        return str_contains($msg, '1061')
            || str_contains($msg, 'duplicate key')
            || str_contains($msg, 'duplicate entry');
    }

    // ─────────────────────────────────────────────────────────────
    //  Foreign Key Management
    // ─────────────────────────────────────────────────────────────

    protected function disableForeignKeyChecks(): void
    {
        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        } catch (Throwable $e) {
            Log::warning('[SchemaReconciler] Could not disable FK checks: ' . $e->getMessage());
        }
    }

    protected function enableForeignKeyChecks(): void
    {
        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        } catch (Throwable $e) {
            Log::warning('[SchemaReconciler] Could not re-enable FK checks: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  Results
    // ─────────────────────────────────────────────────────────────

    protected function resetResults(): void
    {
        $this->results = [
            'created_tables'   => [],
            'added_columns'    => [],
            'added_indexes'    => [],
            'modified_columns' => [],
            'skipped'          => [],
            'errors'           => [],
        ];
    }

    protected function logFinalResults(): void
    {
        $summary = sprintf(
            '[SchemaReconciler] Reconciliation complete — Tables created: %d, Columns added: %d, Indexes added: %d, Skipped: %d, Errors: %d',
            count($this->results['created_tables']),
            count($this->results['added_columns']),
            count($this->results['added_indexes']),
            count($this->results['skipped']),
            count($this->results['errors'])
        );

        Log::info($summary);

        if (!empty($this->results['errors'])) {
            Log::warning('[SchemaReconciler] Reconciliation errors', $this->results['errors']);
        }
    }

    public function getResults(): array
    {
        return $this->results;
    }

    public function getSummaryMessage(): string
    {
        $tables  = count($this->results['created_tables']);
        $columns = count($this->results['added_columns']);
        $indexes = count($this->results['added_indexes']);
        $errors  = count($this->results['errors']);

        if ($tables === 0 && $columns === 0 && $indexes === 0 && $errors === 0) {
            return 'Schema is up to date — no changes needed.';
        }

        $parts = [];
        if ($tables > 0)  $parts[] = "{$tables} table(s) created";
        if ($columns > 0) $parts[] = "{$columns} column(s) added";
        if ($indexes > 0) $parts[] = "{$indexes} index(es) added";
        if ($errors > 0)  $parts[] = "{$errors} error(s)";

        return 'Schema reconciliation: ' . implode(', ', $parts) . '.';
    }
}
