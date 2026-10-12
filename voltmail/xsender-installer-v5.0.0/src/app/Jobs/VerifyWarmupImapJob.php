<?php

namespace App\Jobs;

use App\Models\WarmupSchedule;
use App\Services\Warmup\WarmupImapService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class VerifyWarmupImapJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $scheduleId;
    public int $tries = 2;
    public int $timeout = 90;

    public function __construct(int $scheduleId)
    {
        $this->scheduleId = $scheduleId;
        $this->onQueue('warmup-imap');
    }

    public function handle(WarmupImapService $imapService): void
    {
        $schedule = WarmupSchedule::with('gateway')->find($this->scheduleId);

        if (!$schedule || $schedule->status !== 'active') {
            return;
        }

        $imapService->verifyDispatchesForSchedule($schedule);
    }
}
