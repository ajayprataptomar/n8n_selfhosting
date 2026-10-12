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
        if (Schema::hasTable('meta_configurations')) {
            return;
        }

        Schema::create('meta_configurations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->string('name');
            $table->string('config_id')->nullable()->comment('Meta Configuration ID for Tech Provider - MANDATORY for 2025');
            $table->string('app_id');
            $table->text('app_secret')->comment('Encrypted');
            $table->string('system_user_id')->nullable();
            $table->text('system_user_token')->nullable()->comment('Encrypted System User Access Token');
            $table->timestamp('system_user_token_expires_at')->nullable();
            $table->string('business_manager_id')->nullable();
            $table->string('tech_provider_id')->nullable()->comment('Tech Provider Business ID');
            $table->string('solution_id')->nullable()->comment('Meta Solution ID');
            $table->string('webhook_verify_token')->nullable();
            $table->string('webhook_callback_url')->nullable();
            $table->json('permissions')->nullable()->comment('Allowed OAuth scopes');
            $table->json('allowed_features')->nullable()->comment('whatsapp_embedded_signup, etc.');
            $table->string('api_version')->default('v21.0');
            $table->enum('environment', ['sandbox', 'production'])->default('production');
            $table->enum('status', ['active', 'inactive', 'pending_verification'])->default('pending_verification');
            $table->boolean('is_default')->default(false);
            $table->json('rate_limits')->nullable()->comment('API rate limit configuration');
            $table->json('meta_response')->nullable()->comment('Raw Meta API response data');
            $table->text('setup_instructions')->nullable()->comment('Admin notes for setup');
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'is_default']);
            $table->index('config_id');
            $table->index('app_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meta_configurations');
    }
};
