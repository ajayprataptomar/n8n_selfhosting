<?php

namespace App\Services\LeadGeneration;

use App\Models\LeadScrapingJob;
use App\Models\ScrapedLead;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LeadEnrichmentService
{
    /**
     * Disposable email domains list (partial)
     */
    protected array $disposableDomains = [
        'tempmail.com', 'temp-mail.org', 'guerrillamail.com', '10minutemail.com',
        'mailinator.com', 'throwaway.email', 'fakeinbox.com', 'discard.email',
        'yopmail.com', 'trashmail.com', 'sharklasers.com', 'maildrop.cc',
        'getairmail.com', 'mailnesia.com', 'emailondeck.com', 'tempail.com',
        'mohmal.com', 'getnada.com', 'tempmailaddress.com', 'tempmailo.com',
    ];

    /**
     * Free email providers (not necessarily disposable)
     */
    protected array $freeProviders = [
        'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'aol.com',
        'icloud.com', 'mail.com', 'protonmail.com', 'zoho.com', 'gmx.com',
    ];

    /**
     * Enrich a single lead with additional data
     */
    public function enrichLead(ScrapedLead $lead): ScrapedLead
    {
        $enrichedData = [];

        // Validate and enrich email
        if ($lead->email) {
            $emailData = $this->validateEmail($lead->email);
            $enrichedData['email_verified'] = $emailData['is_valid'];

            // Store additional email info in raw_data
            $rawData = $lead->raw_data ?? [];
            $rawData['email_validation'] = $emailData;
            $enrichedData['raw_data'] = $rawData;
        }

        // Validate phone number
        if ($lead->phone) {
            $phoneData = $this->validatePhone($lead->phone);
            $enrichedData['phone_verified'] = $phoneData['is_valid'];

            // Extract country from phone if not set
            if (!$lead->country && $phoneData['country']) {
                $enrichedData['country'] = $phoneData['country'];
            }
        }

        // Try to scrape website for more data
        if ($lead->website && (!$lead->email || !$lead->phone)) {
            $websiteData = $this->scrapeWebsiteForMissingData($lead);

            if (!$lead->email && !empty($websiteData['email'])) {
                $enrichedData['email'] = $websiteData['email'];
            }

            if (!$lead->phone && !empty($websiteData['phone'])) {
                $enrichedData['phone'] = $websiteData['phone'];
            }

            // Add social profiles if missing
            foreach (['facebook', 'instagram', 'twitter', 'linkedin'] as $platform) {
                if (!$lead->$platform && !empty($websiteData[$platform])) {
                    $enrichedData[$platform] = $websiteData[$platform];
                }
            }
        }

        // Update lead with enriched data
        if (!empty($enrichedData)) {
            $lead->update($enrichedData);

            // Recalculate quality score
            $lead->update([
                'quality_score' => $lead->calculateQualityScore(),
            ]);
        }

        return $lead->fresh();
    }

    /**
     * Enrich multiple leads
     */
    public function enrichLeads(array $leadIds): int
    {
        $enrichedCount = 0;

        $leads = ScrapedLead::whereIn('id', $leadIds)->get();

        foreach ($leads as $lead) {
            try {
                $this->enrichLead($lead);
                $enrichedCount++;
            } catch (\Exception $e) {
                Log::warning('Failed to enrich lead', [
                    'lead_id' => $lead->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        return $enrichedCount;
    }

    /**
     * Validate email address
     */
    public function validateEmail(string $email): array
    {
        $result = [
            'email'         => $email,
            'is_valid'      => false,
            'is_disposable' => false,
            'is_free'       => false,
            'domain'        => null,
            'mx_records'    => [],
        ];

        // Basic format validation
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $result;
        }

        $parts = explode('@', $email);
        $domain = strtolower($parts[1] ?? '');
        $result['domain'] = $domain;

        // Check if disposable
        $result['is_disposable'] = $this->isDisposableEmail($domain);

        // Check if free provider
        $result['is_free'] = in_array($domain, $this->freeProviders);

        // Check MX records
        $mxRecords = $this->getMxRecords($domain);
        $result['mx_records'] = $mxRecords;

        // Email is valid if it has MX records and is not disposable
        $result['is_valid'] = !empty($mxRecords) && !$result['is_disposable'];

        return $result;
    }

    /**
     * Check if email domain is disposable
     */
    protected function isDisposableEmail(string $domain): bool
    {
        // Check against known disposable domains
        if (in_array($domain, $this->disposableDomains)) {
            return true;
        }

        // Check common patterns for disposable emails
        $disposablePatterns = [
            'temp', 'trash', 'throw', 'fake', 'disposable',
            '10minute', '1minute', 'guerrilla', 'mailinator',
        ];

        foreach ($disposablePatterns as $pattern) {
            if (Str::contains($domain, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get MX records for a domain
     */
    protected function getMxRecords(string $domain): array
    {
        $mxRecords = [];

        try {
            if (getmxrr($domain, $hosts, $weights)) {
                for ($i = 0; $i < count($hosts); $i++) {
                    $mxRecords[] = [
                        'host'   => $hosts[$i],
                        'weight' => $weights[$i] ?? 0,
                    ];
                }
            }
        } catch (\Exception $e) {
            // DNS lookup failed
        }

        return $mxRecords;
    }

    /**
     * Validate phone number
     */
    public function validatePhone(string $phone): array
    {
        $result = [
            'phone'     => $phone,
            'is_valid'  => false,
            'country'   => null,
            'formatted' => null,
            'type'      => null,
        ];

        // Clean the phone number
        $cleaned = preg_replace('/[^0-9+]/', '', $phone);

        // Check minimum length
        if (strlen($cleaned) < 7) {
            return $result;
        }

        // Try to detect country from prefix
        $countryData = $this->detectCountryFromPhone($cleaned);
        if ($countryData) {
            $result['country'] = $countryData['country'];
            $result['formatted'] = $countryData['formatted'];
        }

        // Basic validation
        $result['is_valid'] = strlen($cleaned) >= 7 && strlen($cleaned) <= 15;

        return $result;
    }

    /**
     * Detect country from phone number prefix
     */
    protected function detectCountryFromPhone(string $phone): ?array
    {
        // Remove leading + or 00
        $phone = preg_replace('/^(\+|00)/', '', $phone);

        // Common country codes
        $countryCodes = [
            '1'   => 'United States',
            '44'  => 'United Kingdom',
            '91'  => 'India',
            '86'  => 'China',
            '81'  => 'Japan',
            '49'  => 'Germany',
            '33'  => 'France',
            '39'  => 'Italy',
            '7'   => 'Russia',
            '55'  => 'Brazil',
            '61'  => 'Australia',
            '971' => 'UAE',
            '966' => 'Saudi Arabia',
            '65'  => 'Singapore',
            '60'  => 'Malaysia',
            '62'  => 'Indonesia',
            '63'  => 'Philippines',
            '880' => 'Bangladesh',
            '92'  => 'Pakistan',
        ];

        // Try to match 3-digit codes first, then 2-digit, then 1-digit
        foreach ([3, 2, 1] as $len) {
            $prefix = substr($phone, 0, $len);
            if (isset($countryCodes[$prefix])) {
                return [
                    'country'   => $countryCodes[$prefix],
                    'code'      => $prefix,
                    'formatted' => '+' . $phone,
                ];
            }
        }

        return null;
    }

    /**
     * Scrape website for missing contact data
     */
    protected function scrapeWebsiteForMissingData(ScrapedLead $lead): array
    {
        $websiteService = new WebsiteScraperService();

        try {
            return $websiteService->scrapeWebsite($lead->website, [
                'max_pages' => 3,
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to scrape website for enrichment', [
                'lead_id' => $lead->id,
                'website' => $lead->website,
                'error'   => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Process an enrichment job
     */
    public function processJob(LeadScrapingJob $job): void
    {
        $job->markAsProcessing();

        $params = $job->parameters ?? [];
        $leadIds = $params['lead_ids'] ?? [];

        if (empty($leadIds)) {
            $job->markAsFailed('No leads provided for enrichment');
            return;
        }

        try {
            $leads = ScrapedLead::whereIn('id', $leadIds)
                ->where(function ($query) use ($job) {
                    if ($job->user_id) {
                        $query->where('user_id', $job->user_id);
                    }
                })
                ->get();

            $job->update(['total_found' => $leads->count()]);

            foreach ($leads as $lead) {
                try {
                    $this->enrichLead($lead);
                    $job->incrementProcessed();
                } catch (\Exception $e) {
                    Log::warning('Failed to enrich lead', [
                        'lead_id' => $lead->id,
                        'error'   => $e->getMessage(),
                    ]);
                }
            }

            $job->markAsCompleted();

        } catch (\Exception $e) {
            Log::error('Lead enrichment job failed', [
                'job_id' => $job->id,
                'error'  => $e->getMessage(),
            ]);
            $job->markAsFailed($e->getMessage());
        }
    }

    /**
     * Calculate a lead score based on quality indicators
     */
    public function calculateLeadScore(ScrapedLead $lead): int
    {
        $score = 0;

        // Email quality (max 30)
        if ($lead->email) {
            $score += 10;
            if ($lead->email_verified) {
                $score += 15;
            }
            // Prefer business emails over free providers
            $domain = explode('@', $lead->email)[1] ?? '';
            if (!in_array($domain, $this->freeProviders)) {
                $score += 5;
            }
        }

        // Phone quality (max 20)
        if ($lead->phone) {
            $score += 10;
            if ($lead->phone_verified) {
                $score += 10;
            }
        }

        // Business info (max 25)
        if ($lead->business_name) $score += 5;
        if ($lead->website) $score += 5;
        if ($lead->address) $score += 5;
        if ($lead->rating && $lead->rating >= 4.0) $score += 5;
        if ($lead->reviews_count && $lead->reviews_count >= 10) $score += 5;

        // Social presence (max 15)
        $socialCount = 0;
        if ($lead->facebook) $socialCount++;
        if ($lead->instagram) $socialCount++;
        if ($lead->twitter) $socialCount++;
        if ($lead->linkedin) $socialCount++;
        $score += min($socialCount * 4, 15);

        // Data completeness (max 10)
        if ($lead->city) $score += 3;
        if ($lead->country) $score += 3;
        if ($lead->category) $score += 4;

        return min($score, 100);
    }
}
