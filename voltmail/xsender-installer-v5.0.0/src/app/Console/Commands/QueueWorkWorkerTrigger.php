<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class QueueWorkWorkerTrigger extends Command
{
    protected $signature = 'queue:work:worker-trigger';
    protected $description = 'Process one job from the worker-trigger queue';

    public function handle()
    {
        Artisan::call('queue:work', [
            '--queue' => 'worker-trigger',
            '--once' => false,
            '--tries' => 3,
            '--timeout' => 300,
        ]);

        $this->info('Processed one job from worker-trigger queue');
    }
}