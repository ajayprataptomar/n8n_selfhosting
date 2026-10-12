<?php

namespace App\Services\B2bDatabase\Drivers;

use App\Models\B2bProvider;
use App\Services\B2bDatabase\Contracts\B2bDriverInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;

/**
 * Hunter.io API Driver
 *
 * Domain Search: GET /domain-search?domain= (1 credit, returns emails)
 * Email Finder: GET /email-finder?domain=&first_name=&last_name= (1 credit)
 * Email Verifier: GET /email-verifier?email= (0.5 credits)
 * Free tier: 25 searches + 50 verifications/month
 * Rate limit: 15 req/sec, 500 req/min
 */
class HunterDriver implements B2bDriverInterface
{
    protected B2bProvider $provider;
    protected string $baseUrl = 'https://api.hunter.io/v2';

    public function setProvider(B2bProvider $provider): static
    {
        $this->provider = $provider;
        return $this;
    }

    public function testConnection(): array
    {
        try {
            $apiKey = $this->apiKey();
            if (empty($apiKey)) {
                return ['success' => false, 'message' => 'API key is required', 'credits_remaining' => null];
            }

            $response = Http::timeout(15)
                ->get("{$this->baseUrl}/account", ['api_key' => $apiKey]);

            if ($response->successful()) {
                $data = $response->json('data') ?? [];
                $available = $data['requests']['searches']['available'] ?? 0;
                $used = $data['requests']['searches']['used'] ?? 0;
                $remaining = max(0, $available - $used);
                $plan = $data['plan_name'] ?? 'Free';
                return [
                    'success' => true,
                    'message' => "Connected to Hunter.io ({$plan} plan, {$remaining} searches remaining)",
                    'credits_remaining' => $remaining,
                ];
            }

            $error = $response->json('errors.0.details') ?? 'Invalid API key';
            return ['success' => false, 'message' => $error, 'credits_remaining' => null];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'credits_remaining' => null];
        }
    }

    public function search(array $filters, int $page = 1, int $perPage = 25): array
    {
        try {
            $domain = Arr::get($filters, 'domain');
            $company = Arr::get($filters, 'company');

            if (!$domain && !$company) {
                return ['success' => false, 'total' => 0, 'results' => [], 'credits_used' => 0, 'page' => $page, 'per_page' => $perPage, 'error' => 'Hunter.io requires a domain or company name'];
            }

            $params = [
                'api_key' => $this->apiKey(),
                'limit'   => min($perPage, 100),
                'offset'  => ($page - 1) * $perPage,
            ];

            if ($domain) $params['domain'] = $domain;
            if ($company) $params['company'] = $company;
            if ($type = Arr::get($filters, 'type')) $params['type'] = $type; // personal or generic
            if ($seniority = Arr::get($filters, 'seniority')) $params['seniority'] = $seniority;
            if ($department = Arr::get($filters, 'department')) $params['department'] = $department;

            $response = Http::timeout(30)->get("{$this->baseUrl}/domain-search", $params);

            if (!$response->successful()) {
                return ['success' => false, 'total' => 0, 'results' => [], 'credits_used' => 0, 'page' => $page, 'per_page' => $perPage, 'error' => $response->json('errors.0.details') ?? $response->body()];
            }

            $data = $response->json('data') ?? [];
            $emails = $data['emails'] ?? [];

            $results = array_map(function ($email) use ($data) {
                return [
                    'provider_id'    => null,
                    'first_name'     => $email['first_name'] ?? '',
                    'last_name'      => $email['last_name'] ?? '',
                    'email'          => $email['value'] ?? null,
                    'phone'          => $email['phone_number'] ?? null,
                    'job_title'      => $email['position'] ?? '',
                    'company'        => $data['organization'] ?? '',
                    'company_domain' => $data['domain'] ?? '',
                    'linkedin_url'   => $email['linkedin'] ?? '',
                    'location'       => '',
                    'seniority'      => $email['seniority'] ?? '',
                    'department'     => $email['department'] ?? '',
                    'confidence'     => $email['confidence'] ?? 0,
                    'is_revealed'    => true, // Hunter returns emails directly
                ];
            }, $emails);

            return [
                'success'      => true,
                'total'        => $data['total'] ?? count($results),
                'results'      => $results,
                'credits_used' => 1,
                'page'         => $page,
                'per_page'     => $perPage,
            ];
        } catch (\Throwable $e) {
            Log::error('Hunter search failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'total' => 0, 'results' => [], 'credits_used' => 0, 'page' => $page, 'per_page' => $perPage, 'error' => $e->getMessage()];
        }
    }

    public function reveal(array $prospects): array
    {
        $contacts = [];
        $creditsUsed = 0;

        foreach ($prospects as $prospect) {
            $domain = $prospect['company_domain'] ?? '';
            $firstName = $prospect['first_name'] ?? '';
            $lastName = $prospect['last_name'] ?? '';

            if (!$domain || (!$firstName && !$lastName)) {
                continue;
            }

            try {
                $response = Http::timeout(15)->get("{$this->baseUrl}/email-finder", [
                    'api_key'    => $this->apiKey(),
                    'domain'     => $domain,
                    'first_name' => $firstName,
                    'last_name'  => $lastName,
                ]);

                if ($response->successful()) {
                    $data = $response->json('data') ?? [];
                    $contacts[] = [
                        'first_name'     => $data['first_name'] ?? $firstName,
                        'last_name'      => $data['last_name'] ?? $lastName,
                        'email'          => $data['email'] ?? null,
                        'phone'          => null,
                        'job_title'      => $data['position'] ?? $prospect['job_title'] ?? '',
                        'company'        => $data['company'] ?? $prospect['company'] ?? '',
                        'company_domain' => $domain,
                        'linkedin_url'   => $data['linkedin'] ?? '',
                        'quality_score'  => $data['score'] ?? 50,
                    ];
                    $creditsUsed++;
                }
            } catch (\Throwable $e) {
                Log::warning('Hunter reveal failed', ['error' => $e->getMessage()]);
            }
        }

        return ['success' => true, 'contacts' => $contacts, 'credits_used' => $creditsUsed];
    }

    public function verifyEmail(string $email): array
    {
        try {
            $response = Http::timeout(15)->get("{$this->baseUrl}/email-verifier", [
                'api_key' => $this->apiKey(),
                'email'   => $email,
            ]);

            if ($response->successful()) {
                $data = $response->json('data') ?? [];
                return [
                    'success' => true,
                    'result'  => $data['result'] ?? 'unknown',
                    'score'   => $data['score'] ?? null,
                ];
            }

            return ['success' => false, 'result' => 'error', 'score' => null];
        } catch (\Throwable $e) {
            return ['success' => false, 'result' => 'error', 'score' => null];
        }
    }

    public function getQuota(): array
    {
        try {
            $response = Http::timeout(10)->get("{$this->baseUrl}/account", [
                'api_key' => $this->apiKey(),
            ]);

            if ($response->successful()) {
                $data = $response->json('data') ?? [];
                $searches = $data['requests']['searches'] ?? [];
                $remaining = ($searches['available'] ?? 0) - ($searches['used'] ?? 0);
                return ['success' => true, 'credits_remaining' => max(0, $remaining), 'plan' => $data['plan_name'] ?? null];
            }

            return ['success' => false, 'credits_remaining' => null, 'plan' => null];
        } catch (\Throwable $e) {
            return ['success' => false, 'credits_remaining' => null, 'plan' => null];
        }
    }

    protected function apiKey(): string
    {
        return $this->provider->getCredential('api_key') ?? '';
    }
}
