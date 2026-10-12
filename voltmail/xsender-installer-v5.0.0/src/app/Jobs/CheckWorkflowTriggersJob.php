<?php

namespace App\Jobs;

use App\Models\Automation\WorkflowExecution;
use App\Services\Automation\TriggerHandlerService;
use App\Services\Automation\WorkflowExecutionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckWorkflowTriggersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->onQueue(config('queue.pipes.automation', 'automation'));
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Starting workflow trigger check");

        $triggerService = new TriggerHandlerService();
        $executionService = new WorkflowExecutionService();

        try {
            // 1. Process scheduled triggers
            $scheduleResults = $triggerService->handleScheduleTrigger();
            Log::info("Schedule triggers processed", $scheduleResults);

            // 2. Process birthday triggers (daily - checks date_of_birth in contacts)
            $birthdayResults = $triggerService->handleBirthdayTrigger();
            Log::info("Birthday triggers processed", $birthdayResults);

            // 3. Process no-response triggers
            $noResponseResults = $triggerService->handleNoResponseTrigger();
            Log::info("No-response triggers processed", $noResponseResults);

            // 4. Resume waiting executions
            $resumed = $executionService->resumeWaitingExecutions();
            Log::info("Resumed waiting executions", ['count' => $resumed]);

            // 5. Dispatch jobs for resumed executions
            $readyExecutions = WorkflowExecution::where('status', 'running')
                ->whereNotNull('current_node_id')
                ->pluck('id');

            foreach ($readyExecutions as $executionId) {
                ProcessWorkflowNodeJob::dispatch($executionId);
            }

            Log::info("Dispatched jobs for running executions", ['count' => $readyExecutions->count()]);

        } catch (\Exception $e) {
            Log::error("Workflow trigger check failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return ['automation', 'workflow-triggers'];
    }
}
