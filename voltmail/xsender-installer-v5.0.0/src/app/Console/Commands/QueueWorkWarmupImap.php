<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class QueueWorkWarmupImap extends Command
{
    protected $signature = 'queue:work:warmup-imap {--stop-when-empty : Stop when queue is empty} {--max-jobs=0 : Maximum jobs to process} {--max-time=0 : Maximum seconds to run}';
    protected $description = 'Dedicated worker for inbound email warmup IMAP verification queue';

    public function handle()
    {
        $params = [
            '--queue'   => 'warmup-imap',
            '--tries'   => 1,
            '--timeout' => 120,
            '--memory'  => 128,
        ];

        if ($this->option('stop-when-empty')) {
            $params['--stop-when-empty'] = true;
        }

        $maxJobs = (int) $this->option('max-jobs');
        if ($maxJobs > 0) {
            $params['--max-jobs'] = $maxJobs;
        }

        $maxTime = (int) $this->option('max-time');
        if ($maxTime > 0) {
            $params['--max-time'] = $maxTime;
        }

        Artisan::call('queue:work', $params);

        $this->info('Warmup IMAP worker terminated safely.');
    }
}
