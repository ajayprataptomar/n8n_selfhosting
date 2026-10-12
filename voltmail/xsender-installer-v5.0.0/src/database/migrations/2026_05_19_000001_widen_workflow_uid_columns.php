<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Workflow tables ship with uid VARCHAR(32) but the model generates 36-char
 * UUIDs via Str::uuid()->toString(). MySQL silently truncates on insert,
 * so the value returned by the model after refresh does not match the URL
 * the controller redirects to — every "Show Workflow" hits 404.
 *
 * Widening to 50 chars matches every other uid column in the codebase
 * and leaves headroom for future ID formats.
 */
return new class extends Migration
{
    private array $tables = [
        'automation_workflows',
        'workflow_nodes',
        'workflow_executions',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'uid')) {
                continue;
            }

            // Widen so future UUIDs (36 chars) are stored fully.
            try {
                Schema::table($table, function (Blueprint $t) {
                    $t->string('uid', 50)->change();
                });
            } catch (\Throwable $e) {
                // doctrine/dbal may be missing on some installs — fall back to raw ALTER.
                try {
                    DB::statement("ALTER TABLE `{$table}` MODIFY `uid` VARCHAR(50) NOT NULL");
                } catch (\Throwable $inner) {
                    \Log::warning("widen_workflow_uid_columns: could not widen {$table}.uid: " . $inner->getMessage());
                }
            }

            // Existing rows with truncated uids (length < 36) get a fresh full UUID so
            // the model's auto-generated uid matches what controllers redirect to.
            try {
                DB::table($table)
                    ->whereRaw('CHAR_LENGTH(uid) < 36')
                    ->orWhereNull('uid')
                    ->get()
                    ->each(function ($row) use ($table) {
                        DB::table($table)->where('id', $row->id)->update([
                            'uid' => (string) \Illuminate\Support\Str::uuid(),
                        ]);
                    });
            } catch (\Throwable $e) {
                \Log::warning("widen_workflow_uid_columns: backfill uid on {$table} failed: " . $e->getMessage());
            }
        }
    }

    public function down(): void
    {
        // Down would truncate data — refuse.
    }
};
