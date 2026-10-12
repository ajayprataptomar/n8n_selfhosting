<?php

namespace App\Console\Commands;

use App\Models\WarmupSchedule;
use App\Services\Warmup\WarmupImapService;
use Illuminate\Console\Command;

class WarmupVerifyCommand extends Command
{
    protected $signature = 'warmup:verify
                            {--schedule= : Specific warmup schedule ID to verify}';

    protected $description = 'Perform inbound IMAP mailbox verification to observe inbox and spam placement';

    public function handle(WarmupImapService $imapService): int
    {
        $scheduleId = $this->option('schedule');

        if ($scheduleId) {
            $schedule = WarmupSchedule::with('gateway')->find($scheduleId);
            if (!$schedule) {
                $this->error("Warmup schedule #{$scheduleId} not found.");
                return 1;
            }

            $this->info("Verifying IMAP placement for schedule #{$scheduleId}...");
            $res = $imapService->verifyDispatchesForSchedule($schedule);
            $this->info("Result: " . json_encode($res));
            return 0;
        }

        $this->info('Running IMAP verification for all active warmup mailboxes...');
        $results = $imapService->verifyActiveSchedules();

        $this->info("Completed verification for " . count($results) . " mailboxes.");
        foreach ($results as $id => $res) {
            $this->line("Schedule #{$id}: " . json_encode($res));
        }

        return 0;
    }
}
