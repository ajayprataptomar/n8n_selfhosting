<?php

namespace App\Services\Content;

use App\Models\ContentGenerationHistory;
use App\Models\ContentResource;

interface ContentEngineInterface
{
    /**
     * Generate personalized email content based on business resource data
     * without calling external AI APIs.
     *
     * @param ContentResource $resource
     * @param array $options Optional overrides: 'gateway_id', 'intent', 'recipient_name', 'dispatch_ref'
     * @return array Contains: subject, body, template_id, template_version, content_fingerprint, variables_used, is_repeated, reuse_count
     */
    public function generateContent(ContentResource $resource, array $options = []): array;

    /**
     * Compute a normalized SHA-256 fingerprint for email content to detect duplicates
     *
     * @param string $subject
     * @param string $body
     * @return string
     */
    public function computeFingerprint(string $subject, string $body): string;

    /**
     * Record content usage into history, tracking fingerprint frequency and dispatch references
     *
     * @param int $userId
     * @param int|null $resourceId
     * @param int|null $gatewayId
     * @param array $contentPayload
     * @param string|null $dispatchReference
     * @return ContentGenerationHistory
     */
    public function recordUsage(
        int $userId,
        ?int $resourceId,
        ?int $gatewayId,
        array $contentPayload,
        ?string $dispatchReference = null
    ): ContentGenerationHistory;
}
