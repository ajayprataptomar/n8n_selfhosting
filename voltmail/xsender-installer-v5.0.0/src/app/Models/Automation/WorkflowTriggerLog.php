<?php

namespace App\Models\Automation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowTriggerLog extends Model
{
    protected $table = 'workflow_trigger_logs';

    protected $fillable = [
        'workflow_id',
        'trigger_type',
        'contacts_enrolled',
        'trigger_data',
        'triggered_at',
    ];

    protected $casts = [
        'trigger_data' => 'array',
        'triggered_at' => 'datetime',
        'contacts_enrolled' => 'integer',
    ];

    /**
     * Workflow relationship
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(AutomationWorkflow::class, 'workflow_id');
    }
}
