<?php

namespace App\Models\Automation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WorkflowNode extends Model
{
    protected $table = 'workflow_nodes';

    protected $fillable = [
        'uid',
        'workflow_id',
        'type',
        'action_type',
        'config',
        'label',
        'position_x',
        'position_y',
        'next_node_id',
        'condition_true_node_id',
        'condition_false_node_id',
        'order',
    ];

    protected $casts = [
        'config' => 'array',
        'position_x' => 'integer',
        'position_y' => 'integer',
        'order' => 'integer',
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
     * Node types with colors for visual builder
     */
    public const NODE_TYPES = [
        'trigger' => ['color' => '#10b981', 'icon' => 'ri-flashlight-line'],
        'action' => ['color' => '#3b82f6', 'icon' => 'ri-play-circle-line'],
        'condition' => ['color' => '#f59e0b', 'icon' => 'ri-git-branch-line'],
        'wait' => ['color' => '#6b7280', 'icon' => 'ri-time-line'],
    ];

    /**
     * Available action types
     */
    public const ACTION_TYPES = [
        // Messaging Actions
        'send_sms' => [
            'label' => 'Send SMS',
            'description' => 'Send an SMS message to the contact',
            'icon' => 'ri-message-2-line',
            'category' => 'messaging',
            'config_fields' => ['gateway_id', 'message', 'sender_id'],
        ],
        'send_email' => [
            'label' => 'Send Email',
            'description' => 'Send an email to the contact',
            'icon' => 'ri-mail-line',
            'category' => 'messaging',
            'config_fields' => ['gateway_id', 'subject', 'message'],
        ],
        'send_whatsapp' => [
            'label' => 'Send WhatsApp',
            'description' => 'Send a WhatsApp message to the contact',
            'icon' => 'ri-whatsapp-line',
            'category' => 'messaging',
            'config_fields' => ['device_id', 'message', 'media_url'],
        ],

        // Contact Management Actions
        'add_to_group' => [
            'label' => 'Add to Group',
            'description' => 'Add contact to a specific group',
            'icon' => 'ri-group-line',
            'category' => 'contact',
            'config_fields' => ['group_id'],
        ],
        'remove_from_group' => [
            'label' => 'Remove from Group',
            'description' => 'Remove contact from a specific group',
            'icon' => 'ri-user-unfollow-line',
            'category' => 'contact',
            'config_fields' => ['group_id'],
        ],
        'update_contact' => [
            'label' => 'Update Contact',
            'description' => 'Update contact field values',
            'icon' => 'ri-edit-line',
            'category' => 'contact',
            'config_fields' => ['field', 'value'],
        ],
        'add_tag' => [
            'label' => 'Add Tag',
            'description' => 'Add a tag to the contact',
            'icon' => 'ri-price-tag-3-line',
            'category' => 'contact',
            'config_fields' => ['tag'],
        ],

        // Notification Actions
        'notify_admin' => [
            'label' => 'Notify Admin',
            'description' => 'Send notification to admin',
            'icon' => 'ri-notification-line',
            'category' => 'notification',
            'config_fields' => ['method', 'recipient', 'message'],
        ],
        'call_webhook' => [
            'label' => 'Call Webhook',
            'description' => 'Call an external webhook URL',
            'icon' => 'ri-links-line',
            'category' => 'notification',
            'config_fields' => ['url', 'method', 'headers', 'body'],
        ],

        // AI Actions
        'ai_auto_reply' => [
            'label' => 'AI Auto Reply',
            'description' => 'Generate and send an AI-powered response to the contact message',
            'icon' => 'ri-robot-line',
            'category' => 'ai',
            'config_fields' => ['system_prompt', 'model', 'max_tokens', 'channel', 'device_id', 'gateway_id', 'email_subject'],
        ],

        // v5.0-S6 — Cheap counterpart to ai_auto_reply. Sends a fixed
        // template of text on the same channel the inbound reply arrived on,
        // reusing the existing InboxReplyService (which already routes
        // WhatsApp / Email / SMS through the correct sender). No AI cost.
        'send_template_reply' => [
            'label' => 'Send Template Reply',
            'description' => 'Reply with a fixed message on the same channel the inbound message arrived on. Supports {first_name}/{name} placeholders.',
            'icon' => 'ri-reply-all-line',
            'category' => 'messaging',
            'config_fields' => ['message'],
        ],
    ];

    /**
     * Condition types
     */
    public const CONDITION_TYPES = [
        'has_tag' => [
            'label' => 'Has Tag',
            'description' => 'Check if contact has a specific tag',
            'icon' => 'ri-price-tag-3-line',
            'config_fields' => ['tag'],
        ],
        'field_equals' => [
            'label' => 'Field Equals',
            'description' => 'Check if a contact field equals a value',
            'icon' => 'ri-equalizer-line',
            'config_fields' => ['field', 'operator', 'value'],
        ],
        'in_group' => [
            'label' => 'In Group',
            'description' => 'Check if contact is in a specific group',
            'icon' => 'ri-group-line',
            'config_fields' => ['group_id'],
        ],
        'random_split' => [
            'label' => 'Random Split (A/B)',
            'description' => 'Randomly split contacts for A/B testing',
            'icon' => 'ri-git-branch-line',
            'config_fields' => ['percentage'],
        ],
        'day_of_week' => [
            'label' => 'Day of Week',
            'description' => 'Check if today is a specific day',
            'icon' => 'ri-calendar-line',
            'config_fields' => ['days'],
        ],
        'time_between' => [
            'label' => 'Time Between',
            'description' => 'Check if current time is between hours',
            'icon' => 'ri-time-line',
            'config_fields' => ['start_time', 'end_time', 'timezone'],
        ],

        // v5.0-S6 — keyword and regex matchers on the inbound message body.
        // Reads $execution->context['trigger_data']['reply_data']['message_body']
        // (the same payload the AI Auto-Reply action already consumes), so
        // works uniformly across WhatsApp + Email + SMS inbound.
        'message_contains' => [
            'label' => 'Message Contains',
            'description' => 'Match inbound message body against a keyword or comma-separated keyword list (case-insensitive by default).',
            'icon' => 'ri-search-line',
            'config_fields' => ['keywords', 'match_mode', 'case_sensitive'],
        ],
        'message_matches_regex' => [
            'label' => 'Message Matches Regex',
            'description' => 'Match inbound message body against a PHP-compatible regular expression. Delimiters not required — the engine wraps as ~pattern~i by default.',
            'icon' => 'ri-code-s-slash-line',
            'config_fields' => ['pattern', 'case_sensitive'],
        ],
    ];

    /**
     * Wait types
     */
    public const WAIT_TYPES = [
        'delay' => [
            'label' => 'Wait for Duration',
            'description' => 'Wait for a specific duration',
            'icon' => 'ri-hourglass-line',
            'config_fields' => ['duration', 'unit'], // unit: minutes, hours, days
        ],
        'until_time' => [
            'label' => 'Wait Until Time',
            'description' => 'Wait until a specific time of day',
            'icon' => 'ri-alarm-line',
            'config_fields' => ['time', 'timezone'],
        ],
        'until_date' => [
            'label' => 'Wait Until Date',
            'description' => 'Wait until a specific date',
            'icon' => 'ri-calendar-check-line',
            'config_fields' => ['date', 'time'],
        ],
    ];

    /**
     * Workflow relationship
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(AutomationWorkflow::class, 'workflow_id');
    }

    /**
     * Next node relationship
     */
    public function nextNode(): BelongsTo
    {
        return $this->belongsTo(WorkflowNode::class, 'next_node_id');
    }

    /**
     * True condition node relationship
     */
    public function trueNode(): BelongsTo
    {
        return $this->belongsTo(WorkflowNode::class, 'condition_true_node_id');
    }

    /**
     * False condition node relationship
     */
    public function falseNode(): BelongsTo
    {
        return $this->belongsTo(WorkflowNode::class, 'condition_false_node_id');
    }

    /**
     * Execution logs for this node
     */
    public function executionLogs(): HasMany
    {
        return $this->hasMany(WorkflowExecutionLog::class, 'node_id');
    }

    /**
     * Check if this is a trigger node
     */
    public function isTrigger(): bool
    {
        return $this->type === 'trigger';
    }

    /**
     * Check if this is an action node
     */
    public function isAction(): bool
    {
        return $this->type === 'action';
    }

    /**
     * Check if this is a condition node
     */
    public function isCondition(): bool
    {
        return $this->type === 'condition';
    }

    /**
     * Check if this is a wait node
     */
    public function isWait(): bool
    {
        return $this->type === 'wait';
    }

    /**
     * Get the node's display label
     */
    public function getDisplayLabelAttribute(): string
    {
        if ($this->label) {
            return $this->label;
        }

        if ($this->type === 'trigger') {
            $triggers = AutomationWorkflow::TRIGGER_TYPES;
            return $triggers[$this->action_type]['label'] ?? 'Trigger';
        }

        if ($this->type === 'action') {
            return self::ACTION_TYPES[$this->action_type]['label'] ?? 'Action';
        }

        if ($this->type === 'condition') {
            return self::CONDITION_TYPES[$this->action_type]['label'] ?? 'Condition';
        }

        if ($this->type === 'wait') {
            return self::WAIT_TYPES[$this->action_type]['label'] ?? 'Wait';
        }

        return ucfirst($this->type);
    }

    /**
     * Get the node's icon
     */
    public function getIconAttribute(): string
    {
        if ($this->type === 'trigger') {
            $triggers = AutomationWorkflow::TRIGGER_TYPES;
            return $triggers[$this->action_type]['icon'] ?? 'ri-flashlight-line';
        }

        if ($this->type === 'action') {
            return self::ACTION_TYPES[$this->action_type]['icon'] ?? 'ri-play-circle-line';
        }

        if ($this->type === 'condition') {
            return self::CONDITION_TYPES[$this->action_type]['icon'] ?? 'ri-git-branch-line';
        }

        if ($this->type === 'wait') {
            return self::WAIT_TYPES[$this->action_type]['icon'] ?? 'ri-time-line';
        }

        return self::NODE_TYPES[$this->type]['icon'] ?? 'ri-checkbox-blank-circle-line';
    }

    /**
     * Get the node's color
     */
    public function getColorAttribute(): string
    {
        return self::NODE_TYPES[$this->type]['color'] ?? '#6b7280';
    }

    /**
     * Get config value
     */
    public function getConfigValue(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Get next node ID based on condition result
     */
    public function getNextNodeId(?bool $conditionResult = null): ?int
    {
        if ($this->isCondition() && $conditionResult !== null) {
            return $conditionResult ? $this->condition_true_node_id : $this->condition_false_node_id;
        }
        return $this->next_node_id;
    }
}
