<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class QueueWorkRegularEmail extends Command
{
    protected $signature = 'queue:work:regular-email';
    protected $description = 'Process jobs from the regular-email queue';

    public function handle()
    {
        Artisan::call('queue:work', [
            '--queue' => 'regular-email',
            '--once' => false,
            '--tries' => 3,
            '--timeout' => 300,
        ]);

        $this->info('Processed jobs from regular-email queue');
    }
}