<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Skip if gateways table doesn't exist
        if (!Schema::hasTable('gateways')) {
            return;
        }

        Schema::table('gateways', function (Blueprint $table) {
            // Meta Configuration Reference
            if (!Schema::hasColumn('gateways', 'meta_configuration_id')) {
                $table->unsignedBigInteger('meta_configuration_id')->nullable()->after('user_id');
            }

            // WhatsApp Business Account Fields
            if (!Schema::hasColumn('gateways', 'waba_id')) {
                $table->string('waba_id')->nullable()->after('meta_data')->comment('WhatsApp Business Account ID');
            }
            if (!Schema::hasColumn('gateways', 'phone_number_id')) {
                $table->string('phone_number_id')->nullable()->after('waba_id');
            }
            if (!Schema::hasColumn('gateways', 'business_id')) {
                $table->string('business_id')->nullable()->after('phone_number_id');
            }

            // Status & Quality
            if (!Schema::hasColumn('gateways', 'verification_status')) {
                $table->string('verification_status')->nullable()->after('status')->comment('verified, pending, not_verified');
            }
            if (!Schema::hasColumn('gateways', 'quality_rating')) {
                $table->string('quality_rating')->nullable()->after('verification_status')->comment('GREEN, YELLOW, RED');
            }
            if (!Schema::hasColumn('gateways', 'messaging_limit_tier')) {
                $table->string('messaging_limit_tier')->nullable()->after('quality_rating')->comment('TIER_1K, TIER_10K, TIER_100K, UNLIMITED');
            }
            if (!Schema::hasColumn('gateways', 'account_mode')) {
                $table->string('account_mode')->nullable()->after('messaging_limit_tier')->comment('SANDBOX, LIVE');
            }

            // Token Management
            if (!Schema::hasColumn('gateways', 'token_expires_at')) {
                $table->timestamp('token_expires_at')->nullable()->after('api_version');
            }
            if (!Schema::hasColumn('gateways', 'token_refreshed_at')) {
                $table->timestamp('token_refreshed_at')->nullable()->after('token_expires_at');
            }

            // Health Monitoring
            if (!Schema::hasColumn('gateways', 'health_status')) {
                $table->enum('health_status', ['healthy', 'degraded', 'unhealthy', 'unknown'])->default('unknown')->after('status');
            }
            if (!Schema::hasColumn('gateways', 'last_health_check_at')) {
                $table->timestamp('last_health_check_at')->nullable()->after('last_sync_at');
            }
            if (!Schema::hasColumn('gateways', 'health_check_history')) {
                $table->json('health_check_history')->nullable()->after('last_health_check_at');
            }
            if (!Schema::hasColumn('gateways', 'consecutive_failures')) {
                $table->integer('consecutive_failures')->default(0)->after('health_check_history');
            }

            // Webhook Status
            if (!Schema::hasColumn('gateways', 'webhook_subscribed')) {
                $table->boolean('webhook_subscribed')->default(false)->after('consecutive_failures');
            }
            if (!Schema::hasColumn('gateways', 'webhook_subscribed_at')) {
                $table->timestamp('webhook_subscribed_at')->nullable()->after('webhook_subscribed');
            }

            // Onboarding Reference
            if (!Schema::hasColumn('gateways', 'onboarding_id')) {
                $table->unsignedBigInteger('onboarding_id')->nullable()->after('meta_configuration_id');
            }
        });

        // Add indexes - use try-catch to handle if they already exist
        try {
            Schema::table('gateways', function (Blueprint $table) {
                $table->index('waba_id', 'gateways_waba_id_index');
            });
        } catch (\Exception $e) {
            // Index already exists
        }

        try {
            Schema::table('gateways', function (Blueprint $table) {
                $table->index('phone_number_id', 'gateways_phone_number_id_index');
            });
        } catch (\Exception $e) {
            // Index already exists
        }

        try {
            Schema::table('gateways', function (Blueprint $table) {
                $table->index('health_status', 'gateways_health_status_index');
            });
        } catch (\Exception $e) {
            // Index already exists
        }

        try {
            Schema::table('gateways', function (Blueprint $table) {
                $table->index(['meta_configuration_id', 'status'], 'gateways_meta_config_status_index');
            });
        } catch (\Exception $e) {
            // Index already exists
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gateways', function (Blueprint $table) {
            // Drop foreign keys if they exist
            try {
                $table->dropForeign(['meta_configuration_id']);
            } catch (\Exception $e) {}

            try {
                $table->dropForeign(['onboarding_id']);
            } catch (\Exception $e) {}

            // Drop indexes
            try {
                $table->dropIndex('gateways_waba_id_index');
            } catch (\Exception $e) {}

            try {
                $table->dropIndex('gateways_phone_number_id_index');
            } catch (\Exception $e) {}

            try {
                $table->dropIndex('gateways_health_status_index');
            } catch (\Exception $e) {}

            try {
                $table->dropIndex('gateways_meta_config_status_index');
            } catch (\Exception $e) {}

            // Drop columns that exist
            $columnsToDrop = [
                'meta_configuration_id',
                'onboarding_id',
                'waba_id',
                'phone_number_id',
                'business_id',
                'verification_status',
                'quality_rating',
                'messaging_limit_tier',
                'account_mode',
                'token_expires_at',
                'token_refreshed_at',
                'health_status',
                'last_health_check_at',
                'health_check_history',
                'consecutive_failures',
                'webhook_subscribed',
                'webhook_subscribed_at',
            ];

            foreach ($columnsToDrop as $column) {
                if (Schema::hasColumn('gateways', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
