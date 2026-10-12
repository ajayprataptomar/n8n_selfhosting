<?php

namespace App\Services\Content;

use App\Models\ContentGenerationHistory;
use App\Models\ContentResource;
use Illuminate\Support\Str;

class DeterministicContentEngine implements ContentEngineInterface
{
    /**
     * Standard built-in templates with diverse conversational and business structures
     */
    protected array $standardTemplates = [
        [
            'id'      => 'intro_overview_v1',
            'version' => '1.0.0',
            'intent'  => 'intro',
            'subject' => '{Quick question regarding|Touching base regarding|Brief introduction from} {company_name}',
            'body'    => "<p>{Hi|Hello|Good day},</p>\n<p>{I wanted to reach out regarding our work at|Reaching out from} <strong>{company_name}</strong>. {We specialize in|Our focus is} {company_description}.</p>\n<p>{We work extensively with|Specifically supporting} {target_audience} in the {industry} {sector|space}.</p>\n<p>{If you have a moment, I would welcome the opportunity to share brief insights.|Would you be open to a brief chat next week?}</p>\n<p>{Best regards|Kind regards|Sincerely},<br>{company_name} Team</p>",
        ],
        [
            'id'      => 'product_highlight_v1',
            'version' => '1.0.0',
            'intent'  => 'product',
            'subject' => '{Update on|New updates regarding} {product_name} | {company_name}',
            'body'    => "<p>{Hello|Hi there},</p>\n<p>{I hope your week is off to a productive start.|Hope all is well on your end.}</p>\n<p>{At|Here at} <strong>{company_name}</strong>, {we recently enhanced our offering around|we focus on providing} <strong>{product_name}</strong>: {product_benefit}.</p>\n<p>{Our goal is to help|Designed specifically for} {target_audience} {streamline operations and scale efficiently|achieve measurable outcomes}.</p>\n<p>{Feel free to take a look at our site for context:|More details can be explored at:} {website}</p>\n<p>{Warm regards|Best wishes},<br>{company_name}</p>",
        ],
        [
            'id'      => 'faq_solution_v1',
            'version' => '1.0.0',
            'intent'  => 'faq',
            'subject' => '{Addressing common questions on|Key insight regarding} {industry}',
            'body'    => "<p>{Hi|Hello},</p>\n<p>{One of the questions we most frequently hear from|A common challenge faced by} {target_audience} is:</p>\n<blockquote><em>\"{faq_question}\"</em></blockquote>\n<p>{Our perspective at|How we address this at} <strong>{company_name}</strong>: {faq_answer}</p>\n<p>{Happy to discuss how this applies to your current workflow if helpful.|Let me know if this aligns with your current priorities.}</p>\n<p>{Regards|Best},<br>{company_name}</p>",
        ],
        [
            'id'      => 'partnership_checkin_v1',
            'version' => '1.0.0',
            'intent'  => 'partnership',
            'subject' => '{Exploring collaboration with|Potential synergy between our teams and} {company_name}',
            'body'    => "<p>{Hello|Hi},</p>\n<p>{Following up on industry developments in|Observing recent shifts across the} {industry} space.</p>\n<p><strong>{company_name}</strong> {continues to partner with forward-thinking organizations in|is actively engaging with} {business_type}. {Our core mission is to provide|We strive to deliver} {company_description}.</p>\n<p>{Would it make sense to connect briefly for 10 minutes this Thursday or Friday?|Let me know if a quick introductory exchange is worthwhile.}</p>\n<p>{Best regards|Thanks and best regards},<br>{company_name}</p>",
        ],
        [
            'id'      => 'operational_feedback_v1',
            'version' => '1.0.0',
            'intent'  => 'feedback',
            'subject' => '{Quick check-in regarding|Thoughts on} {industry} workflows',
            'body'    => "<p>{Hi there|Hello},</p>\n<p>{I wanted to follow up quickly on our earlier notes regarding|Reaching out to follow up on} {industry} {initiatives|processes}.</p>\n<p>{Our team at|The group here at} <strong>{company_name}</strong> {is committed to supporting|specializes in delivering value for} {target_audience}.</p>\n<p>{Please let me know if there are specific requirements you are currently reviewing.|Any feedback or open points on your end would be greatly appreciated.}</p>\n<p>{Sincerely|Best regards},<br>{company_name}</p>",
        ],
    ];

    /**
     * Generate personalized email content based on business resource data
     */
    public function generateContent(ContentResource $resource, array $options = []): array
    {
        $tenantId = (int) $resource->user_id;

        // 1. Gather all candidate templates (Custom templates in resource + Standard templates)
        $candidates = $this->collectCandidateTemplates($resource, $options['intent'] ?? null);

        // 2. Extract and format variables from the business resource
        $vars = $this->extractVariables($resource, $options);

        // 3. Score candidates deterministically by previous reuse frequency for this tenant
        $scored = [];
        foreach ($candidates as $template) {
            $rawSubject = $this->substituteVariables($template['subject'], $vars);
            $rawBody    = $this->substituteVariables($template['body'], $vars);

            // Apply spintax for surface variety
            $spunSubject = $this->spinText($rawSubject);
            $spunBody    = $this->spinText($rawBody);

            $fingerprint = $this->computeFingerprint($spunSubject, $spunBody);

            // Check template usage history for this tenant
            $templateUseCount = ContentGenerationHistory::where('user_id', $tenantId)
                ->where('template_id', $template['id'])
                ->count();

            // Check exact content fingerprint reuse
            $fingerprintHistory = ContentGenerationHistory::where('user_id', $tenantId)
                ->where('content_fingerprint', $fingerprint)
                ->first();

            $fingerprintReuse = $fingerprintHistory ? (int) $fingerprintHistory->reuse_count : 0;
            $lastUsedAt = $fingerprintHistory ? $fingerprintHistory->last_used_at : null;

            // Composite score: heavily penalize fingerprint collisions, then template overuse
            $score = ($fingerprintReuse * 100) + ($templateUseCount * 10);

            $scored[] = [
                'template'            => $template,
                'subject'             => $spunSubject,
                'body'                => $spunBody,
                'fingerprint'         => $fingerprint,
                'variables'           => $vars,
                'score'               => $score,
                'reuse_count'         => $fingerprintReuse,
                'last_used_at'        => $lastUsedAt ? $lastUsedAt->timestamp : 0,
            ];
        }

        // 4. Deterministic selection: Pick candidate with lowest composite score, then oldest last_used_at
        usort($scored, function ($a, $b) {
            if ($a['score'] === $b['score']) {
                return $a['last_used_at'] <=> $b['last_used_at'];
            }
            return $a['score'] <=> $b['score'];
        });

        $chosen = $scored[0];
        $isRepeated = $chosen['reuse_count'] > 0;

        return [
            'subject'             => $chosen['subject'],
            'body'                => $chosen['body'],
            'template_id'         => $chosen['template']['id'],
            'template_version'    => $chosen['template']['version'] ?? '1.0.0',
            'content_fingerprint' => $chosen['fingerprint'],
            'variables_used'      => $chosen['variables'],
            'is_repeated'         => $isRepeated,
            'reuse_count'         => $chosen['reuse_count'],
        ];
    }

    /**
     * Compute normalized SHA-256 fingerprint for deduplication
     */
    public function computeFingerprint(string $subject, string $body): string
    {
        $normalized = strip_tags($subject . ' ' . $body);
        $normalized = mb_strtolower($normalized, 'UTF-8');
        // Collapse punctuation and whitespace into single spaces
        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $normalized);
        $normalized = trim($normalized);

        return hash('sha256', $normalized);
    }

    /**
     * Record usage in history and increment reuse count
     */
    public function recordUsage(
        int $userId,
        ?int $resourceId,
        ?int $gatewayId,
        array $contentPayload,
        ?string $dispatchReference = null
    ): ContentGenerationHistory {
        $fingerprint = $contentPayload['content_fingerprint'];

        $history = ContentGenerationHistory::where('user_id', $userId)
            ->where('content_fingerprint', $fingerprint)
            ->first();

        if ($history) {
            $history->reuse_count += 1;
            $history->last_used_at = now();
            if ($dispatchReference) {
                $history->dispatch_reference = $dispatchReference;
            }
            $history->save();
            return $history;
        }

        return ContentGenerationHistory::create([
            'uid'                 => str_unique(30),
            'user_id'             => $userId,
            'content_resource_id' => $resourceId,
            'gateway_id'          => $gatewayId,
            'template_id'         => $contentPayload['template_id'] ?? 'custom',
            'template_version'    => $contentPayload['template_version'] ?? '1.0.0',
            'subject'             => $contentPayload['subject'],
            'body'                => $contentPayload['body'],
            'content_fingerprint' => $fingerprint,
            'variables_used'      => $contentPayload['variables_used'] ?? [],
            'reuse_count'         => 1,
            'last_used_at'        => now(),
            'dispatch_reference'  => $dispatchReference,
        ]);
    }

    /**
     * Collect templates combining custom resource templates and standard fallbacks
     */
    protected function collectCandidateTemplates(ContentResource $resource, ?string $intent = null): array
    {
        $customTemplates = [];
        if (!empty($resource->templates) && is_array($resource->templates)) {
            foreach ($resource->templates as $i => $tpl) {
                if (!empty($tpl['subject']) && !empty($tpl['body'])) {
                    $customTemplates[] = [
                        'id'      => $tpl['id'] ?? ('custom_tpl_' . ($i + 1)),
                        'version' => $tpl['version'] ?? '1.0.0',
                        'intent'  => $tpl['intent'] ?? 'custom',
                        'subject' => $tpl['subject'],
                        'body'    => $tpl['body'],
                    ];
                }
            }
        }

        $all = array_merge($customTemplates, $this->standardTemplates);

        if ($intent) {
            $filtered = array_values(array_filter($all, fn($t) => ($t['intent'] ?? '') === $intent));
            if (!empty($filtered)) {
                return $filtered;
            }
        }

        return $all;
    }

    /**
     * Extract business variables from ContentResource
     */
    protected function extractVariables(ContentResource $resource, array $options): array
    {
        $products = is_array($resource->products_services) ? $resource->products_services : [];
        $firstProduct = !empty($products) ? $products[0] : [];

        $faqs = is_array($resource->faqs) ? $resource->faqs : [];
        $firstFaq = !empty($faqs) ? $faqs[0] : [];

        return [
            '{company_name}'        => $resource->company_name ?: ($resource->name ?: 'Our Company'),
            '{business_type}'       => $resource->business_type ?: 'Business Solutions',
            '{company_description}' => $resource->company_description ?: 'delivering modern high-impact digital services',
            '{website}'             => $resource->website ?: 'https://' . Str::slug($resource->company_name ?: 'company') . '.com',
            '{industry}'            => $resource->industry ?: 'Cloud & Enterprise Technology',
            '{target_audience}'     => $resource->target_audience ?: 'businesses and professionals',
            '{brand_voice}'         => $resource->brand_voice ?: 'professional and helpful',
            '{product_name}'        => $firstProduct['name'] ?? 'Core Platform',
            '{product_benefit}'     => $firstProduct['benefit'] ?? 'streamlined workflow automation and high reliability',
            '{faq_question}'        => $firstFaq['question'] ?? 'How does your solution integrate with existing workflows?',
            '{faq_answer}'          => $firstFaq['answer'] ?? 'Seamlessly integrates with minimal configuration and instant sync.',
        ];
    }

    /**
     * Replace template placeholders with real business variables
     */
    protected function substituteVariables(string $text, array $variables): string
    {
        return str_replace(array_keys($variables), array_values($variables), $text);
    }

    /**
     * Resolve spintax variations using textSpinner helper if available, or regex fallback
     */
    protected function spinText(string $text): string
    {
        if (function_exists('textSpinner')) {
            $res = textSpinner($text);
            return is_array($res) ? $res[0] : (string) $res;
        }

        // Native regex spintax parser {a|b|c}
        while (preg_match('/\{([^{}]+)\}/', $text, $matches)) {
            $parts = explode('|', $matches[1]);
            $replacement = $parts[array_rand($parts)];
            $text = str_replace($matches[0], $replacement, $text);
        }

        return $text;
    }
}
