<?php

namespace App\Services\Automation;

use App\Models\User;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\Automation\AutomationWorkflow;
use App\Models\Automation\WorkflowTriggerLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class TriggerHandlerService
{
    protected WorkflowExecutionService $executionService;

    public function __construct()
    {
        $this->executionService = new WorkflowExecutionService();
    }

    /**
     * Handle new contact trigger
     */
    public function handleNewContact(Contact $contact): array
    {
        $results = [
            'workflows_triggered' => 0,
            'contacts_enrolled' => 0,
        ];

        // Find all active workflows with 'new_contact' trigger for this group
        $workflows = AutomationWorkflow::active()
            ->where('trigger_type', 'new_contact')
            ->whereJsonContains('trigger_config->group_id', $contact->group_id)
            ->orWhere(function ($q) use ($contact) {
                $q->where('trigger_type', 'new_contact')
                  ->where('status', 'active')
                  ->whereNull('trigger_config->group_id'); // Workflows for any group
            })
            ->get();

        foreach ($workflows as $workflow) {
            // Check if workflow belongs to same user
            if ($workflow->user_id && $workflow->user_id !== $contact->user_id) {
                continue;
            }

            $execution = $this->executionService->startExecution($workflow, $contact, [
                'trigger' => 'new_contact',
                'group_id' => $contact->group_id,
            ]);

            if ($execution) {
                $results['workflows_triggered']++;
                $results['contacts_enrolled']++;
            }
        }

        return $results;
    }

    /**
     * Handle schedule trigger (called by scheduler)
     */
    public function handleScheduleTrigger(): array
    {
        $results = [
            'workflows_processed' => 0,
            'contacts_enrolled' => 0,
        ];

        // Find all active workflows with 'schedule' trigger
        $workflows = AutomationWorkflow::active()
            ->where('trigger_type', 'schedule')
            ->get();

        foreach ($workflows as $workflow) {
            if ($this->shouldTriggerSchedule($workflow)) {
                $enrolled = $this->enrollContactsForScheduledWorkflow($workflow);
                $results['workflows_processed']++;
                $results['contacts_enrolled'] += $enrolled;
            }
        }

        return $results;
    }

    /**
     * Check if a scheduled workflow should trigger now
     */
    protected function shouldTriggerSchedule(AutomationWorkflow $workflow): bool
    {
        $config = $workflow->trigger_config ?? [];
        $scheduleType = $config['schedule_type'] ?? 'once';
        $time = $config['time'] ?? '09:00';
        $days = $config['days'] ?? [];
        $timezone = $config['timezone'] ?? config('app.timezone');

        $now = Carbon::now($timezone);
        $targetTime = Carbon::parse($time, $timezone);

        // Check if we're within the trigger window (5 minute grace period)
        $isWithinWindow = abs($now->diffInMinutes($targetTime, false)) <= 5;

        if (!$isWithinWindow) {
            return false;
        }

        // Check if already triggered today
        $lastTriggered = $workflow->last_triggered_at;
        if ($lastTriggered && $lastTriggered->isSameDay($now)) {
            return false;
        }

        switch ($scheduleType) {
            case 'once':
                // Only trigger if never triggered before
                return is_null($lastTriggered);

            case 'daily':
                return true;

            case 'weekly':
                // Check if today is one of the scheduled days
                $todayName = strtolower($now->format('l'));
                return in_array($todayName, array_map('strtolower', $days));

            case 'monthly':
                // Trigger on specific day of month
                $dayOfMonth = (int)($config['day_of_month'] ?? 1);
                return $now->day === $dayOfMonth;

            default:
                return false;
        }
    }

    /**
     * Enroll contacts for a scheduled workflow
     */
    protected function enrollContactsForScheduledWorkflow(AutomationWorkflow $workflow): int
    {
        $config = $workflow->trigger_config ?? [];
        $groupId = $config['group_id'] ?? null;

        // Get contacts to enroll
        $query = Contact::where('status', 'active');

        if ($workflow->user_id) {
            $query->where('user_id', $workflow->user_id);
        }

        if ($groupId) {
            $query->where('group_id', $groupId);
        }

        $contactIds = $query->pluck('id')->toArray();

        if (empty($contactIds)) {
            return 0;
        }

        $results = $this->executionService->enrollContacts($workflow, $contactIds, [
            'trigger' => 'schedule',
            'scheduled_at' => now()->toISOString(),
        ]);

        return $results['enrolled'];
    }

    /**
     * Handle webhook trigger
     */
    public function handleWebhookTrigger(string $webhookId, array $payload): array
    {
        $results = [
            'workflows_triggered' => 0,
            'contacts_enrolled' => 0,
            'errors' => [],
        ];

        // Find workflow by webhook ID
        $workflow = AutomationWorkflow::active()
            ->where('trigger_type', 'webhook')
            ->whereJsonContains('trigger_config->webhook_id', $webhookId)
            ->first();

        if (!$workflow) {
            $results['errors'][] = 'Workflow not found for webhook';
            return $results;
        }

        // Extract contact from payload
        $contact = $this->findOrCreateContactFromPayload($payload, $workflow->user_id);

        if (!$contact) {
            $results['errors'][] = 'Could not identify contact from payload';
            return $results;
        }

        $execution = $this->executionService->startExecution($workflow, $contact, [
            'trigger' => 'webhook',
            'webhook_id' => $webhookId,
            'payload' => $payload,
        ]);

        if ($execution) {
            $results['workflows_triggered']++;
            $results['contacts_enrolled']++;
        }

        return $results;
    }

    /**
     * Handle manual trigger
     */
    public function handleManualTrigger(AutomationWorkflow $workflow, array $contactIds): array
    {
        return $this->executionService->enrollContacts($workflow, $contactIds, [
            'trigger' => 'manual',
            'triggered_by' => auth()->id(),
            'triggered_at' => now()->toISOString(),
        ]);
    }

    /**
     * Handle contact replied trigger
     */
    public function handleContactReplied(Contact $contact, string $channel, array $replyData = []): array
    {
        $results = [
            'workflows_triggered' => 0,
            'contacts_enrolled' => 0,
        ];

        // Find workflows with 'contact_replied' trigger
        $workflows = AutomationWorkflow::active()
            ->where('trigger_type', 'contact_replied')
            ->where(function ($q) use ($channel) {
                $q->whereJsonContains('trigger_config->channel', $channel)
                  ->orWhereNull('trigger_config->channel'); // Any channel
            })
            ->get();

        foreach ($workflows as $workflow) {
            if ($workflow->user_id && $workflow->user_id !== $contact->user_id) {
                continue;
            }

            $execution = $this->executionService->startExecution($workflow, $contact, [
                'trigger' => 'contact_replied',
                'channel' => $channel,
                'reply_data' => $replyData,
            ]);

            if ($execution) {
                $results['workflows_triggered']++;
                $results['contacts_enrolled']++;
            }
        }

        return $results;
    }

    /**
     * Handle no response trigger (contacts who haven't responded after X days)
     */
    public function handleNoResponseTrigger(): array
    {
        $results = [
            'workflows_processed' => 0,
            'contacts_enrolled' => 0,
        ];

        $workflows = AutomationWorkflow::active()
            ->where('trigger_type', 'no_response')
            ->get();

        foreach ($workflows as $workflow) {
            $config = $workflow->trigger_config ?? [];
            $days = (int)($config['days'] ?? 7);
            $channel = $config['channel'] ?? null;

            // Find contacts with no response after X days
            $contactIds = $this->findContactsWithNoResponse($workflow, $days, $channel);

            if (!empty($contactIds)) {
                $enrolled = $this->executionService->enrollContacts($workflow, $contactIds, [
                    'trigger' => 'no_response',
                    'days_since_last_message' => $days,
                    'channel' => $channel,
                ]);

                $results['workflows_processed']++;
                $results['contacts_enrolled'] += $enrolled['enrolled'];
            }
        }

        return $results;
    }

    /**
     * Find contacts who haven't responded in X days
     */
    protected function findContactsWithNoResponse(AutomationWorkflow $workflow, int $days, ?string $channel): array
    {
        $cutoffDate = now()->subDays($days);

        // Get contacts who received a message but haven't responded
        $query = Contact::where('status', 'active')
            ->whereHas('dispatchLog', function ($q) use ($cutoffDate, $channel) {
                $q->where('created_at', '<=', $cutoffDate)
                  ->where('status', 'delivered');

                if ($channel) {
                    $q->where('channel', $channel);
                }
            })
            ->whereDoesntHave('conversations', function ($q) use ($cutoffDate, $channel) {
                $q->where('created_at', '>=', $cutoffDate)
                  ->where('direction', 'incoming');

                if ($channel) {
                    $q->where('channel', $channel);
                }
            });

        if ($workflow->user_id) {
            $query->where('user_id', $workflow->user_id);
        }

        // Exclude contacts already in active execution for this workflow
        $activeContactIds = $workflow->executions()
            ->active()
            ->pluck('contact_id')
            ->toArray();

        if (!empty($activeContactIds)) {
            $query->whereNotIn('id', $activeContactIds);
        }

        return $query->pluck('id')->toArray();
    }

    /**
     * Find or create contact from webhook payload
     */
    protected function findOrCreateContactFromPayload(array $payload, ?int $userId): ?Contact
    {
        $email = $payload['email'] ?? null;
        $phone = $payload['phone'] ?? $payload['sms'] ?? null;
        $whatsapp = $payload['whatsapp'] ?? null;

        if (!$email && !$phone && !$whatsapp) {
            return null;
        }

        // Try to find existing contact
        $query = Contact::query();

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $query->where(function ($q) use ($email, $phone, $whatsapp) {
            if ($email) {
                $q->orWhere('email_contact', $email);
            }
            if ($phone) {
                $q->orWhere('sms_contact', $phone);
            }
            if ($whatsapp) {
                $q->orWhere('whatsapp_contact', $whatsapp);
            }
        });

        $contact = $query->first();

        if ($contact) {
            return $contact;
        }

        // Create new contact if configured
        $group = ContactGroup::where('name', 'Webhook Contacts')
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->first();

        if (!$group) {
            $group = ContactGroup::create([
                'uid' => str_unique(),
                'name' => 'Webhook Contacts',
                'user_id' => $userId,
                'status' => 'active',
            ]);
        }

        return Contact::create([
            'uid' => str_unique(),
            'user_id' => $userId,
            'group_id' => $group->id,
            'first_name' => $payload['first_name'] ?? $payload['name'] ?? null,
            'last_name' => $payload['last_name'] ?? null,
            'email_contact' => $email,
            'sms_contact' => $phone,
            'whatsapp_contact' => $whatsapp,
            'status' => 'active',
            'meta_data' => json_encode($payload),
        ]);
    }

    /**
     * Handle birthday trigger (called by scheduler daily)
     */
    public function handleBirthdayTrigger(): array
    {
        $results = [
            'workflows_processed' => 0,
            'contacts_enrolled' => 0,
        ];

        // Find all active workflows with 'birthday' trigger or schedule trigger with birthday type
        $workflows = AutomationWorkflow::active()
            ->where(function ($q) {
                $q->where('trigger_type', 'birthday')
                  ->orWhere(function ($sq) {
                      $sq->where('trigger_type', 'schedule')
                         ->whereJsonContains('trigger_config->type', 'birthday');
                  });
            })
            ->get();

        foreach ($workflows as $workflow) {
            $contactIds = $this->findBirthdayContacts($workflow);

            if (!empty($contactIds)) {
                $enrolled = $this->executionService->enrollContacts($workflow, $contactIds, [
                    'trigger' => 'birthday',
                    'triggered_at' => now()->toISOString(),
                ]);

                $results['workflows_processed']++;
                $results['contacts_enrolled'] += $enrolled['enrolled'];

                // Update last triggered
                $workflow->update(['last_triggered_at' => now()]);
            }
        }

        return $results;
    }

    /**
     * Find contacts with birthday today
     */
    protected function findBirthdayContacts(AutomationWorkflow $workflow): array
    {
        $config = $workflow->trigger_config ?? [];
        $daysBefore = (int)($config['days_before'] ?? 0);
        $groupId = $config['group_id'] ?? null;

        // Build query for contacts with meta_data
        $query = Contact::where('status', 'active')
            ->whereNotNull('meta_data');

        if ($workflow->user_id) {
            $query->where('user_id', $workflow->user_id);
        }

        if ($groupId) {
            $query->where('group_id', $groupId);
        }

        // Find contacts with matching birthday (month and day)
        // Uses the Contact model's isBirthdayToday() helper method
        $contacts = $query->get()->filter(function ($contact) use ($daysBefore) {
            return $contact->isBirthdayToday($daysBefore);
        });

        if ($contacts->isEmpty()) {
            return [];
        }

        // Exclude contacts already triggered today for this workflow
        $alreadyTriggeredToday = $workflow->triggerLogs()
            ->whereDate('created_at', now()->toDateString())
            ->pluck('contact_id')
            ->toArray();

        $contactIds = $contacts->pluck('id')
            ->diff($alreadyTriggeredToday)
            ->values()
            ->toArray();

        Log::info('Birthday contacts found', [
            'workflow_id' => $workflow->id,
            'total_found' => $contacts->count(),
            'after_filter' => count($contactIds),
            'days_before' => $daysBefore,
        ]);

        return $contactIds;
    }

    /**
     * Get available trigger types with metadata
     */
    public static function getAvailableTriggers(): array
    {
        return AutomationWorkflow::TRIGGER_TYPES;
    }

    /**
     * Generate webhook URL for a workflow
     */
    public function generateWebhookUrl(AutomationWorkflow $workflow): string
    {
        $webhookId = $workflow->trigger_config['webhook_id'] ?? $workflow->uid;

        return route('api.automation.webhook', ['id' => $webhookId]);
    }
}
