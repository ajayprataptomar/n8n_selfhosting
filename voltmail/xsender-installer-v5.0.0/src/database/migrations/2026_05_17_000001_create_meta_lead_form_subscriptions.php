<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-form mapping: a Facebook/Instagram Lead Ads form is subscribed and its
 * incoming leads are auto-imported into a contact group (and optionally also
 * enrolled into an automation workflow).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('meta_lead_form_subscriptions')) {
            return;
        }

        Schema::create('meta_lead_form_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('uid', 50)->unique();
            // users.id is INT UNSIGNED — must use unsignedInteger (see feedback_user_id_type memory)
            $table->unsignedInteger('user_id')->nullable()->index();
            $table->string('name', 150);
            $table->string('page_id', 64)->index();
            $table->string('page_name', 150)->nullable();
            $table->text('page_access_token'); // encrypted at app layer
            $table->string('form_id', 64)->index();
            $table->string('form_name', 150)->nullable();
            $table->unsignedBigInteger('contact_group_id')->nullable()->index();
            $table->unsignedBigInteger('workflow_id')->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->json('field_mapping')->nullable()->comment('Maps Meta lead field names → contact columns');
            $table->json('meta_data')->nullable();
            $table->unsignedInteger('leads_imported')->default(0);
            $table->timestamp('last_lead_at')->nullable();
            $table->timestamps();

            $table->unique(['page_id', 'form_id'], 'meta_lead_page_form_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_lead_form_subscriptions');
    }
};
