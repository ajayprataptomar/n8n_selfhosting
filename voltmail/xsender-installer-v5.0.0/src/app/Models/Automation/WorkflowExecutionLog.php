<?php

namespace App\Models\Automation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowExecutionLog extends Model
{
    protected $table = 'workflow_execution_logs';

    public $timestamps = false;

    protected $fillable = [
        'execution_id',
        'node_id',
        'action',
        'result',
        'data',
        'error_message',
        'executed_at',
    ];

    protected $casts = [
        'data' => 'array',
        'executed_at' => 'datetime',
    ];

    /**
     * Execution relationship
     */
    public function execution(): BelongsTo
    {
        return $this->belongsTo(WorkflowExecution::class, 'execution_id');
    }

    /**
     * Node relationship
     */
    public function node(): BelongsTo
    {
        return $this->belongsTo(WorkflowNode::class, 'node_id');
    }

    /**
     * Check if result is success
     */
    public function isSuccess(): bool
    {
        return $this->result === 'success';
    }

    /**
     * Check if result is failed
     */
    public function isFailed(): bool
    {
        return $this->result === 'failed';
    }

    /**
     * Check if result is skipped
     */
    public function isSkipped(): bool
    {
        return $this->result === 'skipped';
    }

    /**
     * Scope for successful logs
     */
    public function scopeSuccessful($query)
    {
        return $query->where('result', 'success');
    }

    /**
     * Scope for failed logs
     */
    public function scopeFailed($query)
    {
        return $query->where('result', 'failed');
    }
}
