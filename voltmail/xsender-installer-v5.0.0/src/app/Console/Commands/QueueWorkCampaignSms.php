<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class QueueWorkCampaignSms extends Command
{
    protected $signature = 'queue:work:campaign-sms';
    protected $description = 'Process jobs from the campaign-sms queue';

    public function handle()
    {
        Artisan::call('queue:work', [
            '--queue' => 'campaign-sms',
            '--once' => false,
            '--tries' => 3,
            '--timeout' => 300,
        ]);

        $this->info('Processed jobs from campaign-sms queue');
    }
}