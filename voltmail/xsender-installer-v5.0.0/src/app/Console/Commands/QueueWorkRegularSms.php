<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class QueueWorkRegularSms extends Command
{
    protected $signature = 'queue:work:regular-sms';
    protected $description = 'Process jobs from the regular-sms queue';

    public function handle()
    {
        Artisan::call('queue:work', [
            '--queue' => 'regular-sms',
            '--once' => false,
            '--tries' => 3,
            '--timeout' => 300,
        ]);

        $this->info('Processed jobs from regular-sms queue');
    }
}