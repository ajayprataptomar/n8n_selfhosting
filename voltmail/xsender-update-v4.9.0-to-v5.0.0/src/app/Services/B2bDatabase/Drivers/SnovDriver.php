<?php

namespace App\Services\B2bDatabase\Drivers;

use App\Models\B2bProvider;
use App\Services\B2bDatabase\Contracts\B2bDriverInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;

/**
 * Snov.io API Driver
 *
 * Auth: OAuth 2.0 (client_id + client_secret → bearer token, 1hr expiry)
 * Domain Search: POST /v2/domain-search/start + GET /v2/domain-search/result (async)
 * Email Finder: POST /v1/get-emails-from-names (1 credit/prospect)
 * Rate limit: 60 req/min
 */
class SnovDriver implements B2bDriverInterface
{
    protected B2bProvider $provider;
    protected string $baseUrl = 'https://api.snov.io';

    public function setProvider(B2bProvider $provider): static
    {
        $this->provider = $provider;
        return $this;
    }

    public function testConnection(): array
    {
        try {
            $token = $this->getAccessToken();
            if (!$token) {
                return ['success' => false, 'message' => 'Failed to authenticate with Snov.io — check client_id and client_secret', 'credits_remaining' => null];
            }

            $quota = $this->getQuota();
            return [
                'success' => true,
                'message' => 'Connected to Snov.io',
                'credits_remaining' => $quota['credits_remaining'],
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'credits_remaining' => null];
        }
    }

    public function search(array $filters, int $page = 1, int $perPage = 25): array
    {
        try {
            $token = $this->getAccessToken();
            if (!$token) {
                return ['success' => false, 'total' => 0, 'results' => [], 'credits_used' => 0, 'page' => $page, 'per_page' => $perPage, 'error' => 'Authentication failed'];
            }

            $domain = Arr::get($filters, 'domain');
            if (!$domain) {
                return ['success' => false, 'total' => 0, 'results' => [], 'credits_used' => 0, 'page' => $page, 'per_page' => $perPage, 'error' => 'Snov.io requires a domain for search'];
            }

            // Start domain search
            $response = Http::withToken($token)
                ->timeout(30)
                ->post("{$this->baseUrl}/v2/domain-emails-with-info", [
                    'domain'   => $domain,
                    'type'     => Arr::get($filters, 'type', 'all'),
                    'limit'    => min($perPage, 100),
                    'lastId'   => $page > 1 ? (($page - 1) * $perPage) : 0,
                ]);

            if (!$response->successful()) {
                return ['success' => false, 'total' => 0, 'results' => [], 'credits_used' => 0, 'page' => $page, 'per_page' => $perPage, 'error' => $response->body()];
            }

            $data = $response->json();
            $emails = $data['emails'] ?? [];

            $results = array_map(function ($item) use ($domain, $data) {
                return [
                    'provider_id'    => $item['id'] ?? null,
                    'first_name'     => $item['firstName'] ?? '',
                    'last_name'      => $item['lastName'] ?? '',
                    'email'          => $item['email'] ?? null,
                    'phone'          => null,
                    'job_title'      => $item['position'] ?? '',
                    'company'        => $data['companyName'] ?? '',
                    'company_domain' => $domain,
                    'linkedin_url'   => $item['sourcePage'] ?? '',
                    'location'       => '',
                    'seniority'      => '',
                    'department'     => $item['type'] ?? '',
                    'is_revealed'    => true, // Snov returns emails directly
                ];
            }, $emails);

            return [
                'success'      => true,
                'total'        => $data['result'] ?? count($results),
                'results'      => $results,
                'credits_used' => 1,
                'page'         => $page,
                'per_page'     => $perPage,
            ];
        } catch (\Throwable $e) {
            Log::error('Snov search failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'total' => 0, 'results' => [], 'credits_used' => 0, 'page' => $page, 'per_page' => $perPage, 'error' => $e->getMessage()];
        }
    }

    public function reveal(array $prospects): array
    {
        $contacts = [];
        $creditsUsed = 0;
        $token = $this->getAccessToken();

        if (!$token) {
            return ['success' => false, 'contacts' => [], 'credits_used' => 0];
        }

        foreach ($prospects as $prospect) {
            $firstName = $prospect['first_name'] ?? '';
            $lastName = $prospect['last_name'] ?? '';
            $domain = $prospect['company_domain'] ?? '';

            if (!$domain || (!$firstName && !$lastName)) {
                continue;
            }

            try {
                $response = Http::withToken($token)
                    ->timeout(15)
                    ->post("{$this->baseUrl}/v1/get-emails-from-names", [
                        'firstName' => $firstName,
                        'lastName'  => $lastName,
                        'domain'    => $domain,
                    ]);

                if ($response->successful()) {
                    $data = $response->json('data') ?? $response->json();
                    $emails = $data['emails'] ?? [];
                    $bestEmail = $emails[0]['email'] ?? null;

                    $contacts[] = [
                        'first_name'     => $firstName,
                        'last_name'      => $lastName,
                        'email'          => $bestEmail,
                        'phone'          => null,
                        'job_title'      => $prospect['job_title'] ?? '',
                        'company'        => $prospect['company'] ?? '',
                        'company_domain' => $domain,
                        'linkedin_url'   => '',
                        'quality_score'  => $bestEmail ? 70 : 20,
                    ];
                    $creditsUsed++;
                }
            } catch (\Throwable $e) {
                Log::warning('Snov reveal failed', ['error' => $e->getMessage()]);
            }
        }

        return ['success' => true, 'contacts' => $contacts, 'credits_used' => $creditsUsed];
    }

    public function verifyEmail(string $email): array
    {
        try {
            $token = $this->getAccessToken();
            if (!$token) {
                return ['success' => false, 'result' => 'error', 'score' => null];
            }

            $response = Http::withToken($token)
                ->timeout(15)
                ->post("{$this->baseUrl}/v1/get-emails-verification-status", [
                    'emails' => [$email],
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $result = $data[0] ?? [];
                return [
                    'success' => true,
                    'result'  => $result['status'] ?? 'unknown',
                    'score'   => null,
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
            $token = $this->getAccessToken();
            if (!$token) {
                return ['success' => false, 'credits_remaining' => null, 'plan' => null];
            }

            $response = Http::withToken($token)->timeout(10)
                ->get("{$this->baseUrl}/v1/get-balance");

            if ($response->successful()) {
                $data = $response->json('data') ?? $response->json();
                return [
                    'success' => true,
                    'credits_remaining' => $data['balance'] ?? $data['credits'] ?? null,
                    'plan' => null,
                ];
            }

            return ['success' => false, 'credits_remaining' => null, 'plan' => null];
        } catch (\Throwable $e) {
            return ['success' => false, 'credits_remaining' => null, 'plan' => null];
        }
    }

    /**
     * Get OAuth access token (cached for 55 minutes)
     */
    protected function getAccessToken(): ?string
    {
        $cacheKey = "snov_token_{$this->provider->id}";

        return Cache::remember($cacheKey, 3300, function () { // 55 min (token lasts 1hr)
            try {
                $response = Http::post("{$this->baseUrl}/v1/oauth/access_token", [
                    'grant_type'    => 'client_credentials',
                    'client_id'     => $this->provider->getCredential('client_id'),
                    'client_secret' => $this->provider->getCredential('client_secret'),
                ]);

                if ($response->successful()) {
                    return $response->json('access_token');
                }

                return null;
            } catch (\Throwable $e) {
                Log::error('Snov OAuth failed', ['error' => $e->getMessage()]);
                return null;
            }
        });
    }
}
