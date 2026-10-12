<?php

namespace App\Jobs;

use App\Enums\Campaign\UnifiedCampaignStatus;
use App\Models\CampaignDispatch;
use App\Models\UnifiedCampaign;
use App\Services\Campaign\CampaignDispatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessUnifiedCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $campaignId;
    public int $batchSize;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct(int $campaignId, int $batchSize = 50)
    {
        $this->campaignId = $campaignId;
        $this->batchSize = $batchSize;
    }

    /**
     * Execute the job.
     */
    public function handle(CampaignDispatchService $dispatchService): void
    {
        $campaign = UnifiedCampaign::find($this->campaignId);

        if (!$campaign) {
            Log::warning("Campaign not found: {$this->campaignId}");
            return;
        }

        // Check if campaign is still running
        if ($campaign->status !== UnifiedCampaignStatus::RUNNING) {
            Log::info("Campaign {$this->campaignId} is no longer running, status: {$campaign->status->value}");
            return;
        }

        // Process a batch of dispatches
        $dispatches = CampaignDispatch::where('campaign_id', $this->campaignId)
            ->readyToProcess()
            ->limit($this->batchSize)
            ->get();

        if ($dispatches->isEmpty()) {
            // No more dispatches, check if campaign is complete
            $pending = CampaignDispatch::where('campaign_id', $this->campaignId)
                ->whereIn('status', ['pending', 'queued', 'processing'])
                ->count();

            if ($pending === 0) {
                $campaign->markAsCompleted();
                Log::info("Campaign {$this->campaignId} completed");
            }

            return;
        }

        Log::info("Processing {$dispatches->count()} dispatches for campaign {$this->campaignId}");

        foreach ($dispatches as $dispatch) {
            try {
                $dispatchService->processDispatch($dispatch);
            } catch (\Exception $e) {
                Log::error("Error processing dispatch {$dispatch->id}: " . $e->getMessage());
            }
        }

        // If there are more pending dispatches, dispatch another job
        $remaining = CampaignDispatch::where('campaign_id', $this->campaignId)
            ->readyToProcess()
            ->count();

        if ($remaining > 0) {
            self::dispatch($this->campaignId, $this->batchSize)
                ->delay(now()->addSeconds(2)); // Small delay to prevent overwhelming
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("ProcessUnifiedCampaignJob failed for campaign {$this->campaignId}: " . $exception->getMessage());
    }
}
