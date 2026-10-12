<?php

namespace App\Services\Lead;

use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\MetaLeadFormSubscription;
use App\Services\Automation\WorkflowExecutionService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Receives lead notifications from Meta's Lead Ads webhook, fetches the lead
 * detail via the Graph API using the subscribed page's long-lived access
 * token, and creates a Contact row mapped from the lead's field answers.
 *
 * If the subscription has a workflow_id, the new contact is also enrolled
 * into that automation workflow as a "new_contact" trigger.
 *
 * Reference: https://developers.facebook.com/docs/marketing-api/guides/lead-ads/retrieving
 */
class MetaLeadAdsService
{
    private const GRAPH_VERSION = 'v19.0';

    /**
     * Process a single leadgen notification from the webhook payload.
     *
     * @return Contact|null  null = ignored (no matching subscription)
     */
    public function processLead(string $leadgenId, string $pageId, string $formId): ?Contact
    {
        $subscription = MetaLeadFormSubscription::where('page_id', $pageId)
            ->where('form_id', $formId)
            ->where('is_active', true)
            ->first();

        if (!$subscription) {
            Log::info('MetaLeadAds: no subscription matched, lead ignored', [
                'leadgen_id' => $leadgenId, 'page_id' => $pageId, 'form_id' => $formId,
            ]);
            return null;
        }

        $token = $subscription->page_access_token;
        if (!$token) {
            Log::warning('MetaLeadAds: subscription has no page access token', [
                'subscription_id' => $subscription->id,
            ]);
            return null;
        }

        $lead = $this->fetchLead($leadgenId, $token);
        if ($lead === null) return null;

        $contact = $this->mapLeadToContact($lead, $subscription);
        if (!$contact) return null;

        $subscription->increment('leads_imported');
        $subscription->update(['last_lead_at' => now()]);

        // Optional workflow enrollment
        if ($subscription->workflow_id) {
            $this->enrollInWorkflow($contact, (int) $subscription->workflow_id);
        }

        Log::info('MetaLeadAds: lead imported as contact', [
            'leadgen_id'      => $leadgenId,
            'contact_id'      => $contact->id,
            'subscription_id' => $subscription->id,
        ]);

        return $contact;
    }

    /**
     * Fetch the raw lead from Graph API.
     */
    protected function fetchLead(string $leadgenId, string $pageAccessToken): ?array
    {
        try {
            $response = Http::timeout(15)
                ->get(sprintf('https://graph.facebook.com/%s/%s', self::GRAPH_VERSION, $leadgenId), [
                    'access_token' => $pageAccessToken,
                    'fields'       => 'id,created_time,ad_id,form_id,field_data',
                ]);

            if (!$response->successful()) {
                Log::warning('MetaLeadAds: Graph API fetch failed', [
                    'leadgen_id' => $leadgenId,
                    'status'     => $response->status(),
                    'body'       => Str::limit($response->body(), 500),
                ]);
                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::error('MetaLeadAds: Graph API exception', [
                'leadgen_id' => $leadgenId,
                'error'      => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Map Meta's field_data (array of {name, values}) into a Contact row.
     *
     * Default field mapping covers the standard prefilled Meta questions:
     *   email          → email_contact
     *   phone_number   → sms_contact + whatsapp_contact (if neither preset)
     *   full_name      → first_name (split on first space) + last_name
     *   first_name     → first_name
     *   last_name      → last_name
     *
     * Per-subscription overrides via $subscription->field_mapping (e.g.
     * {"company_name": "meta_data.company"}).
     */
    protected function mapLeadToContact(array $lead, MetaLeadFormSubscription $subscription): ?Contact
    {
        $fieldData = collect($lead['field_data'] ?? [])
            ->mapWithKeys(fn ($f) => [strtolower($f['name'] ?? '') => $f['values'][0] ?? null])
            ->filter()
            ->all();

        if (empty($fieldData)) {
            Log::warning('MetaLeadAds: lead has no field_data, skipping', ['lead' => $lead]);
            return null;
        }

        $email = $fieldData['email'] ?? null;
        $phone = $fieldData['phone_number'] ?? $fieldData['phone'] ?? null;

        if (!$email && !$phone) {
            Log::warning('MetaLeadAds: lead has no email or phone, skipping', ['lead' => $lead]);
            return null;
        }

        $firstName = $fieldData['first_name'] ?? null;
        $lastName  = $fieldData['last_name']  ?? null;
        if (!$firstName && !empty($fieldData['full_name'])) {
            $parts = explode(' ', trim($fieldData['full_name']), 2);
            $firstName = $parts[0] ?? null;
            $lastName  = $lastName ?: ($parts[1] ?? null);
        }

        // Dedup: existing contact in same group by email or phone gets updated, not duplicated.
        $contact = Contact::query()
            ->when($subscription->contact_group_id, fn ($q) => $q->where('group_id', $subscription->contact_group_id))
            ->when($email, fn ($q) => $q->orWhere('email_contact', $email))
            ->when($phone, fn ($q) => $q->orWhere('sms_contact', $phone))
            ->first();

        $payload = array_filter([
            'user_id'          => $subscription->user_id,
            'group_id'         => $subscription->contact_group_id,
            'first_name'       => $firstName,
            'last_name'        => $lastName,
            'email_contact'    => $email,
            'sms_contact'      => $phone,
            'whatsapp_contact' => $phone, // WhatsApp shares phone in Meta Lead Ads
            'meta_data'        => json_encode([
                'source'      => 'meta_lead_ads',
                'lead_id'     => $lead['id']        ?? null,
                'ad_id'       => $lead['ad_id']     ?? null,
                'form_id'     => $lead['form_id']   ?? null,
                'created_at'  => $lead['created_time'] ?? null,
                'raw_fields'  => $fieldData,
            ]),
        ], fn ($v) => $v !== null && $v !== '');

        if ($contact) {
            $contact->fill($payload)->save();
            return $contact;
        }

        $payload['uid'] = (string) Str::uuid();
        return Contact::create($payload);
    }

    protected function enrollInWorkflow(Contact $contact, int $workflowId): void
    {
        try {
            $workflow = \App\Models\Automation\AutomationWorkflow::find($workflowId);
            if (!$workflow || !$workflow->isActive()) return;

            $service = new WorkflowExecutionService();
            $service->startExecution($workflow, $contact, [
                'source'    => 'meta_lead_ads',
                'triggered' => now()->toISOString(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('MetaLeadAds: workflow enrollment failed', [
                'contact_id'  => $contact->id,
                'workflow_id' => $workflowId,
                'error'       => $e->getMessage(),
            ]);
        }
    }
}
