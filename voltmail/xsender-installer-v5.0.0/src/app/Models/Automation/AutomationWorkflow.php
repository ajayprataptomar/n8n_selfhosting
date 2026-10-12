<?php

namespace App\Models\Automation;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AutomationWorkflow extends Model
{
    use SoftDeletes;

    protected $table = 'automation_workflows';

    protected $fillable = [
        'uid',
        'user_id',
        'name',
        'description',
        'status',
        'trigger_type',
        'trigger_config',
        'total_enrolled',
        'total_completed',
        'total_failed',
        'last_triggered_at',
    ];

    protected $casts = [
        'trigger_config' => 'array',
        'last_triggered_at' => 'datetime',
        'total_enrolled' => 'integer',
        'total_completed' => 'integer',
        'total_failed' => 'integer',
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
        });
    }

    /**
     * Available trigger types
     */
    public const TRIGGER_TYPES = [
        'new_contact' => [
            'label' => 'New Contact Added',
            'description' => 'Trigger when a contact is added to a group',
            'icon' => 'ri-user-add-line',
            'config_fields' => ['group_id'],
        ],
        'schedule' => [
            'label' => 'Scheduled Time',
            'description' => 'Trigger at a specific time or recurring schedule',
            'icon' => 'ri-calendar-schedule-line',
            'config_fields' => ['schedule_type', 'time', 'days', 'timezone'],
        ],
        'webhook' => [
            'label' => 'Webhook Trigger',
            'description' => 'Trigger via external API webhook',
            'icon' => 'ri-webhook-line',
            'config_fields' => ['webhook_url'],
        ],
        'manual' => [
            'label' => 'Manual Trigger',
            'description' => 'Manually trigger for selected contacts',
            'icon' => 'ri-hand-coin-line',
            'config_fields' => [],
        ],
        'contact_replied' => [
            'label' => 'Contact Replied',
            'description' => 'Trigger when a contact replies to a message',
            'icon' => 'ri-chat-check-line',
            'config_fields' => ['channel'],
        ],
        'no_response' => [
            'label' => 'No Response',
            'description' => 'Trigger when contact hasn\'t responded after X days',
            'icon' => 'ri-time-line',
            'config_fields' => ['days', 'channel'],
        ],
        'birthday' => [
            'label' => 'Birthday/Anniversary',
            'description' => 'Trigger on contact\'s birthday or anniversary date',
            'icon' => 'ri-cake-2-line',
            'config_fields' => ['date_field', 'days_before'],
        ],
    ];

    /**
     * User relationship
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Nodes relationship
     */
    public function nodes(): HasMany
    {
        return $this->hasMany(WorkflowNode::class, 'workflow_id')->orderBy('order');
    }

    /**
     * Executions relationship
     */
    public function executions(): HasMany
    {
        return $this->hasMany(WorkflowExecution::class, 'workflow_id');
    }

    /**
     * Trigger logs relationship
     */
    public function triggerLogs(): HasMany
    {
        return $this->hasMany(WorkflowTriggerLog::class, 'workflow_id');
    }

    /**
     * Get the trigger node
     */
    public function getTriggerNode(): ?WorkflowNode
    {
        return $this->nodes()->where('type', 'trigger')->first();
    }

    /**
     * Get first action node
     */
    public function getFirstActionNode(): ?WorkflowNode
    {
        $triggerNode = $this->getTriggerNode();
        if ($triggerNode && $triggerNode->next_node_id) {
            return WorkflowNode::find($triggerNode->next_node_id);
        }
        return $this->nodes()->where('type', '!=', 'trigger')->orderBy('order')->first();
    }

    /**
     * Check if workflow is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if workflow is draft
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Activate the workflow
     */
    public function activate(): bool
    {
        if ($this->nodes()->count() < 2) {
            return false; // Need at least trigger + 1 action
        }
        $this->update(['status' => 'active']);
        return true;
    }

    /**
     * Pause the workflow
     */
    public function pause(): void
    {
        $this->update(['status' => 'paused']);
    }

    /**
     * Get active executions count
     */
    public function getActiveExecutionsCountAttribute(): int
    {
        return $this->executions()->whereIn('status', ['running', 'waiting'])->count();
    }

    /**
     * Get completion rate
     */
    public function getCompletionRateAttribute(): float
    {
        if ($this->total_enrolled == 0) {
            return 0;
        }
        return round(($this->total_completed / $this->total_enrolled) * 100, 1);
    }

    /**
     * Increment enrolled count
     */
    public function incrementEnrolled(int $count = 1): void
    {
        $this->increment('total_enrolled', $count);
    }

    /**
     * Increment completed count
     */
    public function incrementCompleted(int $count = 1): void
    {
        $this->increment('total_completed', $count);
    }

    /**
     * Increment failed count
     */
    public function incrementFailed(int $count = 1): void
    {
        $this->increment('total_failed', $count);
    }

    /**
     * Scope for active workflows
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for user's workflows
     */
    public function scopeForUser($query, ?int $userId)
    {
        if ($userId) {
            return $query->where('user_id', $userId);
        }
        return $query->whereNull('user_id');
    }

    /**
     * Scope by trigger type
     */
    public function scopeByTrigger($query, string $triggerType)
    {
        return $query->where('trigger_type', $triggerType);
    }
}
