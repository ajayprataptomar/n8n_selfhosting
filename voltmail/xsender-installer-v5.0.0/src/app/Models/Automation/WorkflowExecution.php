<?php

namespace App\Models\Automation;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WorkflowExecution extends Model
{
    protected $table = 'workflow_executions';

    protected $fillable = [
        'uid',
        'workflow_id',
        'contact_id',
        'current_node_id',
        'status',
        'started_at',
        'completed_at',
        'next_action_at',
        'context',
        'error_message',
    ];

    protected $casts = [
        'context' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'next_action_at' => 'datetime',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uid)) {
                $model->uid = Str::uuid()->toString();
            }
            if (empty($model->started_at)) {
                $model->started_at = now();
            }
        });
    }

    /**
     * Workflow relationship
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(AutomationWorkflow::class, 'workflow_id');
    }

    /**
     * Contact relationship
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Current node relationship
     */
    public function currentNode(): BelongsTo
    {
        return $this->belongsTo(WorkflowNode::class, 'current_node_id');
    }

    /**
     * Execution logs relationship
     */
    public function logs(): HasMany
    {
        return $this->hasMany(WorkflowExecutionLog::class, 'execution_id')->orderBy('executed_at');
    }

    /**
     * Check if execution is running
     */
    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    /**
     * Check if execution is waiting
     */
    public function isWaiting(): bool
    {
        return $this->status === 'waiting';
    }

    /**
     * Check if execution is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if execution is failed
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if execution can continue
     */
    public function canContinue(): bool
    {
        return in_array($this->status, ['running', 'waiting']);
    }

    /**
     * Mark as running
     */
    public function markAsRunning(?int $nodeId = null): void
    {
        $data = ['status' => 'running'];
        if ($nodeId) {
            $data['current_node_id'] = $nodeId;
        }
        $this->update($data);
    }

    /**
     * Mark as waiting
     */
    public function markAsWaiting(\DateTime $nextActionAt): void
    {
        $this->update([
            'status' => 'waiting',
            'next_action_at' => $nextActionAt,
        ]);
    }

    /**
     * Mark as completed
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'current_node_id' => null,
        ]);

        $this->workflow?->incrementCompleted();
    }

    /**
     * Mark as failed
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
            'error_message' => $errorMessage,
        ]);

        $this->workflow?->incrementFailed();
    }

    /**
     * Mark as cancelled
     */
    public function markAsCancelled(): void
    {
        $this->update([
            'status' => 'cancelled',
            'completed_at' => now(),
        ]);
    }

    /**
     * Move to next node
     */
    public function moveToNode(int $nodeId): void
    {
        $this->update(['current_node_id' => $nodeId]);
    }

    /**
     * Set context value
     */
    public function setContextValue(string $key, $value): void
    {
        $context = $this->context ?? [];
        $context[$key] = $value;
        $this->update(['context' => $context]);
    }

    /**
     * Get context value
     */
    public function getContextValue(string $key, $default = null)
    {
        return $this->context[$key] ?? $default;
    }

    /**
     * Log action
     */
    public function logAction(int $nodeId, string $action, string $result = 'success', array $data = [], ?string $error = null): WorkflowExecutionLog
    {
        return $this->logs()->create([
            'node_id' => $nodeId,
            'action' => $action,
            'result' => $result,
            'data' => $data,
            'error_message' => $error,
            'executed_at' => now(),
        ]);
    }

    /**
     * Get duration in seconds
     */
    public function getDurationAttribute(): ?int
    {
        if (!$this->completed_at) {
            return null;
        }
        return $this->completed_at->diffInSeconds($this->started_at);
    }

    /**
     * Scope for running executions
     */
    public function scopeRunning($query)
    {
        return $query->where('status', 'running');
    }

    /**
     * Scope for waiting executions
     */
    public function scopeWaiting($query)
    {
        return $query->where('status', 'waiting');
    }

    /**
     * Scope for executions ready to resume
     */
    public function scopeReadyToResume($query)
    {
        return $query->where('status', 'waiting')
                     ->where('next_action_at', '<=', now());
    }

    /**
     * Scope for completed executions
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for failed executions
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope for active executions
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['running', 'waiting']);
    }
}
