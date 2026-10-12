<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class QueueWorkCampaignEmail extends Command
{
    protected $signature = 'queue:work:campaign-email';
    protected $description = 'Process jobs from the campaign-email queue';

    public function handle()
    {
        Artisan::call('queue:work', [
            '--queue' => 'campaign-email',
            '--once' => false,
            '--tries' => 3,
            '--timeout' => 300,
        ]);

        $this->info('Processed jobs from campaign-email queue');
    }
}