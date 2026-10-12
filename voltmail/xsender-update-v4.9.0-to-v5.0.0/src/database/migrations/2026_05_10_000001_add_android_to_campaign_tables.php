<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds Android-pull-dispatcher columns to the unified campaign tables so a campaign
 * message can target an Android Session (pull-based SMS) instead of an API gateway.
 *
 *  campaign_messages.android_session_id  → which Android Session sends this message
 *  campaign_dispatches.android_sim_id    → which SIM picks up the row at send time
 *  campaign_dispatches.dispatch_log_id   → legacy dispatch_logs row used as the
 *                                          handoff to the Android device
 *
 * gateway_id stays nullable (already is). Exactly one of (gateway_id, android_sim_id)
 * is expected to be set per row.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('campaign_messages')
            && !Schema::hasColumn('campaign_messages', 'android_session_id')) {
            Schema::table('campaign_messages', function (Blueprint $table) {
                $table->unsignedBigInteger('android_session_id')
                    ->nullable()
                    ->after('gateway_id')
                    ->index();
            });

            try {
                Schema::table('campaign_messages', function (Blueprint $table) {
                    $table->foreign('android_session_id')
                        ->references('id')
                        ->on('android_sessions')
                        ->nullOnDelete();
                });
            } catch (\Throwable $e) {
                // FK may already exist or schema reconciler will handle it
            }
        }

        if (Schema::hasTable('campaign_dispatches')) {
            if (!Schema::hasColumn('campaign_dispatches', 'android_sim_id')) {
                Schema::table('campaign_dispatches', function (Blueprint $table) {
                    $table->unsignedBigInteger('android_sim_id')
                        ->nullable()
                        ->after('gateway_id')
                        ->index();
                });

                try {
                    Schema::table('campaign_dispatches', function (Blueprint $table) {
                        $table->foreign('android_sim_id')
                            ->references('id')
                            ->on('android_sims')
                            ->nullOnDelete();
                    });
                } catch (\Throwable $e) {
                    // FK may fail
                }
            }

            if (!Schema::hasColumn('campaign_dispatches', 'dispatch_log_id')) {
                Schema::table('campaign_dispatches', function (Blueprint $table) {
                    $table->unsignedBigInteger('dispatch_log_id')
                        ->nullable()
                        ->after('android_sim_id')
                        ->index();
                });
                // No FK to dispatch_logs — the legacy table may be cleaned independently
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('campaign_dispatches')) {
            Schema::table('campaign_dispatches', function (Blueprint $table) {
                if (Schema::hasColumn('campaign_dispatches', 'dispatch_log_id')) {
                    $table->dropIndex(['dispatch_log_id']);
                    $table->dropColumn('dispatch_log_id');
                }
                if (Schema::hasColumn('campaign_dispatches', 'android_sim_id')) {
                    try { $table->dropForeign(['android_sim_id']); } catch (\Throwable $e) {}
                    $table->dropIndex(['android_sim_id']);
                    $table->dropColumn('android_sim_id');
                }
            });
        }

        if (Schema::hasTable('campaign_messages') && Schema::hasColumn('campaign_messages', 'android_session_id')) {
            Schema::table('campaign_messages', function (Blueprint $table) {
                try { $table->dropForeign(['android_session_id']); } catch (\Throwable $e) {}
                $table->dropIndex(['android_session_id']);
                $table->dropColumn('android_session_id');
            });
        }
    }
};
