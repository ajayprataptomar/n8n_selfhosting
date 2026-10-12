<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Google Ads Lead Form Extensions deliver leads via webhook with a shared "key"
 * acting as the HMAC. Each subscription is keyed by `customer_id + form_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('google_ads_lead_subscriptions')) return;

        Schema::create('google_ads_lead_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('uid', 50)->unique();
            $table->unsignedInteger('user_id')->nullable()->index();
            $table->string('name', 150);
            $table->string('customer_id', 32)->index();   // Google Ads customer ID
            $table->string('form_id', 64)->index();
            $table->string('shared_key');                 // encrypted at app layer (HMAC validator)
            $table->unsignedBigInteger('contact_group_id')->nullable()->index();
            $table->unsignedBigInteger('workflow_id')->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('leads_imported')->default(0);
            $table->timestamp('last_lead_at')->nullable();
            $table->json('field_mapping')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'form_id'], 'google_ads_lead_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_ads_lead_subscriptions');
    }
};
