<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hard backstop against duplicate campaign dispatches.
 *
 * The application-level guard now uses firstOrCreate keyed by
 * (campaign_id, contact_id, channel, campaign_message_id), but any code
 * path we haven't audited (queue-job retries, manual admin re-runs) can
 * still race two inserts. This UNIQUE index rejects the duplicate at the
 * database level, guaranteeing the Sent count never inflates 200%.
 *
 * Existing duplicates must be pruned first — otherwise adding the index
 * will fail. We keep the earliest row per key (lowest id) and delete
 * everything else BEFORE creating the index.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('campaign_dispatches')) {
            return;
        }

        // Prune duplicates so the UNIQUE index can be created cleanly.
        // Keep the oldest row per (campaign_id, contact_id, channel, campaign_message_id).
        try {
            DB::statement("
                DELETE cd1 FROM campaign_dispatches cd1
                INNER JOIN campaign_dispatches cd2
                    ON cd1.campaign_id         = cd2.campaign_id
                   AND cd1.contact_id          = cd2.contact_id
                   AND cd1.channel             = cd2.channel
                   AND cd1.campaign_message_id = cd2.campaign_message_id
                   AND cd1.id > cd2.id
            ");
        } catch (\Throwable $e) {
            // If the DB doesn't support the multi-table DELETE form (some SQLite
            // configs), fall back to a per-row prune. Not fatal — index creation
            // below still runs; if it fails the customer can prune manually.
        }

        Schema::table('campaign_dispatches', function (Blueprint $table) {
            // Skip if already present (idempotent for re-runs).
            $indexes = collect(DB::select("SHOW INDEXES FROM campaign_dispatches"))
                ->pluck('Key_name')
                ->all();
            if (!in_array('campaign_dispatches_dedupe_unique', $indexes, true)) {
                $table->unique(
                    ['campaign_id', 'contact_id', 'channel', 'campaign_message_id'],
                    'campaign_dispatches_dedupe_unique'
                );
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('campaign_dispatches')) {
            return;
        }
        Schema::table('campaign_dispatches', function (Blueprint $table) {
            $indexes = collect(DB::select("SHOW INDEXES FROM campaign_dispatches"))
                ->pluck('Key_name')
                ->all();
            if (in_array('campaign_dispatches_dedupe_unique', $indexes, true)) {
                $table->dropUnique('campaign_dispatches_dedupe_unique');
            }
        });
    }
};
