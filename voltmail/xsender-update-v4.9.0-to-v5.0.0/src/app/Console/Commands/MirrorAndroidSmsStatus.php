<?php

namespace App\Console\Commands;

use App\Enums\Campaign\CampaignChannel;
use App\Enums\Campaign\DispatchStatus;
use App\Enums\System\CommunicationStatusEnum;
use App\Models\CampaignDispatch;
use App\Models\DispatchLog;
use App\Services\Campaign\CampaignDispatchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Mirrors the legacy dispatch_logs status back into campaign_dispatches for
 * Android-pull SMS sends. The Android device updates dispatch_logs via the
 * existing /api/logs/sms/update-statuses endpoint — this command picks up
 * those changes and syncs them to the unified campaign side, then bumps the
 * unified campaign's processed_contacts counter so progress bars advance.
 */
class MirrorAndroidSmsStatus extends Command
{
    protected $signature = 'campaigns:mirror-android-sms
                            {--limit=200 : Max rows to mirror per run}';

    protected $description = 'Mirror Android dispatch_logs status into unified campaign_dispatches';

    public function handle(CampaignDispatchService $dispatchService): int
    {
        $limit = (int) $this->option('limit');

        $queued = CampaignDispatch::where('channel', CampaignChannel::SMS->value)
            ->where('status', DispatchStatus::QUEUED->value)
            ->whereNotNull('dispatch_log_id')
            ->orderBy('id', 'asc')
            ->limit($limit)
            ->get();

        if ($queued->isEmpty()) {
            $this->line('No Android-queued dispatches to mirror.');
            return Command::SUCCESS;
        }

        $logsById = DispatchLog::whereIn('id', $queued->pluck('dispatch_log_id'))
            ->get(['id', 'status', 'response_message', 'sent_at', 'processed_at'])
            ->keyBy('id');

        $mirrored = 0;
        $failed   = 0;

        foreach ($queued as $dispatch) {
            $log = $logsById->get($dispatch->dispatch_log_id);
            if (!$log) {
                continue;
            }

            $logStatus = $log->status instanceof CommunicationStatusEnum
                ? $log->status->value
                : (string) $log->status;

            // Map legacy dispatch_log status → unified campaign_dispatch status
            switch ($logStatus) {
                case CommunicationStatusEnum::DELIVERED->value:
                    $dispatch->update([
                        'status'  => DispatchStatus::SENT,
                        'sent_at' => $log->sent_at ?? $log->processed_at ?? now(),
                    ]);
                    $this->bumpCampaignStats($dispatch, 'sent');
                    $mirrored++;
                    break;

                case CommunicationStatusEnum::FAIL->value:
                case CommunicationStatusEnum::CANCEL->value:
                    $dispatch->update([
                        'status'        => DispatchStatus::FAILED,
                        'error_message' => $this->extractErrorMessage($log),
                    ]);
                    $this->bumpCampaignStats($dispatch, 'failed');
                    $failed++;
                    break;

                case CommunicationStatusEnum::PROCESSING->value:
                    if ($dispatch->status !== DispatchStatus::PROCESSING) {
                        $dispatch->update(['status' => DispatchStatus::PROCESSING]);
                    }
                    break;

                // PENDING / SCHEDULE: device hasn't picked it up yet, leave as QUEUED
                default:
                    break;
            }
        }

        $this->info("Mirrored {$mirrored} sent, {$failed} failed.");

        return Command::SUCCESS;
    }

    protected function bumpCampaignStats(CampaignDispatch $dispatch, string $outcome): void
    {
        try {
            $campaign = $dispatch->campaign;
            if (!$campaign) return;

            // Increment unified campaign processed counter so progress UI advances
            $campaign->incrementProcessed();

            // Per-channel stats update (mirrors what CampaignDispatchService does for sync sends)
            $stats = $campaign->stats ?? [];
            $channel = CampaignChannel::SMS->value;
            $stats[$channel] = $stats[$channel] ?? [];
            $stats[$channel][$outcome] = ($stats[$channel][$outcome] ?? 0) + 1;
            $campaign->update(['stats' => $stats]);
        } catch (\Throwable $e) {
            Log::warning('MirrorAndroidSmsStatus stats bump failed: ' . $e->getMessage(), [
                'dispatch_id' => $dispatch->id,
            ]);
        }
    }

    protected function extractErrorMessage(DispatchLog $log): string
    {
        $raw = $log->response_message;
        if (is_array($raw)) {
            return $raw['error'] ?? $raw['message'] ?? json_encode($raw);
        }
        return (string) ($raw ?? 'Android device reported failure');
    }
}
