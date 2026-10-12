<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class QueueWorkVerifyEmail extends Command
{
    protected $signature = 'queue:work:verify-email';
    protected $description = 'Process jobs from the verify-email queue';

    public function handle()
    {
        Artisan::call('queue:work', [
            '--queue' => 'verify-email',
            '--once' => false,
            '--tries' => 3,
            '--timeout' => 300,
        ]);

        $this->info('Processed jobs from verify-email queue');
    }
}