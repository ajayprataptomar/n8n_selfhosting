<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Models\Automation\AutomationWorkflow;
use App\Services\Automation\WorkflowExecutionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class StartWorkflowExecutionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;
    public int $timeout = 60;

    protected int $workflowId;
    protected int $contactId;
    protected array $triggerData;

    /**
     * Create a new job instance.
     */
    public function __construct(int $workflowId, int $contactId, array $triggerData = [])
    {
        $this->workflowId = $workflowId;
        $this->contactId = $contactId;
        $this->triggerData = $triggerData;
        $this->onQueue(config('queue.pipes.automation', 'automation'));
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $workflow = AutomationWorkflow::find($this->workflowId);
        $contact = Contact::find($this->contactId);

        if (!$workflow || !$contact) {
            Log::warning("Workflow or contact not found for execution", [
                'workflow_id' => $this->workflowId,
                'contact_id' => $this->contactId,
            ]);
            return;
        }

        $service = new WorkflowExecutionService();

        try {
            $execution = $service->startExecution($workflow, $contact, $this->triggerData);

            if ($execution) {
                // Dispatch first node processing
                ProcessWorkflowNodeJob::dispatch($execution->id)
                    ->delay(now()->addSeconds(1));

                Log::info("Started workflow execution", [
                    'workflow_id' => $this->workflowId,
                    'contact_id' => $this->contactId,
                    'execution_id' => $execution->id,
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Failed to start workflow execution", [
                'workflow_id' => $this->workflowId,
                'contact_id' => $this->contactId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("StartWorkflowExecutionJob failed", [
            'workflow_id' => $this->workflowId,
            'contact_id' => $this->contactId,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'automation',
            'workflow-start',
            "workflow:{$this->workflowId}",
            "contact:{$this->contactId}",
        ];
    }
}
