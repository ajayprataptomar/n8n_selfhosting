<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class QueueWorkDispatchLogs extends Command
{
    protected $signature = 'queue:work:dispatch-logs';
    protected $description = 'Process jobs from the dispatch-logs queue';

    public function handle()
    {
        Artisan::call('queue:work', [
            '--queue' => 'dispatch-logs',
            '--once' => false,
            '--tries' => 3,
            '--timeout' => 300,
        ]);

        $this->info('Processed jobs from dispatch-logs queue');
    }
}