<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class QueueWorkContactImport extends Command
{
    protected $signature = 'queue:work:import-contacts';
    protected $description = 'Process jobs from the import-contacts queue';

    public function handle()
    {
        Artisan::call('queue:work', [
            '--queue' => 'import-contacts',
            '--once' => false,
            '--tries' => 3,
            '--timeout' => 300,
        ]);

        $this->info('Processed jobs from import-contacts queue');
    }
}