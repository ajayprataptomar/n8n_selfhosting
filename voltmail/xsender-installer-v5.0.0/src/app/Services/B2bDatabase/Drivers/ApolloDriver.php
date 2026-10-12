<?php

namespace App\Services\B2bDatabase\Drivers;

use App\Models\B2bProvider;
use App\Services\B2bDatabase\Contracts\B2bDriverInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;

/**
 * Apollo.io API Driver
 *
 * Search: POST /mixed_people/api_search (0 credits, returns names+titles only)
 * Reveal: POST /people/match (1 credit/email, 5-8/mobile)
 * Free tier: 250 emails/day
 */
class ApolloDriver implements B2bDriverInterface
{
    protected B2bProvider $provider;
    protected string $baseUrl = 'https://api.apollo.io/api/v1';

    public function setProvider(B2bProvider $provider): static
    {
        $this->provider = $provider;
        return $this;
    }

    public function testConnection(): array
    {
        try {
            $apiKey = $this->provider->getCredential('api_key');
            if (empty($apiKey)) {
                return ['success' => false, 'message' => 'API key is required', 'credits_remaining' => null];
            }

            // Apollo: test with a minimal search (0 credits)
            $response = Http::withHeaders($this->headers())
                ->timeout(15)
                ->post("{$this->baseUrl}/mixed_people/search", [
                    'page' => 1,
                    'per_page' => 1,
                    'person_titles' => ['CEO'],
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $total = $data['pagination']['total_entries'] ?? 0;
                return [
                    'success' => true,
                    'message' => "Connected to Apollo.io ({$total} contacts available)",
                    'credits_remaining' => null,
                ];
            }

            $error = $response->json('error') ?? $response->json('message') ?? 'Invalid API key';
            return ['success' => false, 'message' => is_string($error) ? $error : 'Invalid API key or connection failed', 'credits_remaining' => null];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'credits_remaining' => null];
        }
    }

    public function search(array $filters, int $page = 1, int $perPage = 25): array
    {
        try {
            $body = [
                'page'     => $page,
                'per_page' => min($perPage, 100),
            ];

            if ($title = Arr::get($filters, 'job_title')) {
                $body['person_titles'] = is_array($title) ? $title : explode(',', $title);
            }
            if ($seniority = Arr::get($filters, 'seniority')) {
                $body['person_seniorities'] = is_array($seniority) ? $seniority : [$seniority];
            }
            if ($location = Arr::get($filters, 'location')) {
                $body['person_locations'] = is_array($location) ? $location : [$location];
            }
            if ($company = Arr::get($filters, 'company')) {
                $body['organization_names'] = is_array($company) ? $company : [$company];
            }
            if ($domain = Arr::get($filters, 'domain')) {
                $body['organization_domains'] = is_array($domain) ? $domain : [$domain];
            }
            if ($industry = Arr::get($filters, 'industry')) {
                $body['organization_industry_tag_ids'] = is_array($industry) ? $industry : [$industry];
            }
            if ($size = Arr::get($filters, 'company_size')) {
                $body['organization_num_employees_ranges'] = is_array($size) ? $size : [$size];
            }
            if ($keywords = Arr::get($filters, 'keywords')) {
                $body['q_keywords'] = $keywords;
            }
            if ($department = Arr::get($filters, 'department')) {
                $body['person_departments'] = is_array($department) ? $department : [$department];
            }

            $response = Http::withHeaders($this->headers())
                ->timeout(30)
                ->post("{$this->baseUrl}/mixed_people/search", $body);

            if (!$response->successful()) {
                return ['success' => false, 'total' => 0, 'results' => [], 'credits_used' => 0, 'page' => $page, 'per_page' => $perPage, 'error' => $response->body()];
            }

            $data = $response->json();
            $people = $data['people'] ?? [];

            $results = array_map(function ($person) {
                return [
                    'provider_id'    => $person['id'] ?? null,
                    'first_name'     => $person['first_name'] ?? '',
                    'last_name'      => $person['last_name'] ?? '',
                    'email'          => $person['email'] ?? null,
                    'phone'          => $person['phone_numbers'][0]['sanitized_number'] ?? null,
                    'job_title'      => $person['title'] ?? '',
                    'company'        => $person['organization']['name'] ?? '',
                    'company_domain' => $person['organization']['primary_domain'] ?? '',
                    'linkedin_url'   => $person['linkedin_url'] ?? '',
                    'location'       => trim(($person['city'] ?? '') . ', ' . ($person['country'] ?? ''), ', '),
                    'seniority'      => $person['seniority'] ?? '',
                    'department'     => implode(', ', $person['departments'] ?? []),
                    'is_revealed'    => !empty($person['email']),
                ];
            }, $people);

            return [
                'success'      => true,
                'total'        => $data['pagination']['total_entries'] ?? count($results),
                'results'      => $results,
                'credits_used' => 0, // Apollo search is free
                'page'         => $page,
                'per_page'     => $perPage,
            ];
        } catch (\Throwable $e) {
            Log::error('Apollo search failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'total' => 0, 'results' => [], 'credits_used' => 0, 'page' => $page, 'per_page' => $perPage, 'error' => $e->getMessage()];
        }
    }

    public function reveal(array $prospects): array
    {
        $contacts = [];
        $creditsUsed = 0;

        foreach ($prospects as $prospect) {
            try {
                $body = [];
                if (isset($prospect['provider_id'])) {
                    $body['id'] = $prospect['provider_id'];
                } else {
                    $body['first_name'] = $prospect['first_name'] ?? '';
                    $body['last_name']  = $prospect['last_name'] ?? '';
                    if (isset($prospect['company_domain'])) {
                        $body['organization_name'] = $prospect['company'] ?? '';
                        $body['domain'] = $prospect['company_domain'];
                    }
                }

                $response = Http::withHeaders($this->headers())
                    ->timeout(15)
                    ->post("{$this->baseUrl}/people/match", $body);

                if ($response->successful()) {
                    $person = $response->json('person') ?? $response->json();
                    $contacts[] = [
                        'first_name'     => $person['first_name'] ?? $prospect['first_name'] ?? '',
                        'last_name'      => $person['last_name'] ?? $prospect['last_name'] ?? '',
                        'email'          => $person['email'] ?? null,
                        'phone'          => $person['phone_numbers'][0]['sanitized_number'] ?? null,
                        'job_title'      => $person['title'] ?? $prospect['job_title'] ?? '',
                        'company'        => $person['organization']['name'] ?? $prospect['company'] ?? '',
                        'company_domain' => $person['organization']['primary_domain'] ?? '',
                        'linkedin_url'   => $person['linkedin_url'] ?? '',
                        'quality_score'  => $this->calculateQualityScore($person),
                    ];
                    $creditsUsed++;
                }
            } catch (\Throwable $e) {
                Log::warning('Apollo reveal failed for prospect', ['error' => $e->getMessage()]);
            }
        }

        return ['success' => true, 'contacts' => $contacts, 'credits_used' => $creditsUsed];
    }

    public function verifyEmail(string $email): array
    {
        // Apollo doesn't have a separate email verification endpoint
        return ['success' => true, 'result' => 'unknown', 'score' => null];
    }

    public function getQuota(): array
    {
        // Apollo doesn't expose quota via API directly
        return ['success' => true, 'credits_remaining' => null, 'plan' => null];
    }

    protected function headers(): array
    {
        return [
            'X-Api-Key'    => $this->provider->getCredential('api_key'),
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ];
    }

    protected function calculateQualityScore(array $person): int
    {
        $score = 0;
        if (!empty($person['email'])) $score += 40;
        if (!empty($person['phone_numbers'])) $score += 20;
        if (!empty($person['linkedin_url'])) $score += 15;
        if (!empty($person['title'])) $score += 10;
        if (!empty($person['organization']['name'])) $score += 10;
        if (!empty($person['city'])) $score += 5;
        return min($score, 100);
    }
}
