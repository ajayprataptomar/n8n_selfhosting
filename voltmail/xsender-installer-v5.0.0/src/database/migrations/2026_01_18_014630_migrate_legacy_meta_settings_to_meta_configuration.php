<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration auto-migrates legacy Meta App credentials (stored in site_settings)
     * to the new MetaConfiguration table for existing users who update to this version.
     *
     * @return void
     */
    public function up(): void
    {
        // Skip if meta_configurations table doesn't exist
        if (!Schema::hasTable('meta_configurations')) {
            return;
        }

        // Check if there are already any MetaConfigurations
        $existingConfigs = DB::table('meta_configurations')->count();
        if ($existingConfigs > 0) {
            // Already has configurations, no need to migrate
            return;
        }

        // Get legacy settings from the settings table
        $settings = DB::table('settings')
            ->whereIn('key', ['meta_app_Id', 'meta_app_secret', 'webhook_verify_token'])
            ->pluck('value', 'key')
            ->toArray();

        $metaAppId = $settings['meta_app_Id'] ?? null;
        $metaAppSecret = $settings['meta_app_secret'] ?? null;
        $webhookVerifyToken = $settings['webhook_verify_token'] ?? null;

        // Only migrate if both app_id and app_secret exist
        if (empty($metaAppId) || empty($metaAppSecret)) {
            return;
        }

        // Create a MetaConfiguration from legacy settings
        DB::table('meta_configurations')->insert([
            'uid' => Str::uuid()->toString(),
            'name' => 'Legacy Configuration (Migrated)',
            'environment' => 'production',
            'app_id' => $metaAppId,
            'app_secret' => $metaAppSecret, // Note: In production, this should be encrypted
            'api_version' => 'v21.0',
            'webhook_verify_token' => $webhookVerifyToken ?? Str::random(32),
            'is_default' => true,
            'status' => 'active',
            'config_id' => null, // User needs to add this manually for Meta 2025 compliance
            'solution_id' => null,
            'business_manager_id' => null,
            'tech_provider_id' => null,
            'system_user_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Log the migration
        \Illuminate\Support\Facades\Log::info('Migrated legacy Meta settings to MetaConfiguration table', [
            'app_id' => substr($metaAppId, 0, 8) . '...',
            'migrated_at' => now()->toISOString(),
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        // Delete the migrated configuration (identified by name)
        DB::table('meta_configurations')
            ->where('name', 'Legacy Configuration (Migrated)')
            ->delete();
    }
};
