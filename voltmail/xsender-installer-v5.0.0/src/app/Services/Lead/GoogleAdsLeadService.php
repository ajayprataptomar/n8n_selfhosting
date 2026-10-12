<?php

namespace App\Services\Lead;

use App\Models\Contact;
use App\Models\GoogleAdsLeadSubscription;
use App\Services\Automation\WorkflowExecutionService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Receives Google Ads Lead Form Extension webhook payloads, validates the shared
 * key against the matching subscription, and creates / enrolls a Contact.
 *
 * Payload shape (Google docs):
 *   {
 *     "lead_id":"...",
 *     "user_column_data":[
 *       {"column_name":"EMAIL","string_value":"..."},
 *       {"column_name":"FULL_NAME","string_value":"..."},
 *       {"column_name":"PHONE_NUMBER","string_value":"..."}
 *     ],
 *     "api_version":"1.0",
 *     "form_id": 123456789,
 *     "campaign_id": "...",
 *     "google_key": "<shared HMAC>",
 *     "is_test": false
 *   }
 */
class GoogleAdsLeadService
{
    public function processLead(array $payload): ?Contact
    {
        $formId      = (string) ($payload['form_id'] ?? '');
        $customerId  = (string) ($payload['google_key_customer_id'] ?? $payload['customer_id'] ?? '');
        $sharedKey   = (string) ($payload['google_key'] ?? '');

        if ($formId === '' || $sharedKey === '') {
            Log::warning('GoogleAdsLead: missing form_id or google_key', ['payload' => $payload]);
            return null;
        }

        // Match by form_id + verify shared key. Customer ID is optional secondary filter.
        $sub = GoogleAdsLeadSubscription::where('form_id', $formId)
            ->when($customerId !== '', fn ($q) => $q->where('customer_id', $customerId))
            ->where('is_active', true)
            ->get()
            ->first(fn ($s) => hash_equals((string) $s->shared_key, $sharedKey));

        if (!$sub) {
            Log::info('GoogleAdsLead: no matching subscription (or key mismatch)', [
                'form_id' => $formId, 'customer_id' => $customerId,
            ]);
            return null;
        }

        $fields = $this->normalizeFields($payload['user_column_data'] ?? []);
        if (empty($fields)) {
            Log::warning('GoogleAdsLead: empty user_column_data', ['lead_id' => $payload['lead_id'] ?? null]);
            return null;
        }

        $email = $fields['email']        ?? null;
        $phone = $fields['phone_number'] ?? null;

        if (!$email && !$phone) {
            Log::warning('GoogleAdsLead: lead has no email or phone', ['fields' => $fields]);
            return null;
        }

        $firstName = $fields['first_name'] ?? null;
        $lastName  = $fields['last_name']  ?? null;
        if (!$firstName && !empty($fields['full_name'])) {
            $parts = explode(' ', trim($fields['full_name']), 2);
            $firstName = $parts[0] ?? null;
            $lastName  = $lastName ?: ($parts[1] ?? null);
        }

        $contact = Contact::query()
            ->when($sub->contact_group_id, fn ($q) => $q->where('group_id', $sub->contact_group_id))
            ->when($email, fn ($q) => $q->orWhere('email_contact', $email))
            ->when($phone, fn ($q) => $q->orWhere('sms_contact', $phone))
            ->first();

        $data = array_filter([
            'user_id'          => $sub->user_id,
            'group_id'         => $sub->contact_group_id,
            'first_name'       => $firstName,
            'last_name'        => $lastName,
            'email_contact'    => $email,
            'sms_contact'      => $phone,
            'whatsapp_contact' => $phone,
            'meta_data'        => json_encode([
                'source'      => 'google_ads_lead',
                'lead_id'     => $payload['lead_id']     ?? null,
                'form_id'     => $formId,
                'campaign_id' => $payload['campaign_id'] ?? null,
                'is_test'     => $payload['is_test']     ?? false,
                'raw_fields'  => $fields,
            ]),
        ], fn ($v) => $v !== null && $v !== '');

        if ($contact) {
            $contact->fill($data)->save();
        } else {
            $data['uid'] = (string) Str::uuid();
            $contact = Contact::create($data);
        }

        $sub->increment('leads_imported');
        $sub->update(['last_lead_at' => now()]);

        if ($sub->workflow_id && !($payload['is_test'] ?? false)) {
            $this->enrollInWorkflow($contact, (int) $sub->workflow_id);
        }

        return $contact;
    }

    /**
     * Convert Google's user_column_data array into a flat lowercase-keyed map.
     */
    protected function normalizeFields(array $columns): array
    {
        $out = [];
        foreach ($columns as $col) {
            $name  = strtolower((string) ($col['column_name'] ?? ''));
            $value = $col['string_value'] ?? null;
            if ($name === '' || $value === null || $value === '') continue;
            $out[$name] = $value;
        }
        return $out;
    }

    protected function enrollInWorkflow(Contact $contact, int $workflowId): void
    {
        try {
            $workflow = \App\Models\Automation\AutomationWorkflow::find($workflowId);
            if (!$workflow || !$workflow->isActive()) return;
            (new WorkflowExecutionService())->startExecution($workflow, $contact, [
                'source'    => 'google_ads_lead',
                'triggered' => now()->toISOString(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('GoogleAdsLead: enrollment failed', [
                'contact_id' => $contact->id, 'workflow_id' => $workflowId, 'error' => $e->getMessage(),
            ]);
        }
    }
}
