<?php

namespace App\Console\Commands;

use App\Services\Core\SchemaReconciler;
use Illuminate\Console\Command;

/**
 * Generate a schema snapshot from the current database
 *
 * Usage:
 *   php artisan schema:snapshot           — Generate snapshot from current DB
 *   php artisan schema:snapshot --verify  — Verify current DB against existing snapshot
 *   php artisan schema:snapshot --reconcile — Run reconciliation against existing snapshot
 *
 * This command should be run by developers BEFORE creating an update package
 * to capture the expected schema state. The snapshot file (database/schema_snapshot.json)
 * is included in update packages and used by SchemaReconciler during updates.
 */
class SchemaSnapshotCommand extends Command
{
    protected $signature = 'schema:snapshot
                            {--verify : Verify current DB against existing snapshot without making changes}
                            {--reconcile : Run reconciliation to fix missing tables/columns}';

    protected $description = 'Generate, verify, or reconcile database schema snapshot';

    public function handle(): int
    {
        $reconciler = new SchemaReconciler();

        if ($this->option('verify')) {
            return $this->verifySchema($reconciler);
        }

        if ($this->option('reconcile')) {
            return $this->reconcileSchema($reconciler);
        }

        return $this->generateSnapshot($reconciler);
    }

    /**
     * Generate a new snapshot from current database
     */
    protected function generateSnapshot(SchemaReconciler $reconciler): int
    {
        $this->info('Generating schema snapshot from current database...');
        $this->newLine();

        $snapshot = $reconciler->generateSnapshot();

        $tableCount  = count($snapshot['tables'] ?? []);
        $columnCount = 0;
        $indexCount  = 0;

        foreach ($snapshot['tables'] as $table) {
            $columnCount += count($table['columns'] ?? []);
            $indexCount  += count($table['indexes'] ?? []);
        }

        $path = $reconciler->saveSnapshot($snapshot);

        $this->info("Snapshot generated successfully!");
        $this->newLine();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Tables', $tableCount],
                ['Columns', $columnCount],
                ['Indexes', $indexCount],
                ['Version', $snapshot['version'] ?? 'unknown'],
            ]
        );

        $this->newLine();
        $this->info("Saved to: {$path}");
        $this->comment("Include this file in your update package (it's in database/ directory).");

        return Command::SUCCESS;
    }

    /**
     * Verify current DB against existing snapshot
     */
    protected function verifySchema(SchemaReconciler $reconciler): int
    {
        $this->info('Verifying current database against schema snapshot...');
        $this->newLine();

        $snapshot = $reconciler->generateSnapshot();
        $snapshotPath = database_path('schema_snapshot.json');

        if (!file_exists($snapshotPath)) {
            $this->error('No schema snapshot found. Run `php artisan schema:snapshot` first.');
            return Command::FAILURE;
        }

        $expected = json_decode(file_get_contents($snapshotPath), true);
        $expectedTables = $expected['tables'] ?? [];
        $currentTables = $snapshot['tables'] ?? [];

        $missingTables    = [];
        $missingColumns   = [];
        $missingIndexes   = [];
        $typeMismatches   = [];

        foreach ($expectedTables as $tableName => $tableSchema) {
            if (!isset($currentTables[$tableName])) {
                $missingTables[] = $tableName;
                continue;
            }

            $expectedColumns = $tableSchema['columns'] ?? [];
            $actualColumns = $currentTables[$tableName]['columns'] ?? [];

            foreach ($expectedColumns as $colName => $colDef) {
                if (!isset($actualColumns[$colName])) {
                    $missingColumns[] = "{$tableName}.{$colName}";
                } else {
                    // Check type mismatch
                    $expType = preg_replace('/\b(int|bigint|smallint|tinyint|mediumint)\(\d+\)/', '$1', strtolower(trim($colDef['type'] ?? '')));
                    $actType = preg_replace('/\b(int|bigint|smallint|tinyint|mediumint)\(\d+\)/', '$1', strtolower(trim($actualColumns[$colName]['type'] ?? '')));
                    if ($expType !== $actType) {
                        $typeMismatches[] = "{$tableName}.{$colName}: {$actType} → {$expType}";
                    }
                }
            }

            $expectedIndexes = $tableSchema['indexes'] ?? [];
            $actualIndexes = $currentTables[$tableName]['indexes'] ?? [];

            foreach ($expectedIndexes as $idxName => $idxDef) {
                if (!isset($actualIndexes[$idxName]) && $idxName !== 'PRIMARY') {
                    $missingIndexes[] = "{$tableName}.{$idxName}";
                }
            }
        }

        if (empty($missingTables) && empty($missingColumns) && empty($missingIndexes) && empty($typeMismatches)) {
            $this->info('Schema is fully consistent — no differences found.');
            return Command::SUCCESS;
        }

        $this->warn('Schema differences detected:');
        $this->newLine();

        if (!empty($missingTables)) {
            $this->error("Missing tables (" . count($missingTables) . "):");
            foreach ($missingTables as $t) {
                $this->line("  - {$t}");
            }
            $this->newLine();
        }

        if (!empty($missingColumns)) {
            $this->error("Missing columns (" . count($missingColumns) . "):");
            foreach ($missingColumns as $c) {
                $this->line("  - {$c}");
            }
            $this->newLine();
        }

        if (!empty($typeMismatches)) {
            $this->warn("Column type mismatches (" . count($typeMismatches) . "):");
            foreach ($typeMismatches as $m) {
                $this->line("  ~ {$m}");
            }
            $this->newLine();
        }

        if (!empty($missingIndexes)) {
            $this->warn("Missing indexes (" . count($missingIndexes) . "):");
            foreach ($missingIndexes as $i) {
                $this->line("  - {$i}");
            }
        }

        $this->newLine();
        $this->comment('Run `php artisan schema:snapshot --reconcile` to fix these differences.');

        return Command::FAILURE;
    }

    /**
     * Run reconciliation to fix missing tables/columns
     */
    protected function reconcileSchema(SchemaReconciler $reconciler): int
    {
        $this->info('Running schema reconciliation...');
        $this->newLine();

        $results = $reconciler->reconcile();

        // Display results
        if (!empty($results['created_tables'])) {
            $this->info('Tables created:');
            foreach ($results['created_tables'] as $t) {
                $this->line("  + {$t}");
            }
            $this->newLine();
        }

        if (!empty($results['added_columns'])) {
            $this->info('Columns added:');
            foreach ($results['added_columns'] as $c) {
                $this->line("  + {$c}");
            }
            $this->newLine();
        }

        if (!empty($results['added_indexes'])) {
            $this->info('Indexes added:');
            foreach ($results['added_indexes'] as $i) {
                $this->line("  + {$i}");
            }
            $this->newLine();
        }

        if (!empty($results['errors'])) {
            $this->error('Errors (' . count($results['errors']) . '):');
            foreach ($results['errors'] as $err) {
                $this->line("  ! {$err['table']}: {$err['error']}");
            }
            $this->newLine();
        }

        $this->info($reconciler->getSummaryMessage());

        return empty($results['errors']) ? Command::SUCCESS : Command::FAILURE;
    }
}
