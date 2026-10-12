<?php

namespace App\Console\Commands;

use App\Enums\Campaign\DispatchStatus;
use App\Enums\Campaign\UnifiedCampaignStatus;
use App\Models\UnifiedCampaign;
use App\Services\Campaign\CampaignDiagnosticService;
use App\Services\Campaign\CampaignDispatchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessUnifiedCampaigns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'campaigns:process
                            {--batch=50 : Number of dispatches per batch}
                            {--limit=10 : Maximum campaigns to process per run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process all unified campaigns (SMS, Email, WhatsApp) in a single cron job';

    /**
     * Execute the console command.
     */
    public function handle(CampaignDispatchService $dispatchService): int
    {
        // Heartbeat: lets the diagnostic UI prove the cron is actually firing.
        CampaignDiagnosticService::recordProcessorHeartbeat();

        $batchSize = (int) $this->option('batch');
        $limit = (int) $this->option('limit');

        $this->info('Processing unified campaigns...');

        // 1. Check for scheduled campaigns that should start
        $this->startScheduledCampaigns();

        // 2. Auto-recover dispatches stuck in PROCESSING/QUEUED before processing
        $this->recoverOrphanDispatches();

        // 3. Process running campaigns
        $runningCampaigns = UnifiedCampaign::running()
            ->orderBy('started_at', 'asc')
            ->limit($limit)
            ->get();

        $this->line("Found {$runningCampaigns->count()} running campaigns");

        foreach ($runningCampaigns as $campaign) {
            $this->processCampaign($campaign, $batchSize, $dispatchService);
        }

        $this->info('Campaign processing completed');

        return Command::SUCCESS;
    }

    /**
     * Reset dispatches stuck in PROCESSING/QUEUED for too long back to PENDING.
     *
     * A dispatch transitions PENDING -> PROCESSING in CampaignDispatchService::processDispatch().
     * If the worker dies mid-send (OOM, deploy, server restart), the row stays in PROCESSING forever
     * and the campaign stalls. This sweep recovers those silently before each run.
     */
    protected function recoverOrphanDispatches(): void
    {
        $cutoff = now()->subMinutes(CampaignDiagnosticService::ORPHAN_THRESHOLD_MINUTES);

        // CRITICAL: skip rows that already have `sent_at`. The old sweep
        // reset any PROCESSING row past the cutoff back to PENDING —
        // including rows whose SMTP send SUCCEEDED but crashed before the
        // markAsSent() UPDATE landed. Those recipients then received the
        // same email a second time and the Sent counter jumped 200%.
        // With this guard, a dispatch that has proof-of-send (sent_at set)
        // is left alone; only genuinely stuck rows (no sent_at) recover.
        $resetCount = DB::table('campaign_dispatches')
            ->whereIn('status', [DispatchStatus::PROCESSING->value, DispatchStatus::QUEUED->value])
            ->where('updated_at', '<', $cutoff)
            ->whereNull('sent_at')
            ->update([
                'status'     => DispatchStatus::PENDING->value,
                'updated_at' => now(),
            ]);

        // Rows that DID send but never got their status flipped: mark them
        // SENT retroactively so the campaign completes and the Sent count
        // matches reality. Idempotent — no re-dispatch, just status fix-up.
        $healedCount = DB::table('campaign_dispatches')
            ->whereIn('status', [DispatchStatus::PROCESSING->value, DispatchStatus::QUEUED->value])
            ->where('updated_at', '<', $cutoff)
            ->whereNotNull('sent_at')
            ->update([
                'status'     => DispatchStatus::SENT->value,
                'updated_at' => now(),
            ]);

        if ($resetCount > 0) {
            $this->line("Recovered {$resetCount} orphan dispatches stuck in PROCESSING/QUEUED");
            Log::info("ProcessUnifiedCampaigns: recovered {$resetCount} orphan dispatches");
        }
        if ($healedCount > 0) {
            $this->line("Healed {$healedCount} dispatches that sent but never got status update");
            Log::info("ProcessUnifiedCampaigns: healed {$healedCount} already-sent dispatches");
        }
    }

    /**
     * Start scheduled campaigns that are ready
     */
    protected function startScheduledCampaigns(): void
    {
        $readyToStart = UnifiedCampaign::readyToRun()->get();

        foreach ($readyToStart as $campaign) {
            $this->line("Starting scheduled campaign: {$campaign->name} (schedule_at: {$campaign->schedule_at}, now: " . now() . ")");

            $campaign->markAsStarted();

            Log::info("Scheduled campaign {$campaign->id} started: {$campaign->name}", [
                'schedule_at' => $campaign->schedule_at?->toDateTimeString(),
                'now' => now()->toDateTimeString(),
                'app_timezone' => config('app.timezone'),
            ]);
        }

        // Log scheduled campaigns that are still waiting
        $waiting = UnifiedCampaign::where('status', UnifiedCampaignStatus::SCHEDULED)
            ->where('schedule_at', '>', now())
            ->get();

        foreach ($waiting as $campaign) {
            $this->line("Waiting for schedule: {$campaign->name} (schedule_at: {$campaign->schedule_at})");
        }

        if ($readyToStart->count() > 0) {
            $this->info("Started {$readyToStart->count()} scheduled campaigns");
        }
    }

    /**
     * Process a single campaign - dispatches are processed directly (no queue dependency)
     */
    protected function processCampaign(UnifiedCampaign $campaign, int $batchSize, CampaignDispatchService $dispatchService): void
    {
        $this->line("Processing campaign: {$campaign->name} (ID: {$campaign->id})");

        // Get pending dispatches ready to process
        $dispatches = $campaign->dispatches()
            ->readyToProcess()
            ->with(['campaignMessage', 'contact', 'gateway'])
            ->limit($batchSize)
            ->get();

        if ($dispatches->isEmpty()) {
            // Check if campaign should be completed
            $pendingCount = $campaign->dispatches()
                ->whereIn('status', [DispatchStatus::PENDING, DispatchStatus::QUEUED, DispatchStatus::PROCESSING])
                ->count();

            if ($pendingCount === 0) {
                $campaign->markAsCompleted();
                $this->line("Campaign {$campaign->id} completed - no more dispatches");
            } else {
                $this->line("Campaign {$campaign->id} has {$pendingCount} dispatches still in progress");
            }
            return;
        }

        $this->line("Processing {$dispatches->count()} dispatches for campaign {$campaign->id}");

        $succeeded = 0;
        $failed = 0;

        foreach ($dispatches as $dispatch) {
            try {
                if ($dispatchService->processDispatch($dispatch)) {
                    $succeeded++;
                } else {
                    $failed++;
                }
            } catch (\Exception $e) {
                $failed++;
                Log::error("Error processing dispatch {$dispatch->id}: " . $e->getMessage());
            }
        }

        $this->line("Campaign {$campaign->id}: {$succeeded} sent, {$failed} failed");
    }
}
