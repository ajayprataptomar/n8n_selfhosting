<?php

namespace App\Console\Commands;

use App\Services\Warmup\WarmupSchedulerService;
use Illuminate\Console\Command;

class WarmupScheduleCommand extends Command
{
    protected $signature = 'warmup:schedule
                            {--date= : Target date in YYYY-MM-DD format (defaults to today)}';

    protected $description = 'Generate daily warmup slots and advance quota ramp-up for active mailboxes';

    public function handle(WarmupSchedulerService $scheduler): int
    {
        $date = $this->option('date');
        $this->info('Running daily warmup scheduling...');

        $result = $scheduler->scheduleDailyDispatches($date);

        $this->info("Date: {$result['date']}");
        $this->info("Active Schedules Processed: {$result['schedules_processed']}");
        $this->info("Slots Generated: {$result['slots_generated']}");

        return 0;
    }
}
