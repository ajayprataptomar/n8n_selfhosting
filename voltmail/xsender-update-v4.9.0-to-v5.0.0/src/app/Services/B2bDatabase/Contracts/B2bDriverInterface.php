<?php

namespace App\Services\B2bDatabase\Contracts;

use App\Models\B2bProvider;

interface B2bDriverInterface
{
    /**
     * Test the provider connection / credentials
     *
     * @return array{success: bool, message: string, credits_remaining: int|null}
     */
    public function testConnection(): array;

    /**
     * Search for contacts/prospects
     *
     * @param array $filters Search filters (job_title, company, location, etc.)
     * @param int $page Page number
     * @param int $perPage Results per page
     * @return array{success: bool, total: int, results: array, credits_used: int, page: int, per_page: int}
     */
    public function search(array $filters, int $page = 1, int $perPage = 25): array;

    /**
     * Reveal/enrich contact details (email, phone)
     * For providers with 2-step flow (Apollo, RocketReach)
     *
     * @param array $prospects Array of prospect IDs or data to enrich
     * @return array{success: bool, contacts: array, credits_used: int}
     */
    public function reveal(array $prospects): array;

    /**
     * Verify an email address
     *
     * @param string $email
     * @return array{success: bool, result: string, score: int|null}
     */
    public function verifyEmail(string $email): array;

    /**
     * Get remaining API credits/quota
     *
     * @return array{success: bool, credits_remaining: int|null, plan: string|null}
     */
    public function getQuota(): array;

    /**
     * Set the provider model (credentials source)
     */
    public function setProvider(B2bProvider $provider): static;
}
