<?php

namespace App\Services\Automation;

use App\Models\Contact;
use App\Models\Automation\AutomationWorkflow;
use App\Models\Automation\WorkflowNode;
use App\Models\Automation\WorkflowExecution;
use App\Models\Automation\WorkflowTriggerLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class WorkflowExecutionService
{
    protected ActionExecutorService $actionExecutor;
    protected ConditionEvaluatorService $conditionEvaluator;

    public function __construct()
    {
        $this->actionExecutor = new ActionExecutorService();
        $this->conditionEvaluator = new ConditionEvaluatorService();
    }

    /**
     * Start workflow execution for a contact
     */
    public function startExecution(AutomationWorkflow $workflow, Contact $contact, array $triggerData = []): ?WorkflowExecution
    {
        if (!$workflow->isActive()) {
            Log::warning("Attempted to start execution for inactive workflow", [
                'workflow_id' => $workflow->id,
                'contact_id' => $contact->id,
            ]);
            return null;
        }

        // Check if contact is already in an active execution for this workflow
        $existingExecution = WorkflowExecution::where('workflow_id', $workflow->id)
            ->where('contact_id', $contact->id)
            ->active()
            ->first();

        if ($existingExecution) {
            Log::info("Contact already in active workflow execution", [
                'workflow_id' => $workflow->id,
                'contact_id' => $contact->id,
                'execution_id' => $existingExecution->id,
            ]);
            return null;
        }

        // Get the first action node (after trigger)
        $firstNode = $workflow->getFirstActionNode();
        if (!$firstNode) {
            Log::warning("Workflow has no action nodes", ['workflow_id' => $workflow->id]);
            return null;
        }

        try {
            return DB::transaction(function () use ($workflow, $contact, $firstNode, $triggerData) {
                // Create execution record
                $execution = WorkflowExecution::create([
                    'workflow_id' => $workflow->id,
                    'contact_id' => $contact->id,
                    'current_node_id' => $firstNode->id,
                    'status' => 'running',
                    'context' => [
                        'trigger_data' => $triggerData,
                        'enrolled_at' => now()->toISOString(),
                    ],
                ]);

                // Increment workflow stats
                $workflow->incrementEnrolled();
                $workflow->update(['last_triggered_at' => now()]);

                Log::info("Started workflow execution", [
                    'workflow_id' => $workflow->id,
                    'contact_id' => $contact->id,
                    'execution_id' => $execution->id,
                ]);

                return $execution;
            });
        } catch (\Exception $e) {
            Log::error("Failed to start workflow execution", [
                'workflow_id' => $workflow->id,
                'contact_id' => $contact->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Start workflow for multiple contacts (batch enrollment)
     */
    public function enrollContacts(AutomationWorkflow $workflow, array $contactIds, array $triggerData = []): array
    {
        $results = [
            'enrolled' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        foreach ($contactIds as $contactId) {
            $contact = Contact::find($contactId);
            if (!$contact) {
                $results['failed']++;
                continue;
            }

            $execution = $this->startExecution($workflow, $contact, $triggerData);
            if ($execution) {
                $results['enrolled']++;
            } else {
                $results['skipped']++;
            }
        }

        // Log trigger
        if ($results['enrolled'] > 0) {
            WorkflowTriggerLog::create([
                'workflow_id' => $workflow->id,
                'trigger_type' => $workflow->trigger_type,
                'contacts_enrolled' => $results['enrolled'],
                'trigger_data' => $triggerData,
                'triggered_at' => now(),
            ]);
        }

        return $results;
    }

    /**
     * Process the current node in an execution
     */
    public function processCurrentNode(WorkflowExecution $execution): bool
    {
        if (!$execution->canContinue()) {
            return false;
        }

        $node = $execution->currentNode;
        if (!$node) {
            $execution->markAsCompleted();
            return true;
        }

        $execution->markAsRunning($node->id);

        try {
            $result = $this->executeNode($execution, $node);
            return $result;
        } catch (\Exception $e) {
            Log::error("Node execution failed", [
                'execution_id' => $execution->id,
                'node_id' => $node->id,
                'error' => $e->getMessage(),
            ]);

            $execution->logAction($node->id, $node->action_type ?? $node->type, 'failed', [], $e->getMessage());
            $execution->markAsFailed($e->getMessage());
            return false;
        }
    }

    /**
     * Execute a specific node
     */
    protected function executeNode(WorkflowExecution $execution, WorkflowNode $node): bool
    {
        $contact = $execution->contact;
        $workflow = $execution->workflow;

        switch ($node->type) {
            case 'action':
                return $this->executeActionNode($execution, $node, $contact);

            case 'condition':
                return $this->executeConditionNode($execution, $node, $contact);

            case 'wait':
                return $this->executeWaitNode($execution, $node);

            default:
                Log::warning("Unknown node type", [
                    'node_id' => $node->id,
                    'type' => $node->type,
                ]);
                return $this->moveToNextNode($execution, $node);
        }
    }

    /**
     * Execute an action node
     */
    protected function executeActionNode(WorkflowExecution $execution, WorkflowNode $node, Contact $contact): bool
    {
        $result = $this->actionExecutor->execute($node, $contact, $execution);

        $execution->logAction(
            $node->id,
            $node->action_type,
            $result['success'] ? 'success' : 'failed',
            $result['data'] ?? [],
            $result['error'] ?? null
        );

        if (!$result['success']) {
            // Resolve on_failure_action (new) with fallback to legacy stop_on_failure (boolean).
            $config  = $node->config ?? [];
            $action  = $config['on_failure_action']
                ?? (($config['stop_on_failure'] ?? false) ? 'stop' : 'continue');

            switch ($action) {
                case 'stop':
                    $execution->markAsFailed($result['error'] ?? 'Action failed');
                    return false;

                case 'skip':
                    // Mark execution completed without running remaining nodes.
                    $execution->markAsCompleted();
                    return true;

                case 'continue':
                default:
                    // Fall through and continue to next node — the failed action is logged.
                    break;
            }
        }

        return $this->moveToNextNode($execution, $node);
    }

    /**
     * Execute a condition node
     */
    protected function executeConditionNode(WorkflowExecution $execution, WorkflowNode $node, Contact $contact): bool
    {
        $conditionResult = $this->conditionEvaluator->evaluate($node, $contact, $execution);

        $execution->logAction(
            $node->id,
            $node->action_type,
            'success',
            ['condition_result' => $conditionResult]
        );

        // Store condition result in context for reference
        $execution->setContextValue("condition_{$node->id}", $conditionResult);

        // Get next node based on condition result
        $nextNodeId = $node->getNextNodeId($conditionResult);

        if ($nextNodeId) {
            $execution->moveToNode($nextNodeId);
            return true;
        }

        // No next node, execution complete
        $execution->markAsCompleted();
        return true;
    }

    /**
     * Execute a wait node
     */
    protected function executeWaitNode(WorkflowExecution $execution, WorkflowNode $node): bool
    {
        $waitUntil = $this->calculateWaitTime($node);

        $execution->logAction(
            $node->id,
            $node->action_type ?? 'wait',
            'success',
            ['wait_until' => $waitUntil->toISOString()]
        );

        // Set the next action time and mark as waiting
        $execution->markAsWaiting($waitUntil);

        // Store next node ID in context for when we resume
        $nextNodeId = $node->next_node_id;
        $execution->setContextValue('resume_node_id', $nextNodeId);

        return true;
    }

    /**
     * Calculate wait time based on node configuration
     */
    protected function calculateWaitTime(WorkflowNode $node): \DateTime
    {
        $config = $node->config ?? [];
        $actionType = $node->action_type;

        switch ($actionType) {
            case 'delay':
                $duration = (int)($config['duration'] ?? 1);
                $unit = $config['unit'] ?? 'hours';
                return match ($unit) {
                    'minutes' => now()->addMinutes($duration),
                    'hours' => now()->addHours($duration),
                    'days' => now()->addDays($duration),
                    default => now()->addHours($duration),
                };

            case 'until_time':
                $time = $config['time'] ?? '09:00';
                $timezone = $config['timezone'] ?? config('app.timezone');
                $targetTime = now($timezone)->setTimeFromTimeString($time);
                if ($targetTime->isPast()) {
                    $targetTime->addDay();
                }
                return $targetTime;

            case 'until_date':
                $date = $config['date'] ?? now()->addDay()->format('Y-m-d');
                $time = $config['time'] ?? '09:00';
                return \Carbon\Carbon::parse("{$date} {$time}");

            default:
                return now()->addHour();
        }
    }

    /**
     * Move to the next node in the workflow
     */
    protected function moveToNextNode(WorkflowExecution $execution, WorkflowNode $currentNode): bool
    {
        $nextNodeId = $currentNode->next_node_id;

        if ($nextNodeId) {
            $execution->moveToNode($nextNodeId);
            return true;
        }

        // No next node, workflow complete
        $execution->markAsCompleted();
        return true;
    }

    /**
     * Resume waiting executions that are ready
     */
    public function resumeWaitingExecutions(): int
    {
        $executions = WorkflowExecution::readyToResume()
            ->with(['workflow', 'contact', 'currentNode'])
            ->limit(100)
            ->get();

        $resumed = 0;

        foreach ($executions as $execution) {
            // Get the node to resume from
            $resumeNodeId = $execution->getContextValue('resume_node_id');

            if ($resumeNodeId) {
                $execution->moveToNode($resumeNodeId);
                $execution->markAsRunning($resumeNodeId);
            }

            $resumed++;
        }

        return $resumed;
    }

    /**
     * Cancel an execution
     */
    public function cancelExecution(WorkflowExecution $execution): bool
    {
        if ($execution->isCompleted() || $execution->isFailed()) {
            return false;
        }

        $execution->markAsCancelled();

        Log::info("Workflow execution cancelled", [
            'execution_id' => $execution->id,
            'workflow_id' => $execution->workflow_id,
        ]);

        return true;
    }

    /**
     * Get execution statistics for a workflow
     */
    public function getWorkflowStats(AutomationWorkflow $workflow): array
    {
        $executions = $workflow->executions();

        return [
            'total_enrolled' => $workflow->total_enrolled,
            'total_completed' => $workflow->total_completed,
            'total_failed' => $workflow->total_failed,
            'currently_running' => $executions->where('status', 'running')->count(),
            'currently_waiting' => $executions->where('status', 'waiting')->count(),
            'completion_rate' => $workflow->completion_rate,
        ];
    }

    /**
     * Get detailed execution logs
     */
    public function getExecutionTimeline(WorkflowExecution $execution): array
    {
        $logs = $execution->logs()->with('node')->orderBy('executed_at')->get();

        // Pre-compute durations between consecutive log entries so the debug view
        // can show how long each node took without per-row queries.
        $previous = null;

        return $logs->map(function ($log) use (&$previous) {
            $durationMs = null;
            if ($previous && $log->executed_at && $previous->executed_at) {
                $durationMs = (int) $log->executed_at->diffInMilliseconds($previous->executed_at);
            }
            $previous = $log;

            return [
                'node_id'       => $log->node_id,
                'node_label'    => $log->node?->display_label ?? 'Unknown',
                'node_type'     => $log->node?->type,
                'action'        => $log->action,
                'result'        => $log->result,
                'data'          => $log->data,
                'error'         => $log->error_message,
                'executed_at'   => $log->executed_at?->toISOString(),
                'since_prev_ms' => $durationMs,
            ];
        })->toArray();
    }
}
