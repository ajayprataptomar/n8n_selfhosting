<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;

class TriggerQueueWorker implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $targetQueue;

    /**
     * Create a new job instance.
     *
     * @param string $targetQueue
     */
    public function __construct(string $targetQueue)
    {
        $this->targetQueue = $targetQueue;
        $this->onQueue('worker-trigger');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Artisan::call('queue:work', [
            '--queue' => $this->targetQueue,
            '--once' => false,
            '--tries' => 3,
            '--timeout' => 90,
        ]);
    }
}