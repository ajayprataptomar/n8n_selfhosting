<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class QueueWorkRegularWhatsapp extends Command
{
    protected $signature = 'queue:work:regular-whatsapp';
    protected $description = 'Process jobs from the regular-whatsapp queue';

    public function handle()
    {
        Artisan::call('queue:work', [
            '--queue' => 'regular-whatsapp',
            '--once' => false,
            '--tries' => 3,
            '--timeout' => 300,
        ]);

        $this->info('Processed jobs from regular-whatsapp queue');
    }
}