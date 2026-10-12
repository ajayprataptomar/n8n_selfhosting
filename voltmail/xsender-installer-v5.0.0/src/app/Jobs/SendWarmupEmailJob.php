<?php

namespace App\Jobs;

use App\Models\WarmupDispatch;
use App\Services\Warmup\WarmupOutboxDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWarmupEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $dispatchId;
    public int $tries = 2;
    public int $timeout = 60;

    public function __construct(int $dispatchId)
    {
        $this->dispatchId = $dispatchId;
        $this->onQueue('warmup-send');
    }

    public function handle(WarmupOutboxDispatcher $dispatcher): void
    {
        $dispatch = WarmupDispatch::find($this->dispatchId);

        if (!$dispatch || $dispatch->status !== WarmupDispatch::STATUS_CLAIMED) {
            return;
        }

        $dispatcher->dispatchSlot($dispatch);
    }
}
