<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Upgrade Meta Graph API version defaults from v22.0/v23.0/v24.0 to v25.0
     *
     * Meta Graph API v25.0 (Released: February 4, 2026)
     * - Backward-compatible for WhatsApp Cloud API messaging
     * - v22.0 expires Feb 10, 2026
     * - v23.0 available until Jun 9, 2026
     * - v24.0 available until Oct 7, 2026
     * - v25.0: Latest stable
     */
    public function up(): void
    {
        // Update meta_configurations table - column default + existing records
        if (Schema::hasTable('meta_configurations') && Schema::hasColumn('meta_configurations', 'api_version')) {
            DB::statement("ALTER TABLE `meta_configurations` ALTER COLUMN `api_version` SET DEFAULT 'v25.0'");

            DB::table('meta_configurations')
                ->whereIn('api_version', ['v19.0', 'v20.0', 'v21.0', 'v22.0', 'v23.0'])
                ->update(['api_version' => 'v25.0']);
        }

        // Update gateways table - column default + existing records
        if (Schema::hasTable('gateways') && Schema::hasColumn('gateways', 'api_version')) {
            DB::statement("ALTER TABLE `gateways` ALTER COLUMN `api_version` SET DEFAULT 'v25.0'");

            DB::table('gateways')
                ->whereIn('api_version', ['v19.0', 'v20.0', 'v21.0', 'v22.0', 'v23.0'])
                ->update(['api_version' => 'v25.0']);
        }

        // Update site settings if meta_api_version exists
        if (Schema::hasTable('settings')) {
            DB::table('settings')
                ->where('key', 'meta_api_version')
                ->whereIn('value', ['v19.0', 'v20.0', 'v21.0', 'v22.0', 'v23.0'])
                ->update(['value' => 'v25.0']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('meta_configurations') && Schema::hasColumn('meta_configurations', 'api_version')) {
            DB::statement("ALTER TABLE `meta_configurations` ALTER COLUMN `api_version` SET DEFAULT 'v24.0'");
        }

        if (Schema::hasTable('gateways') && Schema::hasColumn('gateways', 'api_version')) {
            DB::statement("ALTER TABLE `gateways` ALTER COLUMN `api_version` SET DEFAULT 'v24.0'");
        }
    }
};
