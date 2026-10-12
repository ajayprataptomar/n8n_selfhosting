<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Raw open/click event log for bot-filtered insights (v4.8).
 *
 * Every tracking-pixel hit and every click-redirect logs a row here BEFORE
 * we decide whether to advance CampaignDispatch.status. That lets us:
 *   - Show `raw_open_count` vs `real_open_count` (bot-filtered) on Insights.
 *   - Audit exactly which user-agents are hitting the pixel (helps refine
 *     the bot allowlist over time).
 *   - Prevent a single Gmail image-prefetcher from advancing a dispatch's
 *     status all the way to CLICKED before the human ever sees the email.
 *
 * Non-destructive. Safe to run on existing v4.7.x installs.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('email_events')) {
            return;
        }

        Schema::create('email_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_dispatch_id')->nullable()->index();
            $table->unsignedBigInteger('dispatch_log_id')->nullable()->index();
            $table->unsignedBigInteger('contact_id')->nullable()->index();
            $table->unsignedInteger('user_id')->nullable()->index(); // INT UNSIGNED to match users.id (see memory: feedback_user_id_type)
            $table->enum('event_type', ['open', 'click'])->index();
            $table->string('user_agent', 500)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->boolean('is_bot')->default(false)->index();
            $table->string('bot_name', 100)->nullable();
            $table->string('url', 2048)->nullable(); // populated for click events
            $table->timestamp('created_at')->nullable()->index();
            $table->timestamp('updated_at')->nullable();

            $table->index(['campaign_dispatch_id', 'event_type'], 'email_events_dispatch_type_idx');
            $table->index(['contact_id', 'event_type'], 'email_events_contact_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_events');
    }
};
