<?php

namespace App\Jobs;

use App\Models\WhatsappClientOnboarding;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CleanupStaleOnboardingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    /**
     * Execute the job.
     *
     * This job marks stale onboarding sessions as failed and cleans up old records.
     */
    public function handle(): void
    {
        // Mark initiated sessions older than 2 hours as failed (OAuth timeout)
        $staleInitiated = WhatsappClientOnboarding::where('onboarding_status', WhatsappClientOnboarding::STATUS_INITIATED)
            ->where('initiated_at', '<=', now()->subHours(2))
            ->get();

        foreach ($staleInitiated as $onboarding) {
            $onboarding->update([
                'onboarding_status' => WhatsappClientOnboarding::STATUS_FAILED,
                'error_message' => 'Session expired - OAuth flow not completed within 2 hours',
                'last_error_at' => now(),
            ]);

            Log::info("Marked stale onboarding as failed", ['onboarding_id' => $onboarding->id]);
        }

        // Mark in-progress sessions older than 24 hours as failed
        $staleInProgress = WhatsappClientOnboarding::whereIn('onboarding_status', [
            WhatsappClientOnboarding::STATUS_OAUTH_COMPLETED,
            WhatsappClientOnboarding::STATUS_PHONE_REGISTERED,
        ])
            ->where('initiated_at', '<=', now()->subHours(24))
            ->get();

        foreach ($staleInProgress as $onboarding) {
            $onboarding->update([
                'onboarding_status' => WhatsappClientOnboarding::STATUS_FAILED,
                'error_message' => 'Session expired - Onboarding not completed within 24 hours',
                'last_error_at' => now(),
            ]);

            Log::info("Marked incomplete onboarding as failed", ['onboarding_id' => $onboarding->id]);
        }

        // Delete very old failed records (older than 90 days)
        $deleted = WhatsappClientOnboarding::where('onboarding_status', WhatsappClientOnboarding::STATUS_FAILED)
            ->where('created_at', '<=', now()->subDays(90))
            ->delete();

        if ($deleted > 0) {
            Log::info("Deleted old failed onboarding records", ['count' => $deleted]);
        }

        // Log summary
        Log::info("Onboarding cleanup completed", [
            'stale_initiated_marked' => $staleInitiated->count(),
            'stale_in_progress_marked' => $staleInProgress->count(),
            'old_records_deleted' => $deleted,
        ]);
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("CleanupStaleOnboardingsJob failed", [
            'error' => $exception->getMessage()
        ]);
    }
}
