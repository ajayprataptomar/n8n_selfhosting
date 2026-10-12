<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

/**
 * Fix duplicate settings keys and ensure unique constraint
 *
 * Problem: Previous updates inserted duplicate rows for the same key
 * (e.g., app_version appeared 5+ times). This migration:
 * 1. Removes duplicate rows, keeping only the LATEST value for each key
 * 2. Ensures a unique constraint exists on the `key` column
 * 3. Preserves all user data — no settings values are lost
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('settings')) {
            return;
        }

        // Step 1: Remove duplicate keys, keeping only the row with highest ID (latest value)
        $this->deduplicateSettings();

        // Step 2: Ensure unique constraint on `key` column
        $this->ensureUniqueConstraint();
    }

    /**
     * Remove duplicate settings rows, keeping only the latest (highest ID) for each key
     */
    protected function deduplicateSettings(): void
    {
        try {
            // Find all keys that have duplicates
            $duplicates = DB::select("
                SELECT `key`, COUNT(*) as cnt, MAX(id) as keep_id
                FROM `settings`
                WHERE `key` IS NOT NULL
                GROUP BY `key`
                HAVING COUNT(*) > 1
            ");

            if (empty($duplicates)) {
                Log::info('[FixDuplicateSettings] No duplicate settings found');
                return;
            }

            $totalRemoved = 0;

            foreach ($duplicates as $dup) {
                // Delete all rows for this key EXCEPT the one with highest ID
                $deleted = DB::table('settings')
                    ->where('key', $dup->key)
                    ->where('id', '!=', $dup->keep_id)
                    ->delete();

                $totalRemoved += $deleted;

                Log::info("[FixDuplicateSettings] Deduplicated key '{$dup->key}': removed {$deleted} duplicate(s), kept id={$dup->keep_id}");
            }

            Log::info("[FixDuplicateSettings] Total duplicates removed: {$totalRemoved} across " . count($duplicates) . " keys");

        } catch (\Throwable $e) {
            Log::error('[FixDuplicateSettings] Deduplication failed: ' . $e->getMessage());
        }
    }

    /**
     * Ensure unique constraint exists on key column
     */
    protected function ensureUniqueConstraint(): void
    {
        try {
            // Check if unique constraint already exists
            $indexes = DB::select("SHOW INDEX FROM `settings` WHERE Column_name = 'key'");

            $hasUnique = false;
            foreach ($indexes as $index) {
                if ($index->Non_unique == 0 && $index->Column_name === 'key') {
                    $hasUnique = true;
                    break;
                }
            }

            if (!$hasUnique) {
                // Remove any non-unique index on key first
                foreach ($indexes as $index) {
                    if ($index->Column_name === 'key' && $index->Key_name !== 'PRIMARY') {
                        try {
                            DB::statement("ALTER TABLE `settings` DROP INDEX `{$index->Key_name}`");
                        } catch (\Throwable $e) {
                            // Index might already be gone
                        }
                    }
                }

                // Add unique constraint
                DB::statement("ALTER TABLE `settings` ADD UNIQUE INDEX `settings_key_unique` (`key`)");
                Log::info('[FixDuplicateSettings] Added unique constraint on settings.key');
            } else {
                Log::info('[FixDuplicateSettings] Unique constraint on settings.key already exists');
            }

        } catch (\Throwable $e) {
            // If adding unique fails (e.g., NULL keys), log but don't break
            Log::warning('[FixDuplicateSettings] Could not add unique constraint: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        // No rollback — deduplication is a data fix
    }
};
