<?php

namespace App\Jobs;

use App\Models\Automation\WorkflowExecution;
use App\Services\Automation\WorkflowExecutionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessWorkflowNodeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Hard ceiling — actual retry count is per-node and read from the node's
    // config via tries()/backoff() methods below. 1 = run once with no retry.
    public int $tries = 1;
    public int $timeout = 120;

    protected int $executionId;

    /** Cached node config so tries()/backoff() don't hit the DB twice. */
    private ?array $cachedNodeConfig = null;

    /**
     * Create a new job instance.
     */
    public function __construct(int $executionId)
    {
        $this->executionId = $executionId;
        $this->onQueue(config('queue.pipes.automation', 'automation'));
    }

    /**
     * Per-node retry count from the node's config (Error Handling UI).
     * Returns 1 (no retry) if the node has no retry_count set.
     */
    public function tries(): int
    {
        $config = $this->loadNodeConfig();
        $retry = (int) ($config['retry_count'] ?? 0);
        // tries = attempt count (1 means run once). retry_count is additional attempts.
        return max(1, $retry + 1);
    }

    /**
     * Per-node backoff (seconds between retries) from the node's config.
     * Defaults to 60s.
     */
    public function backoff(): int
    {
        $config = $this->loadNodeConfig();
        return max(5, (int) ($config['retry_delay'] ?? 60));
    }

    /**
     * Load the current node's config from the execution's current_node_id.
     * Cached to avoid repeated queries — Laravel calls tries() and backoff()
     * separately on the same job instance before/after each attempt.
     */
    private function loadNodeConfig(): array
    {
        if ($this->cachedNodeConfig !== null) {
            return $this->cachedNodeConfig;
        }

        try {
            $execution = WorkflowExecution::with('currentNode')->find($this->executionId);
            $config = $execution?->currentNode?->config ?? [];
            return $this->cachedNodeConfig = (is_array($config) ? $config : []);
        } catch (\Throwable $e) {
            return $this->cachedNodeConfig = [];
        }
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $execution = WorkflowExecution::with(['workflow', 'contact', 'currentNode'])->find($this->executionId);

        if (!$execution) {
            Log::warning("Workflow execution not found", ['execution_id' => $this->executionId]);
            return;
        }

        if (!$execution->canContinue()) {
            Log::info("Workflow execution cannot continue", [
                'execution_id' => $this->executionId,
                'status' => $execution->status,
            ]);
            return;
        }

        if (!$execution->workflow || !$execution->workflow->isActive()) {
            Log::info("Workflow is not active, marking execution as cancelled", [
                'execution_id' => $this->executionId,
            ]);
            $execution->markAsCancelled();
            return;
        }

        $service = new WorkflowExecutionService();

        try {
            $success = $service->processCurrentNode($execution);

            // If execution is still running (not waiting), continue to next node
            $execution->refresh();

            if ($execution->isRunning() && $execution->current_node_id) {
                // Dispatch next node processing with small delay
                self::dispatch($this->executionId)
                    ->delay(now()->addSeconds(2));
            }
        } catch (\Exception $e) {
            Log::error("Workflow node processing failed", [
                'execution_id' => $this->executionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $execution->markAsFailed($e->getMessage());
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("ProcessWorkflowNodeJob failed", [
            'execution_id' => $this->executionId,
            'error' => $exception->getMessage(),
        ]);

        $execution = WorkflowExecution::find($this->executionId);
        if ($execution && $execution->canContinue()) {
            $execution->markAsFailed("Job failed: " . $exception->getMessage());
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return ['automation', 'workflow-execution', "execution:{$this->executionId}"];
    }
}
