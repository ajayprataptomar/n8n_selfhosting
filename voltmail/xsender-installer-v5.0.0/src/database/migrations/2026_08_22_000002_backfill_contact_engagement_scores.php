<?php

use App\Models\ContactEngagement;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contact_engagements')) {
            return;
        }

        // Recompute engagement_score for every row that has activity but a
        // zero/stale score. Prior versions only set the score inside the
        // batch analyzer, so installs that never ran that command display
        // "Avg Engagement 0.0%" even with real opens/clicks logged.
        ContactEngagement::query()
            ->where(function ($q) {
                $q->whereNull('analyzed_at')
                  ->orWhere('engagement_score', 0);
            })
            ->where(function ($q) {
                $q->where('total_sent', '>', 0)
                  ->orWhere('total_delivered', '>', 0)
                  ->orWhere('total_opened', '>', 0)
                  ->orWhere('total_clicked', '>', 0)
                  ->orWhere('total_replied', '>', 0);
            })
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    try {
                        // Downstream implies upstream — patch counters so the
                        // rate math has a non-zero denominator on rows where
                        // send-side tracking never fed ContactEngagement.
                        $dirty = false;
                        if ((int) $row->total_replied > (int) $row->total_opened) {
                            $row->total_opened = (int) $row->total_replied; $dirty = true;
                        }
                        if ((int) $row->total_clicked > (int) $row->total_opened) {
                            $row->total_opened = (int) $row->total_clicked; $dirty = true;
                        }
                        if ((int) $row->total_opened > (int) $row->total_delivered) {
                            $row->total_delivered = (int) $row->total_opened; $dirty = true;
                        }
                        if ((int) $row->total_delivered > (int) $row->total_sent) {
                            $row->total_sent = (int) $row->total_delivered; $dirty = true;
                        }
                        if ($dirty) {
                            $row->saveQuietly();
                        }
                        $row->calculateEngagementScore();
                    } catch (\Throwable $e) {
                        // Skip malformed rows so a single bad record cannot abort the whole backfill.
                    }
                }
            });
    }

    public function down(): void
    {
        // No-op; recalculated scores are correct.
    }
};
