<?php

namespace App\Services\B2bDatabase\Drivers;

use App\Models\B2bProvider;
use App\Services\B2bDatabase\Contracts\B2bDriverInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;

/**
 * RocketReach API Driver
 *
 * Search: POST /person/search (0 credits, returns names+titles)
 * Reveal: GET /person/lookup?name=&current_employer= (1 credit)
 * Rate limit: Plan-dependent
 */
class RocketReachDriver implements B2bDriverInterface
{
    protected B2bProvider $provider;
    protected string $baseUrl = 'https://api.rocketreach.co/api/v2';

    public function setProvider(B2bProvider $provider): static
    {
        $this->provider = $provider;
        return $this;
    }

    public function testConnection(): array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->timeout(10)
                ->get("{$this->baseUrl}/account");

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'message' => 'Connected to RocketReach',
                    'credits_remaining' => $data['lookups_remaining'] ?? null,
                ];
            }

            return ['success' => false, 'message' => 'Invalid API key', 'credits_remaining' => null];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'credits_remaining' => null];
        }
    }

    public function search(array $filters, int $page = 1, int $perPage = 25): array
    {
        try {
            $query = [];

            if ($title = Arr::get($filters, 'job_title')) {
                $query['current_title'] = is_array($title) ? $title : explode(',', $title);
            }
            if ($company = Arr::get($filters, 'company')) {
                $query['current_employer'] = is_array($company) ? $company : [$company];
            }
            if ($location = Arr::get($filters, 'location')) {
                $query['location'] = is_array($location) ? $location : [$location];
            }
            if ($keywords = Arr::get($filters, 'keywords')) {
                $query['keyword'] = $keywords;
            }
            if ($name = Arr::get($filters, 'name')) {
                $query['name'] = $name;
            }

            $body = [
                'query' => $query,
                'start' => ($page - 1) * $perPage + 1,
                'page_size' => min($perPage, 100),
            ];

            $response = Http::withHeaders($this->headers())
                ->timeout(30)
                ->post("{$this->baseUrl}/person/search", $body);

            if (!$response->successful()) {
                return ['success' => false, 'total' => 0, 'results' => [], 'credits_used' => 0, 'page' => $page, 'per_page' => $perPage, 'error' => $response->body()];
            }

            $data = $response->json();
            $profiles = $data['profiles'] ?? [];

            $results = array_map(function ($profile) {
                $nameParts = explode(' ', $profile['name'] ?? '', 2);
                return [
                    'provider_id'    => $profile['id'] ?? null,
                    'first_name'     => $nameParts[0] ?? '',
                    'last_name'      => $nameParts[1] ?? '',
                    'email'          => null, // Not revealed in search
                    'phone'          => null,
                    'job_title'      => $profile['current_title'] ?? '',
                    'company'        => $profile['current_employer'] ?? '',
                    'company_domain' => '',
                    'linkedin_url'   => $profile['linkedin_url'] ?? '',
                    'location'       => $profile['location'] ?? '',
                    'seniority'      => '',
                    'department'     => '',
                    'is_revealed'    => false,
                ];
            }, $profiles);

            return [
                'success'      => true,
                'total'        => $data['pagination']['total'] ?? count($results),
                'results'      => $results,
                'credits_used' => 0, // RocketReach search is free
                'page'         => $page,
                'per_page'     => $perPage,
            ];
        } catch (\Throwable $e) {
            Log::error('RocketReach search failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'total' => 0, 'results' => [], 'credits_used' => 0, 'page' => $page, 'per_page' => $perPage, 'error' => $e->getMessage()];
        }
    }

    public function reveal(array $prospects): array
    {
        $contacts = [];
        $creditsUsed = 0;

        foreach ($prospects as $prospect) {
            try {
                $params = [];
                if (isset($prospect['provider_id'])) {
                    $params['id'] = $prospect['provider_id'];
                } else {
                    $name = trim(($prospect['first_name'] ?? '') . ' ' . ($prospect['last_name'] ?? ''));
                    if ($name) $params['name'] = $name;
                    if ($employer = ($prospect['company'] ?? '')) $params['current_employer'] = $employer;
                    if ($linkedin = ($prospect['linkedin_url'] ?? '')) $params['linkedin_url'] = $linkedin;
                }

                $response = Http::withHeaders($this->headers())
                    ->timeout(15)
                    ->get("{$this->baseUrl}/person/lookup", $params);

                if ($response->successful()) {
                    $person = $response->json();
                    $nameParts = explode(' ', $person['name'] ?? '', 2);

                    $contacts[] = [
                        'first_name'     => $nameParts[0] ?? $prospect['first_name'] ?? '',
                        'last_name'      => $nameParts[1] ?? $prospect['last_name'] ?? '',
                        'email'          => $person['current_personal_email'] ?? $person['current_work_email'] ?? ($person['emails'][0] ?? null),
                        'phone'          => $person['phones'][0]['number'] ?? null,
                        'job_title'      => $person['current_title'] ?? '',
                        'company'        => $person['current_employer'] ?? '',
                        'company_domain' => '',
                        'linkedin_url'   => $person['linkedin_url'] ?? '',
                        'quality_score'  => $this->calculateQualityScore($person),
                    ];
                    $creditsUsed++;
                }
            } catch (\Throwable $e) {
                Log::warning('RocketReach reveal failed', ['error' => $e->getMessage()]);
            }
        }

        return ['success' => true, 'contacts' => $contacts, 'credits_used' => $creditsUsed];
    }

    public function verifyEmail(string $email): array
    {
        return ['success' => true, 'result' => 'unknown', 'score' => null];
    }

    public function getQuota(): array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->timeout(10)
                ->get("{$this->baseUrl}/account");

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'credits_remaining' => $data['lookups_remaining'] ?? null,
                    'plan' => $data['plan'] ?? null,
                ];
            }

            return ['success' => false, 'credits_remaining' => null, 'plan' => null];
        } catch (\Throwable $e) {
            return ['success' => false, 'credits_remaining' => null, 'plan' => null];
        }
    }

    protected function headers(): array
    {
        return [
            'Api-Key'      => $this->provider->getCredential('api_key'),
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ];
    }

    protected function calculateQualityScore(array $person): int
    {
        $score = 0;
        if (!empty($person['current_work_email']) || !empty($person['emails'])) $score += 40;
        if (!empty($person['phones'])) $score += 20;
        if (!empty($person['linkedin_url'])) $score += 15;
        if (!empty($person['current_title'])) $score += 10;
        if (!empty($person['current_employer'])) $score += 10;
        if (!empty($person['location'])) $score += 5;
        return min($score, 100);
    }
}
