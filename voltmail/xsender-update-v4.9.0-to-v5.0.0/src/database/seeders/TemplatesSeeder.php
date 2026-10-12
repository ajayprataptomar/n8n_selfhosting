<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class TemplatesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     * @throws \Exception
     */
    public function run()
    {
        $sqlFilePath = resource_path('database/templates.sql');

        if (!File::exists($sqlFilePath)) {
            throw new \Exception("SQL file not found at: {$sqlFilePath}");
        }

        // Idempotent: if the table already has seed rows, skip. Lets safe
        // re-runs of `db:seed` and the SafeMigrationRunner not double-insert
        // the system templates.
        if (\Illuminate\Support\Facades\Schema::hasTable('templates')
            && DB::table('templates')->exists()) {
            $this->command->info('Templates already seeded — skipping.');
            return;
        }

        try {
            $sql = File::get($sqlFilePath);

            // Strip the bare CREATE TABLE + ALTER TABLE (PK/AUTO_INCREMENT)
            // blocks. The Laravel migration in
            // 2026_08_26_000001_create_templates_table_if_missing.php now
            // owns the schema — running the SQL dump's CREATE against a
            // pre-existing table throws "table already exists", and its
            // no-inline-PK form fails on servers with
            // sql_require_primary_key=ON. Leaving only the INSERTs makes
            // this seeder pure data.
            $sql = preg_replace('/CREATE\s+TABLE\s+`templates`\s*\([^;]*?\)\s*ENGINE[^;]*?;/is', '', $sql);
            $sql = preg_replace('/ALTER\s+TABLE\s+`templates`[^;]*?;/is', '', $sql);

            DB::unprepared($sql);
            $this->command->info('Templates seeded successfully.');
        } catch (\Exception $e) {
            $this->command->error('Failed to seed templates: ' . $e->getMessage());
            throw $e;
        }
    }
}