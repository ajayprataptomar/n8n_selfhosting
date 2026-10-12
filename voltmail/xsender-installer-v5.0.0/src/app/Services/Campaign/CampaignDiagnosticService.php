<?php

namespace App\Services\Campaign;

use App\Enums\Campaign\DispatchStatus;
use App\Enums\Campaign\UnifiedCampaignStatus;
use App\Models\Gateway;
use App\Models\UnifiedCampaign;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class CampaignDiagnosticService
{
    public const STUCK_THRESHOLD_MINUTES = 2;
    public const ORPHAN_THRESHOLD_MINUTES = 5;
    public const PROCESSOR_HEALTHY_THRESHOLD_MINUTES = 5;
    public const HEARTBEAT_FILE = 'campaign_processor_heartbeat.txt';

    public function diagnose(UnifiedCampaign $campaign): array
    {
        $checks = [
            'campaign'   => $this->campaignSummary($campaign),
            'processor'  => $this->processorHealth(),
            'queue'      => $this->queueHealth(),
            'dispatches' => $this->dispatchesHealth($campaign),
            'gateways'   => $this->gatewaysHealth($campaign),
            'errors'     => $this->recentErrors($campaign),
        ];

        $checks['overall'] = $this->overallVerdict($checks);

        return $checks;
    }

    public function retryOrphans(UnifiedCampaign $campaign): int
    {
        $cutoff = now()->subMinutes(self::ORPHAN_THRESHOLD_MINUTES);

        return $campaign->dispatches()
            ->whereIn('status', [DispatchStatus::PROCESSING, DispatchStatus::QUEUED])
            ->where('updated_at', '<', $cutoff)
            ->update([
                'status'     => DispatchStatus::PENDING,
                'updated_at' => now(),
            ]);
    }

    public static function recordProcessorHeartbeat(): void
    {
        $path = storage_path('app/' . self::HEARTBEAT_FILE);
        @file_put_contents($path, (string) time());
    }

    private function campaignSummary(UnifiedCampaign $campaign): array
    {
        $startedMinutesAgo = $campaign->started_at
            ? (int) $campaign->started_at->diffInMinutes(now())
            : null;

        return [
            'id'                  => $campaign->id,
            'status'              => $campaign->status->value,
            'total_contacts'      => $campaign->total_contacts,
            'processed_contacts'  => $campaign->processed_contacts,
            'progress_percentage' => $campaign->getProgressPercentage(),
            'started_at'          => $campaign->started_at?->toDateTimeString(),
            'started_minutes_ago' => $startedMinutesAgo,
            'is_running'          => $campaign->status === UnifiedCampaignStatus::RUNNING,
            'is_stuck_at_zero'    => $campaign->status === UnifiedCampaignStatus::RUNNING
                && $campaign->processed_contacts === 0
                && ($startedMinutesAgo ?? 0) >= self::STUCK_THRESHOLD_MINUTES,
        ];
    }

    /**
     * v4.9 — visibility widened from private → public. Neither method takes a
     * campaign argument, so `SystemHealthService` (and any future aggregator)
     * can call them directly without needing a `UnifiedCampaign` instance.
     */
    public function processorHealth(): array
    {
        $heartbeatPath = storage_path('app/' . self::HEARTBEAT_FILE);
        $lastRun = File::exists($heartbeatPath) ? (int) File::get($heartbeatPath) : null;

        $minutesSinceLastRun = $lastRun ? (int) ((time() - $lastRun) / 60) : null;

        $healthy = $minutesSinceLastRun !== null
            && $minutesSinceLastRun <= self::PROCESSOR_HEALTHY_THRESHOLD_MINUTES;

        if ($lastRun === null) {
            $message = translate('Campaign processor has never run. Configure cron to execute "php artisan campaigns:process" every minute.');
        } elseif (!$healthy) {
            $message = translate('Campaign processor last ran ' . $minutesSinceLastRun . ' minutes ago. It should run every minute.');
        } else {
            $message = translate('Campaign processor is running normally.');
        }

        return [
            'healthy'                => $healthy,
            'last_run_unix'          => $lastRun,
            'last_run_at'            => $lastRun ? date('Y-m-d H:i:s', $lastRun) : null,
            'minutes_since_last_run' => $minutesSinceLastRun,
            'message'                => $message,
        ];
    }

    /**
     * v4.9 — visibility widened (see processorHealth() rationale).
     */
    public function queueHealth(): array
    {
        try {
            $failedCount = DB::table('failed_jobs')->count();
            $pendingJobs = DB::table('jobs')->count();

            return [
                'healthy'      => $failedCount === 0,
                'failed_jobs'  => $failedCount,
                'pending_jobs' => $pendingJobs,
                'message'      => $failedCount > 0
                    ? translate("{$failedCount} failed jobs in queue. Check your queue worker.")
                    : translate('Queue looks healthy.'),
            ];
        } catch (\Throwable $e) {
            return [
                'healthy' => true,
                'message' => translate('Queue tables not available (using sync driver).'),
            ];
        }
    }

    private function dispatchesHealth(UnifiedCampaign $campaign): array
    {
        $cutoff = now()->subMinutes(self::ORPHAN_THRESHOLD_MINUTES);

        $statusCounts = $campaign->dispatches()
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $orphanCount = $campaign->dispatches()
            ->whereIn('status', [DispatchStatus::PROCESSING, DispatchStatus::QUEUED])
            ->where('updated_at', '<', $cutoff)
            ->count();

        $totalDispatches = array_sum($statusCounts);

        if ($totalDispatches === 0) {
            $message = translate('No dispatch records exist for this campaign — they may have failed to create. Try cancelling and relaunching.');
        } elseif ($orphanCount > 0) {
            $message = translate("{$orphanCount} dispatches stuck in PROCESSING/QUEUED for over " . self::ORPHAN_THRESHOLD_MINUTES . ' minutes. Use the "Retry Stuck Dispatches" button.');
        } else {
            $message = translate("{$totalDispatches} dispatch records, none stuck.");
        }

        return [
            'healthy'          => $orphanCount === 0 && $totalDispatches > 0,
            'total'            => $totalDispatches,
            'status_breakdown' => $statusCounts,
            'orphan_count'     => $orphanCount,
            'message'          => $message,
        ];
    }

    private function gatewaysHealth(UnifiedCampaign $campaign): array
    {
        $gatewayIds = $campaign->messages()->pluck('gateway_id')->filter()->unique();

        if ($gatewayIds->isEmpty()) {
            return [
                'healthy'  => false,
                'message'  => translate('Campaign has no gateways assigned to its messages.'),
                'gateways' => [],
            ];
        }

        $gateways = Gateway::whereIn('id', $gatewayIds)->get(['id', 'name', 'status', 'channel']);
        $inactive = $gateways->where('status', '!=', 'active')->values();

        return [
            'healthy'  => $inactive->isEmpty(),
            'gateways' => $gateways->map(fn ($g) => [
                'id'      => $g->id,
                'name'    => $g->name,
                'channel' => $g->channel,
                'status'  => $g->status,
            ])->all(),
            'inactive_count' => $inactive->count(),
            'message' => $inactive->isEmpty()
                ? translate('All gateways are active.')
                : translate("{$inactive->count()} gateway(s) inactive. Activate them or replace in the campaign messages."),
        ];
    }

    private function recentErrors(UnifiedCampaign $campaign): array
    {
        $errors = $campaign->dispatches()
            ->where('status', DispatchStatus::FAILED)
            ->whereNotNull('error_message')
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get(['id', 'channel', 'error_message', 'updated_at'])
            ->map(fn ($d) => [
                'id'      => $d->id,
                'channel' => $d->channel,
                'error'   => mb_strimwidth((string) $d->error_message, 0, 200, '…'),
                'when'    => $d->updated_at?->diffForHumans(),
            ])
            ->all();

        return [
            'count'  => count($errors),
            'errors' => $errors,
        ];
    }

    private function overallVerdict(array $checks): array
    {
        $issues = [];

        foreach (['processor', 'queue', 'dispatches', 'gateways'] as $section) {
            if (isset($checks[$section]['healthy']) && !$checks[$section]['healthy']) {
                $issues[] = $checks[$section]['message'];
            }
        }

        return [
            'healthy' => empty($issues),
            'issues'  => $issues,
        ];
    }
}
