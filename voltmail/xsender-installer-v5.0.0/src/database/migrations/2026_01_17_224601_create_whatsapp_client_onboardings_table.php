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
        if (Schema::hasTable('whatsapp_client_onboardings')) {
            return;
        }

        Schema::create('whatsapp_client_onboardings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();

            // User reference (no foreign key for flexibility)
            $table->unsignedInteger('user_id')->nullable();

            $table->unsignedBigInteger('gateway_id')->nullable();
            $table->unsignedBigInteger('meta_configuration_id');

            // Onboarding status tracking
            $table->enum('onboarding_status', [
                'initiated',
                'oauth_completed',
                'pending_verification',
                'phone_registered',
                'webhook_subscribed',
                'verified',
                'rejected',
                'completed',
                'failed'
            ])->default('initiated');

            // WhatsApp Business Account Info
            $table->string('waba_id')->nullable()->comment('WhatsApp Business Account ID');
            $table->string('waba_name')->nullable();
            $table->string('waba_currency')->nullable();
            $table->string('waba_timezone_id')->nullable();
            $table->string('message_template_namespace')->nullable();

            // Phone Number Info
            $table->string('phone_number_id')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('display_name')->nullable();
            $table->string('verified_name')->nullable();
            $table->string('code_verification_status')->nullable();
            $table->string('quality_rating')->nullable()->comment('GREEN, YELLOW, RED');
            $table->string('messaging_limit_tier')->nullable()->comment('TIER_1K, TIER_10K, TIER_100K, UNLIMITED');

            // Business Verification
            $table->string('business_verification_status')->nullable()->comment('verified, pending, rejected');
            $table->string('account_review_status')->nullable();

            // OAuth & Token Data
            $table->text('user_access_token')->nullable()->comment('Encrypted');
            $table->timestamp('user_token_expires_at')->nullable();
            $table->json('permissions_granted')->nullable();
            $table->json('oauth_response')->nullable()->comment('Full OAuth callback response');

            // Error Tracking
            $table->json('error_log')->nullable();
            $table->text('last_error_message')->nullable();
            $table->integer('retry_count')->default(0);

            // Timestamps
            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('oauth_completed_at')->nullable();
            $table->timestamp('phone_registered_at')->nullable();
            $table->timestamp('webhook_subscribed_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_health_check_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes with custom short names
            $table->index(['onboarding_status', 'user_id'], 'idx_status_user');
            $table->index('waba_id', 'idx_waba');
            $table->index('phone_number_id', 'idx_phone');
            $table->index(['meta_configuration_id', 'onboarding_status'], 'idx_meta_status');
        });

        // Add foreign keys safely
        try {
            Schema::table('whatsapp_client_onboardings', function (Blueprint $table) {
                $table->foreign('gateway_id')->references('id')->on('gateways')->nullOnDelete();
                $table->foreign('meta_configuration_id')->references('id')->on('meta_configurations')->cascadeOnDelete();
            });
        } catch (\Exception $e) {
            // Foreign keys may fail if tables have different structures
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_client_onboardings');
    }
};
