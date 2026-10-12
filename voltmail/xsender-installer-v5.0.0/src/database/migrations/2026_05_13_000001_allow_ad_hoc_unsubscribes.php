<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Allow ad-hoc (non-campaign) email unsubscribes by making campaign_id nullable
 * on campaign_unsubscribes. Direct sends from the "Messages → Send Email" flow
 * still need to honor List-Unsubscribe / CAN-SPAM but have no campaign reference.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('campaign_unsubscribes')) {
            return;
        }

        try {
            Schema::table('campaign_unsubscribes', function (Blueprint $table) {
                $table->unsignedBigInteger('campaign_id')->nullable()->change();
            });
        } catch (\Throwable $e) {
            // doctrine/dbal may not be present; some installs use raw SQL fallback
            try {
                \DB::statement('ALTER TABLE `campaign_unsubscribes` MODIFY `campaign_id` BIGINT UNSIGNED NULL');
            } catch (\Throwable $inner) {
                // schema reconciler will pick it up on next health-check pass
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('campaign_unsubscribes')) {
            return;
        }

        try {
            Schema::table('campaign_unsubscribes', function (Blueprint $table) {
                $table->unsignedBigInteger('campaign_id')->nullable(false)->change();
            });
        } catch (\Throwable $e) {
            // rollback is best-effort
        }
    }
};
