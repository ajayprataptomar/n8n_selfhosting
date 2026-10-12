<?php

namespace App\Services\Automation;

use App\Enums\System\ChannelTypeEnum;
use App\Models\ContactGroup;
use App\Models\Gateway;

/**
 * NodeFormSchema
 * --------------
 * Single source of truth for the workflow builder's per-node properties form.
 * Replaces the if/else chain in builder.blade.php with declarative schemas
 * so adding a node type only requires registering a new schema here.
 *
 * Each schema entry is an array of fields. Each field has:
 *
 *   key:         string  — name of the config key (e.g. 'gateway_id', 'message')
 *   label:       string  — visible form label
 *   type:        string  — text|textarea|email|number|select|checkbox|json|url|time|date
 *   required:    bool    — show * + block save when empty
 *   placeholder: string  — input placeholder
 *   help:        string  — small grey help text below the field
 *   options:     array   — list of [value, label] for select
 *   options_key: string  — dynamic options resolved at render time (e.g. 'sms_gateways')
 *   default:     mixed   — default value
 *   min/max:     int     — for number inputs
 *   rows:        int     — for textarea
 *   visible_if:  array   — { key: value } — show only if another field equals value
 *
 * The Error Handling section is appended automatically by the builder JS — schemas
 * do NOT need to declare retry/on_failure fields.
 */
class NodeFormSchema
{
    /**
     * Full schema map keyed by "{type}.{action_type}".
     *
     *  type        ∈ trigger | action | condition | wait
     *  action_type ∈ specific name within that group
     */
    public function all(): array
    {
        return array_merge(
            $this->actions(),
            $this->conditions(),
            $this->waits(),
            $this->triggers(),
        );
    }

    public function for(string $type, string $actionType): array
    {
        return $this->all()["{$type}.{$actionType}"] ?? [];
    }

    // ================================================================
    //   ACTIONS
    // ================================================================
    protected function actions(): array
    {
        return [
            'action.send_sms' => [
                ['key' => 'gateway_id', 'label' => 'SMS Gateway',    'type' => 'select', 'required' => true, 'options_key' => 'sms_gateways_with_android'],
                ['key' => 'message',    'label' => 'Message',        'type' => 'textarea', 'required' => true, 'rows' => 4,
                 'placeholder' => 'Use {{first_name}}, {{phone}} for personalization',
                 'help'        => 'Variables get substituted at send time.'],
            ],

            'action.send_email' => [
                ['key' => 'gateway_id', 'label' => 'Email Gateway', 'type' => 'select', 'required' => true, 'options_key' => 'email_gateways'],
                ['key' => 'subject',    'label' => 'Subject',       'type' => 'text',   'required' => true],
                ['key' => 'message',    'label' => 'Message',       'type' => 'textarea', 'required' => true, 'rows' => 6],
            ],

            'action.send_whatsapp' => [
                ['key' => 'device_id', 'label' => 'WhatsApp Device', 'type' => 'select', 'required' => true, 'options_key' => 'whatsapp_devices'],
                ['key' => 'message',   'label' => 'Message',         'type' => 'textarea', 'required' => true, 'rows' => 4],
            ],

            'action.add_to_group' => [
                ['key' => 'group_id', 'label' => 'Contact Group', 'type' => 'select', 'required' => true, 'options_key' => 'groups'],
            ],

            'action.remove_from_group' => [
                ['key' => 'group_id', 'label' => 'Contact Group', 'type' => 'select', 'required' => true, 'options_key' => 'groups'],
            ],

            'action.add_tag' => [
                ['key' => 'tag', 'label' => 'Tag Name', 'type' => 'text', 'required' => true,
                 'placeholder' => 'e.g. vip, hot-lead, replied'],
            ],

            'action.update_contact' => [
                ['key' => 'field', 'label' => 'Field', 'type' => 'select', 'required' => true, 'options' => [
                    ['value' => 'first_name',        'label' => 'First Name'],
                    ['value' => 'last_name',         'label' => 'Last Name'],
                    ['value' => 'email_contact',     'label' => 'Email'],
                    ['value' => 'sms_contact',       'label' => 'Phone (SMS)'],
                    ['value' => 'whatsapp_contact',  'label' => 'WhatsApp Number'],
                    ['value' => 'status',            'label' => 'Status'],
                ]],
                ['key' => 'value', 'label' => 'New Value', 'type' => 'text', 'required' => true,
                 'placeholder' => 'Use {{first_name}}, {{email}} for templating'],
            ],

            'action.notify_admin' => [
                ['key' => 'method',    'label' => 'Method',    'type' => 'select', 'required' => true, 'default' => 'email', 'options' => [
                    ['value' => 'email', 'label' => 'Email'],
                    ['value' => 'sms',   'label' => 'SMS'],
                ]],
                ['key' => 'recipient', 'label' => 'Recipient', 'type' => 'text', 'required' => true,
                 'placeholder' => 'admin@example.com or +1234567890'],
                ['key' => 'message',   'label' => 'Message',   'type' => 'textarea', 'required' => true, 'rows' => 3],
            ],

            'action.call_webhook' => [
                ['key' => 'url',     'label' => 'URL',          'type' => 'url',  'required' => true,
                 'placeholder' => 'https://your-service.example.com/hook'],
                ['key' => 'method',  'label' => 'HTTP Method',  'type' => 'select', 'default' => 'POST', 'options' => [
                    ['value' => 'POST',  'label' => 'POST'],
                    ['value' => 'PUT',   'label' => 'PUT'],
                    ['value' => 'PATCH', 'label' => 'PATCH'],
                    ['value' => 'GET',   'label' => 'GET'],
                ]],
                ['key' => 'headers', 'label' => 'Custom Headers (JSON)', 'type' => 'json',
                 'placeholder' => '{ "Authorization": "Bearer xxx" }',
                 'help'        => 'Optional. Must be a JSON object.'],
                ['key' => 'body',    'label' => 'Custom Body (JSON)',     'type' => 'json',
                 'placeholder' => '{ "event": "lead_replied" }',
                 'help'        => 'Optional. Variables like {{first_name}} are substituted at runtime.'],
            ],

            // NOTE: ai_auto_reply has bespoke UI elements (AI-not-configured alert, how-it-works
            // info box) that don't fit the generic schema renderer. Kept in the legacy generator
            // until the schema gains support for embedded info-alert fields.

            // v5.0-S6 — Static-text counterpart to ai_auto_reply. Replies on
            // the same channel the trigger arrived on (no gateway picker
            // needed — auto-routed by the executor).
            'action.send_template_reply' => [
                ['key' => 'message', 'label' => 'Reply Message', 'type' => 'textarea', 'required' => true, 'rows' => 4,
                 'placeholder' => 'Thanks for reaching out {{first_name}}! We will get back to you shortly.',
                 'help'        => 'Sent on the same channel (SMS / Email / WhatsApp) the customer replied from. {{first_name}}, {{last_name}}, {{name}}, {{phone}} are substituted at send time.'],
            ],
        ];
    }

    // ================================================================
    //   CONDITIONS
    // ================================================================
    protected function conditions(): array
    {
        return [
            'condition.has_tag' => [
                ['key' => 'tag', 'label' => 'Tag Name', 'type' => 'text', 'required' => true],
            ],

            'condition.field_equals' => [
                ['key' => 'field',    'label' => 'Field',    'type' => 'select', 'required' => true, 'options' => [
                    ['value' => 'first_name',    'label' => 'First Name'],
                    ['value' => 'last_name',     'label' => 'Last Name'],
                    ['value' => 'email_contact', 'label' => 'Email'],
                    ['value' => 'status',        'label' => 'Status'],
                ]],
                ['key' => 'operator', 'label' => 'Operator', 'type' => 'select', 'required' => true, 'options_key' => 'operators'],
                ['key' => 'value',    'label' => 'Value',    'type' => 'text',   'required' => true],
            ],

            'condition.in_group' => [
                ['key' => 'group_id', 'label' => 'Contact Group', 'type' => 'select', 'required' => true, 'options_key' => 'groups'],
            ],

            'condition.random_split' => [
                ['key' => 'percentage', 'label' => 'Percentage (Yes path)', 'type' => 'number', 'required' => true,
                 'min' => 1, 'max' => 99, 'default' => 50,
                 'help' => '{n}% will go to Yes, {100-n}% to No.'],
            ],

            'condition.day_of_week' => [
                ['key' => 'days', 'label' => 'Days', 'type' => 'text', 'required' => true,
                 'placeholder' => 'Comma-separated: mon,tue,wed'],
            ],

            'condition.time_between' => [
                ['key' => 'start_time', 'label' => 'Start Time', 'type' => 'time', 'required' => true, 'default' => '09:00'],
                ['key' => 'end_time',   'label' => 'End Time',   'type' => 'time', 'required' => true, 'default' => '17:00'],
            ],

            // v5.0-S6 — Inbound-message body matchers. Read the same context
            // slot the ai_auto_reply action consumes (trigger_data.reply_data.message_body),
            // so they work uniformly for WhatsApp + Email + SMS inbound replies.
            'condition.message_contains' => [
                ['key' => 'keywords',       'label' => 'Keywords',        'type' => 'text', 'required' => true,
                 'placeholder' => 'hello, hi, hey',
                 'help'        => 'Comma-separated. Case-insensitive by default.'],
                ['key' => 'match_mode',     'label' => 'Match mode',      'type' => 'select', 'default' => 'any', 'options' => [
                    ['value' => 'any', 'label' => 'ANY keyword matches (OR)'],
                    ['value' => 'all', 'label' => 'ALL keywords must match (AND)'],
                ]],
                ['key' => 'case_sensitive', 'label' => 'Case sensitive',  'type' => 'boolean', 'default' => false],
            ],

            'condition.message_matches_regex' => [
                ['key' => 'pattern',        'label' => 'Regex pattern',  'type' => 'text', 'required' => true,
                 'placeholder' => '\\b(stop|unsubscribe)\\b',
                 'help'        => 'PHP-compatible regex. Delimiters optional — the engine auto-wraps bare patterns.'],
                ['key' => 'case_sensitive', 'label' => 'Case sensitive', 'type' => 'boolean', 'default' => false],
            ],
        ];
    }

    // ================================================================
    //   WAITS
    // ================================================================
    protected function waits(): array
    {
        return [
            'wait.delay' => [
                ['key' => 'duration', 'label' => 'Duration', 'type' => 'number', 'required' => true, 'min' => 1, 'default' => 1],
                ['key' => 'unit',     'label' => 'Unit',     'type' => 'select', 'required' => true, 'default' => 'hours', 'options' => [
                    ['value' => 'minutes', 'label' => 'Minutes'],
                    ['value' => 'hours',   'label' => 'Hours'],
                    ['value' => 'days',    'label' => 'Days'],
                ]],
            ],

            'wait.until_time' => [
                ['key' => 'time', 'label' => 'Time', 'type' => 'time', 'required' => true, 'default' => '09:00'],
            ],

            'wait.until_date' => [
                ['key' => 'date', 'label' => 'Date', 'type' => 'date', 'required' => true],
                ['key' => 'time', 'label' => 'Time', 'type' => 'time', 'required' => true, 'default' => '09:00'],
            ],
        ];
    }

    // ================================================================
    //   TRIGGERS
    // ================================================================
    protected function triggers(): array
    {
        return [
            'trigger.new_contact' => [
                ['key' => 'group_id', 'label' => 'Contact Group', 'type' => 'select', 'options_key' => 'groups',
                 'help' => 'Leave empty to fire for any group.'],
            ],

            'trigger.contact_replied' => [
                ['key' => 'channel', 'label' => 'Channel', 'type' => 'select', 'default' => '', 'options' => [
                    ['value' => '',         'label' => 'Any Channel'],
                    ['value' => 'whatsapp', 'label' => 'WhatsApp Only'],
                    ['value' => 'sms',      'label' => 'SMS Only'],
                    ['value' => 'email',    'label' => 'Email Only'],
                ], 'help' => 'Fires when a contact sends a message on the selected channel.'],
            ],

            'trigger.no_response' => [
                ['key' => 'days', 'label' => 'Days Without Response', 'type' => 'number', 'required' => true,
                 'default' => 3, 'min' => 1, 'max' => 365,
                 'help' => "Trigger if contact hasn't replied within this many days."],
            ],

            'trigger.schedule' => [
                ['key' => 'schedule_type', 'label' => 'Schedule Type', 'type' => 'select', 'default' => 'daily', 'options' => [
                    ['value' => 'daily',   'label' => 'Daily'],
                    ['value' => 'weekly',  'label' => 'Weekly'],
                    ['value' => 'monthly', 'label' => 'Monthly'],
                ]],
                ['key' => 'time', 'label' => 'Time', 'type' => 'time', 'default' => '09:00'],
            ],

            'trigger.birthday' => [
                ['key' => 'days_before', 'label' => 'Days Before Birthday', 'type' => 'number',
                 'default' => 0, 'min' => 0, 'max' => 30,
                 'help' => '0 = on birthday, 1 = one day before, etc.'],
            ],

            // 'webhook' and 'manual' triggers have custom UI (webhook URL display, sample contact picker)
            // and stay hand-written for now — they are not data-form-driven.
        ];
    }

    /**
     * Resolve dynamic option lists (gateways, groups, etc) at render time.
     * Returns a map of options_key → array of {value,label} suitable for select inputs.
     *
     * Pass $userId for user-scoped builders, null for admin builders.
     */
    public function resolveOptionLists(?int $userId = null): array
    {
        $scope = fn ($q) => $userId !== null
            ? $q->where('user_id', $userId)
            : $q->whereNull('user_id');

        $mapGateway = fn ($g) => ['value' => $g->id, 'label' => $g->name];
        $mapGroup   = fn ($g) => ['value' => $g->id, 'label' => $g->name];

        $smsGateways = Gateway::active()
            ->where('channel', ChannelTypeEnum::SMS->value)
            ->where(fn ($q) => $scope($q))
            ->get(['id', 'name', 'type', 'user_id']);

        // Append connected Android Sessions as virtual SMS gateways (parity with v4.4 unified campaign)
        $smsGatewaysWithAndroid = $smsGateways->map($mapGateway)->all();
        try {
            $androidSessions = \App\Models\AndroidSession::query()
                ->where('status', \App\Enums\System\SessionStatusEnum::CONNECTED)
                ->when($userId !== null, fn ($q) => $q->where('user_id', $userId))
                ->when($userId === null, fn ($q) => $q->whereNull('user_id'))
                ->get(['id', 'name']);
            foreach ($androidSessions as $session) {
                $smsGatewaysWithAndroid[] = [
                    'value' => 'android-' . $session->id,
                    'label' => $session->name . ' (Android)',
                ];
            }
        } catch (\Throwable $e) {
            // android_sessions table may not exist on older installs — silent fallback
        }

        return [
            'sms_gateways'              => $smsGateways->map($mapGateway)->all(),
            'sms_gateways_with_android' => $smsGatewaysWithAndroid,
            'email_gateways'            => Gateway::active()->where('channel', ChannelTypeEnum::EMAIL->value)->where(fn ($q) => $scope($q))->get(['id', 'name'])->map($mapGateway)->all(),
            'whatsapp_devices'          => Gateway::active()->where('channel', ChannelTypeEnum::WHATSAPP->value)->where(fn ($q) => $scope($q))->get(['id', 'name'])->map($mapGateway)->all(),
            'groups'                    => ContactGroup::where(fn ($q) => $scope($q))->orderBy('name')->get(['id', 'name'])->map($mapGroup)->all(),
            'operators'                 => $this->fieldOperatorsAsOptions(),
        ];
    }

    private function fieldOperatorsAsOptions(): array
    {
        $ops = ConditionEvaluatorService::getFieldOperators();
        return array_map(fn ($k, $v) => ['value' => $k, 'label' => $v], array_keys($ops), $ops);
    }
}
