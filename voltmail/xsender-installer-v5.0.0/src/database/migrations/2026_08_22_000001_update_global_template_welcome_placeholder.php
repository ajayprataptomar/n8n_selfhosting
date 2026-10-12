<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fresh installs run this migration BEFORE the `templates` table
        // exists (that table is created later by
        // 2026_08_26_000001_create_templates_table_if_missing.php + populated
        // by TemplatesSeeder — which already ships with the {{name}} placeholder
        // baked in). A bare DB::table('templates')->first() throws 42S02 on
        // fresh installs and aborts migrate:fresh, leaving 20+ later migrations
        // un-run and the whole install broken. Skip silently on fresh installs
        // — this migration exists ONLY to patch existing v4.9.0 databases.
        if (! Schema::hasTable('templates')) {
            return;
        }

        $row = DB::table('templates')
            ->where('slug', 'GLOBAL_TEMPLATE')
            ->whereNull('user_id')
            ->where('channel', 'email')
            ->first();

        if (! $row) {
            return;
        }

        $data = json_decode($row->template_data ?? '{}', true) ?: [];
        $body = (string) ($data['mail_body'] ?? '');

        if ($body === '' || stripos($body, '{{name}}') !== false) {
            return;
        }

        $updated = preg_replace(
            '~Welcome to X\s*sender~i',
            'Welcome to {{name}}',
            $body,
            1,
            $count
        );

        if ($count === 0 || $updated === null) {
            return;
        }

        $data['mail_body'] = $updated;

        DB::table('templates')
            ->where('id', $row->id)
            ->update([
                'template_data'    => json_encode($data, JSON_UNESCAPED_SLASHES),
                'personalize_data' => json_encode([
                    'name'    => 'Site Name (from settings)',
                    'message' => 'Email Data',
                ], JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // No-op; hardcoded brand text restore not desirable.
    }
};
