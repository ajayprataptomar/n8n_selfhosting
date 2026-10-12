<?php

namespace App\Services\B2bDatabase;

use App\Models\User;
use App\Models\Contact;
use App\Models\B2bProvider;
use App\Models\B2bSearchLog;
use App\Models\ContactGroup;
use App\Managers\B2bProviderManager;
use App\Services\B2bDatabase\Contracts\B2bDriverInterface;
use Illuminate\Support\Facades\Log;

class B2bProviderService
{
    protected B2bProviderManager $manager;

    public function __construct()
    {
        $this->manager = new B2bProviderManager();
    }

    /**
     * Test provider connection
     */
    public function testConnection(B2bProvider $provider): array
    {
        $driver = $this->manager->driver($provider);
        return $driver->testConnection();
    }

    /**
     * Search for prospects
     */
    public function search(B2bProvider $provider, array $filters, int $page = 1, int $perPage = 25, ?User $user = null): array
    {
        $provider->resetMonthlyIfNeeded();

        $driver = $this->manager->driver($provider);
        $result = $driver->search($filters, $page, $perPage);

        if ($result['success'] && $result['credits_used'] > 0) {
            $provider->incrementLookups($result['credits_used']);
        }

        // Log the search
        if ($result['success']) {
            B2bSearchLog::create([
                'user_id'        => $user?->id ?? $provider->user_id,
                'provider_id'    => $provider->id,
                'provider_type'  => $provider->type instanceof \BackedEnum ? $provider->type->value : $provider->type,
                'search_filters' => $filters,
                'total_results'  => $result['total'] ?? 0,
                'credits_used'   => $result['credits_used'] ?? 0,
                'status'         => 'completed',
            ]);
        }

        return $result;
    }

    /**
     * Reveal contact details and import to a contact group
     */
    public function revealAndImport(
        B2bProvider $provider,
        array $prospects,
        int $groupId,
        ?User $user = null,
        bool $skipDuplicates = true
    ): array {
        $driver = $this->manager->driver($provider);
        $revealResult = $driver->reveal($prospects);

        if (!$revealResult['success']) {
            return ['success' => false, 'imported' => 0, 'duplicates_skipped' => 0, 'error' => 'Reveal failed'];
        }

        $imported = 0;
        $duplicatesSkipped = 0;
        $userId = $user?->id ?? $provider->user_id;

        foreach ($revealResult['contacts'] as $contact) {
            if ($skipDuplicates && $this->isDuplicate($contact, $groupId, $userId)) {
                $duplicatesSkipped++;
                continue;
            }

            Contact::create([
                'user_id'          => $userId,
                'group_id'         => $groupId,
                'first_name'       => $contact['first_name'] ?? null,
                'last_name'        => $contact['last_name'] ?? null,
                'email_contact'    => $contact['email'] ?? null,
                'sms_contact'      => $contact['phone'] ?? null,
                'whatsapp_contact' => $contact['phone'] ?? null,
                'meta_data'        => json_encode([
                    'job_title'      => ['value' => $contact['job_title'] ?? ''],
                    'company'        => ['value' => $contact['company'] ?? ''],
                    'company_domain' => ['value' => $contact['company_domain'] ?? ''],
                    'linkedin_url'   => ['value' => $contact['linkedin_url'] ?? ''],
                    'quality_score'  => ['value' => $contact['quality_score'] ?? 0],
                    'source'         => ['value' => 'b2b_database'],
                    'b2b_provider'   => ['value' => $provider->type instanceof \BackedEnum ? $provider->type->value : $provider->type],
                ]),
            ]);
            $imported++;
        }

        // Update provider stats
        if ($revealResult['credits_used'] > 0) {
            $provider->incrementLookups($revealResult['credits_used']);
        }

        // Update the search log
        $latestLog = B2bSearchLog::where('provider_id', $provider->id)
            ->where('user_id', $userId)
            ->latest()
            ->first();

        if ($latestLog) {
            $latestLog->update([
                'imported_count'       => $latestLog->imported_count + $imported,
                'credits_used'         => $latestLog->credits_used + $revealResult['credits_used'],
                'imported_to_group_id' => $groupId,
            ]);
        }

        return [
            'success'            => true,
            'imported'           => $imported,
            'duplicates_skipped' => $duplicatesSkipped,
            'credits_used'       => $revealResult['credits_used'],
        ];
    }

    /**
     * Check if contact already exists in group
     */
    protected function isDuplicate(array $contact, int $groupId, ?int $userId): bool
    {
        $query = Contact::where('group_id', $groupId);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        if (!empty($contact['email'])) {
            $exists = (clone $query)->where('email_contact', $contact['email'])->exists();
            if ($exists) return true;
        }

        if (!empty($contact['phone'])) {
            $exists = (clone $query)->where(function ($q) use ($contact) {
                $q->where('sms_contact', $contact['phone'])
                  ->orWhere('whatsapp_contact', $contact['phone']);
            })->exists();
            if ($exists) return true;
        }

        return false;
    }
}
