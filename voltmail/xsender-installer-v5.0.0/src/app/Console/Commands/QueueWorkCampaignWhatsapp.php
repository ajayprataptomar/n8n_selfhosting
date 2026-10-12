<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class QueueWorkCampaignWhatsapp extends Command
{
    protected $signature = 'queue:work:campaign-whatsapp';
    protected $description = 'Process jobs from the campaign-whatsapp queue';

    public function handle()
    {
        Artisan::call('queue:work', [
            '--queue' => 'campaign-whatsapp',
            '--once' => false,
            '--tries' => 3,
            '--timeout' => 300,
        ]);

        $this->info('Processed jobs from campaign-whatsapp queue');
    }
}