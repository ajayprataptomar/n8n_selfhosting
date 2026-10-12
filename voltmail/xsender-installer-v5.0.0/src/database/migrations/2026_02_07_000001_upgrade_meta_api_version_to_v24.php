<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Upgrade Meta Graph API version defaults from v21.0/v22.0 to v24.0
     *
     * Meta Graph API v24.0 (Released: October 8, 2025)
     * - Conversation object deprecated from message status webhooks
     * - Per-message pricing (PMP) model
     * - Rate limits at business portfolio level
     * - On-premises API fully deprecated
     *
     * Version support timeline:
     * - v22.0: Available until Feb 10, 2026
     * - v23.0: Available until Jun 9, 2026
     * - v24.0: Latest stable
     */
    public function up(): void
    {
        // Update meta_configurations table - column default + existing records
        if (Schema::hasTable('meta_configurations') && Schema::hasColumn('meta_configurations', 'api_version')) {
            DB::statement("ALTER TABLE `meta_configurations` ALTER COLUMN `api_version` SET DEFAULT 'v24.0'");

            DB::table('meta_configurations')
                ->whereIn('api_version', ['v19.0', 'v20.0', 'v21.0'])
                ->update(['api_version' => 'v24.0']);
        }

        // Update gateways table - column default + existing records
        if (Schema::hasTable('gateways') && Schema::hasColumn('gateways', 'api_version')) {
            DB::statement("ALTER TABLE `gateways` ALTER COLUMN `api_version` SET DEFAULT 'v24.0'");

            DB::table('gateways')
                ->whereIn('api_version', ['v19.0', 'v20.0', 'v21.0'])
                ->update(['api_version' => 'v24.0']);
        }

        // Update site settings if meta_api_version exists
        if (Schema::hasTable('settings')) {
            DB::table('settings')
                ->where('key', 'meta_api_version')
                ->whereIn('value', ['v19.0', 'v20.0', 'v21.0', 'v22.0'])
                ->update(['value' => 'v24.0']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('meta_configurations') && Schema::hasColumn('meta_configurations', 'api_version')) {
            DB::statement("ALTER TABLE `meta_configurations` ALTER COLUMN `api_version` SET DEFAULT 'v22.0'");
        }

        if (Schema::hasTable('gateways') && Schema::hasColumn('gateways', 'api_version')) {
            DB::statement("ALTER TABLE `gateways` ALTER COLUMN `api_version` SET DEFAULT 'v22.0'");
        }
    }
};
