<?php

namespace App\Services\CampaignIntelligence;

use App\Models\CampaignMessage;
use App\Models\ContentAnalysis;

class ContentOptimizerService
{
    /**
     * Common spam trigger words for email
     */
    protected array $spamTriggerWords = [
        'free', 'winner', 'congratulations', 'urgent', 'act now', 'limited time',
        'click here', 'buy now', 'order now', 'special offer', 'exclusive deal',
        'money back', 'no obligation', 'risk free', 'guarantee', 'promise',
        'earn money', 'make money', 'extra income', 'double your', 'increase',
        'casino', 'lottery', 'prize', 'million', 'billion', 'cash',
        'credit card', 'no credit check', 'refinance', 'mortgage',
        'viagra', 'pharmacy', 'medication', 'prescription',
        '100%', '50% off', 'best price', 'lowest price', 'discount',
        'subscribe', 'unsubscribe', 'remove', 'opt out',
        'dear friend', 'dear sir', 'dear madam',
        'as seen on', 'click below', 'click above',
        'spam', 'this is not spam', 'not junk',
        '$$$', '!!!', '???', 'all caps',
    ];

    /**
     * SMS-specific issues
     */
    protected array $smsIssues = [
        'too_long' => 'Message exceeds optimal SMS length (160 characters)',
        'unicode' => 'Contains unicode characters which may increase segment count',
        'multiple_segments' => 'Message will be split into multiple segments',
        'caps_lock' => 'Contains excessive capital letters',
        'special_chars' => 'Contains special characters that may not display correctly',
    ];

    /**
     * Analyze campaign message content
     */
    public function analyzeContent(CampaignMessage $message): ContentAnalysis
    {
        $content = $message->content ?? '';
        $subject = $message->subject ?? '';
        $channel = $message->channel->value ?? 'sms';

        $analysis = [
            'spam_score' => $this->calculateSpamScore($content, $subject, $channel),
            'deliverability_score' => 0,
            'subject_score' => null,
            'readability_score' => $this->calculateReadabilityScore($content),
            'spam_triggers' => $this->detectSpamTriggers($content, $subject),
            'improvements' => [],
            'ai_analysis' => [],
            'character_count' => strlen($content),
            'word_count' => str_word_count(strip_tags($content)),
            'has_personalization' => $this->hasPersonalization($content),
            'has_call_to_action' => $this->hasCallToAction($content),
            'has_unsubscribe_link' => $this->hasUnsubscribeLink($content),
        ];

        // Channel-specific analysis
        if ($channel === 'email' && $subject) {
            $analysis['subject_score'] = $this->analyzeSubjectLine($subject);
        }

        if ($channel === 'sms') {
            $analysis = array_merge($analysis, $this->analyzeSmsContent($content));
        }

        if ($channel === 'whatsapp') {
            $analysis = array_merge($analysis, $this->analyzeWhatsAppContent($content));
        }

        // Calculate deliverability score
        $analysis['deliverability_score'] = $this->calculateDeliverabilityScore($analysis);

        // Generate improvements
        $analysis['improvements'] = $this->generateImprovements($analysis, $channel);

        // Generate AI analysis summary
        $analysis['ai_analysis'] = $this->generateAiAnalysis($analysis, $channel);

        // Save or update analysis
        return ContentAnalysis::updateOrCreate(
            ['campaign_message_id' => $message->id],
            $analysis
        );
    }

    /**
     * Calculate spam score (0-100, lower is better)
     */
    protected function calculateSpamScore(string $content, string $subject, string $channel): float
    {
        $score = 0;
        $contentLower = strtolower($content . ' ' . $subject);

        // Check for spam trigger words
        foreach ($this->spamTriggerWords as $word) {
            if (str_contains($contentLower, strtolower($word))) {
                $score += 5;
            }
        }

        // Check for excessive caps
        $capsRatio = $this->getCapsRatio($content);
        if ($capsRatio > 0.3) {
            $score += 15;
        } elseif ($capsRatio > 0.2) {
            $score += 8;
        }

        // Check for excessive punctuation
        $punctuationRatio = $this->getPunctuationRatio($content);
        if ($punctuationRatio > 0.1) {
            $score += 10;
        }

        // Check for suspicious patterns
        if (preg_match('/\$\d+/', $content)) {
            $score += 5;
        }
        if (preg_match('/\d+%\s*off/i', $content)) {
            $score += 5;
        }
        if (substr_count($content, '!') > 3) {
            $score += 5;
        }
        if (substr_count($content, '?') > 3) {
            $score += 3;
        }

        // For email, check subject line
        if ($channel === 'email' && $subject) {
            if (strtoupper($subject) === $subject && strlen($subject) > 10) {
                $score += 15; // All caps subject
            }
            if (str_contains($subject, 'RE:') || str_contains($subject, 'FW:')) {
                $score += 5; // Fake reply/forward
            }
        }

        return min(100, $score);
    }

    /**
     * Calculate readability score
     */
    protected function calculateReadabilityScore(string $content): float
    {
        $text = strip_tags($content);
        $wordCount = str_word_count($text);
        $sentenceCount = max(1, preg_match_all('/[.!?]+/', $text));
        $syllableCount = $this->countSyllables($text);

        if ($wordCount === 0) {
            return 50;
        }

        // Flesch Reading Ease formula (simplified)
        $avgWordsPerSentence = $wordCount / $sentenceCount;
        $avgSyllablesPerWord = $syllableCount / $wordCount;

        $score = 206.835 - (1.015 * $avgWordsPerSentence) - (84.6 * $avgSyllablesPerWord);

        // Normalize to 0-100
        return max(0, min(100, round($score)));
    }

    /**
     * Count syllables in text (simple approximation)
     */
    protected function countSyllables(string $text): int
    {
        $text = strtolower(strip_tags($text));
        $words = str_word_count($text, 1);
        $count = 0;

        foreach ($words as $word) {
            $count += max(1, preg_match_all('/[aeiouy]+/', $word));
        }

        return $count;
    }

    /**
     * Get capital letters ratio
     */
    protected function getCapsRatio(string $text): float
    {
        $text = preg_replace('/[^a-zA-Z]/', '', $text);
        if (strlen($text) === 0) {
            return 0;
        }

        $caps = preg_match_all('/[A-Z]/', $text);
        return $caps / strlen($text);
    }

    /**
     * Get punctuation ratio
     */
    protected function getPunctuationRatio(string $text): float
    {
        if (strlen($text) === 0) {
            return 0;
        }

        $punctuation = preg_match_all('/[!?.,;:\'\"]+/', $text);
        return $punctuation / strlen($text);
    }

    /**
     * Detect spam triggers in content
     */
    protected function detectSpamTriggers(string $content, string $subject): array
    {
        $triggers = [];
        $combined = strtolower($content . ' ' . $subject);

        foreach ($this->spamTriggerWords as $word) {
            if (str_contains($combined, strtolower($word))) {
                $triggers[] = [
                    'word' => $word,
                    'severity' => $this->getWordSeverity($word),
                ];
            }
        }

        return $triggers;
    }

    /**
     * Get severity level for spam word
     */
    protected function getWordSeverity(string $word): string
    {
        $highSeverity = ['free', 'winner', 'casino', 'lottery', 'viagra', 'pharmacy'];
        $mediumSeverity = ['urgent', 'act now', 'limited time', 'click here', 'buy now'];

        if (in_array(strtolower($word), $highSeverity)) {
            return 'high';
        }
        if (in_array(strtolower($word), $mediumSeverity)) {
            return 'medium';
        }
        return 'low';
    }

    /**
     * Check if content has personalization
     */
    protected function hasPersonalization(string $content): bool
    {
        $patterns = ['{{first_name}}', '{{last_name}}', '{{full_name}}', '{{email}}', '{{phone}}', '@{{'];
        foreach ($patterns as $pattern) {
            if (str_contains($content, $pattern)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if content has call to action
     */
    protected function hasCallToAction(string $content): bool
    {
        $ctaPatterns = [
            'click', 'tap', 'visit', 'shop', 'buy', 'order', 'subscribe',
            'sign up', 'register', 'download', 'get started', 'learn more',
            'contact us', 'call', 'reply', 'respond',
        ];

        $contentLower = strtolower($content);
        foreach ($ctaPatterns as $pattern) {
            if (str_contains($contentLower, $pattern)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if content has unsubscribe link
     */
    protected function hasUnsubscribeLink(string $content): bool
    {
        $patterns = ['unsubscribe', 'opt out', 'opt-out', 'stop receiving', 'manage preferences'];
        $contentLower = strtolower($content);

        foreach ($patterns as $pattern) {
            if (str_contains($contentLower, $pattern)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Analyze email subject line
     */
    protected function analyzeSubjectLine(string $subject): float
    {
        $score = 70; // Base score

        $length = strlen($subject);

        // Length analysis
        if ($length >= 30 && $length <= 50) {
            $score += 10; // Optimal length
        } elseif ($length < 20) {
            $score -= 10; // Too short
        } elseif ($length > 60) {
            $score -= 15; // Too long (may get cut off)
        }

        // Personalization bonus
        if ($this->hasPersonalization($subject)) {
            $score += 10;
        }

        // Emoji bonus (moderate use)
        $emojiCount = preg_match_all('/[\x{1F300}-\x{1F9FF}]/u', $subject);
        if ($emojiCount === 1) {
            $score += 5;
        } elseif ($emojiCount > 2) {
            $score -= 5;
        }

        // All caps penalty
        if (strtoupper($subject) === $subject && $length > 10) {
            $score -= 20;
        }

        // Spam word penalty
        foreach ($this->spamTriggerWords as $word) {
            if (str_contains(strtolower($subject), strtolower($word))) {
                $score -= 5;
            }
        }

        return max(0, min(100, $score));
    }

    /**
     * Analyze SMS-specific content
     */
    protected function analyzeSmsContent(string $content): array
    {
        $issues = [];
        $length = strlen($content);

        // Check length
        if ($length > 160) {
            $issues[] = $this->smsIssues['too_long'];
        }

        // Check for unicode
        if (preg_match('/[^\x00-\x7F]/', $content)) {
            $issues[] = $this->smsIssues['unicode'];
        }

        // Calculate segments
        $isUnicode = preg_match('/[^\x00-\x7F]/', $content);
        if ($isUnicode) {
            $segments = $length <= 70 ? 1 : ceil($length / 67);
        } else {
            $segments = $length <= 160 ? 1 : ceil($length / 153);
        }

        if ($segments > 1) {
            $issues[] = str_replace('multiple', $segments, $this->smsIssues['multiple_segments']);
        }

        // Check for excessive caps
        if ($this->getCapsRatio($content) > 0.3) {
            $issues[] = $this->smsIssues['caps_lock'];
        }

        return [
            'sms_length' => $length,
            'sms_segments' => $segments,
            'sms_is_unicode' => $isUnicode,
            'sms_issues' => $issues,
        ];
    }

    /**
     * Analyze WhatsApp-specific content
     */
    protected function analyzeWhatsAppContent(string $content): array
    {
        $issues = [];
        $length = strlen($content);

        // WhatsApp has a 65536 character limit
        if ($length > 4096) {
            $issues[] = 'Message is very long. Consider breaking into multiple messages.';
        }

        // Check for formatting
        $hasFormatting = preg_match('/\*[^*]+\*|_[^_]+_|~[^~]+~|```/', $content);

        return [
            'whatsapp_length' => $length,
            'whatsapp_has_formatting' => (bool)$hasFormatting,
            'whatsapp_issues' => $issues,
        ];
    }

    /**
     * Calculate overall deliverability score
     */
    protected function calculateDeliverabilityScore(array $analysis): float
    {
        $score = 100;

        // Spam score impact
        $score -= $analysis['spam_score'] * 0.5;

        // Readability impact
        if ($analysis['readability_score'] < 30) {
            $score -= 10;
        } elseif ($analysis['readability_score'] > 70) {
            $score += 5;
        }

        // Subject line impact (for email)
        if (isset($analysis['subject_score'])) {
            if ($analysis['subject_score'] < 50) {
                $score -= 15;
            } elseif ($analysis['subject_score'] > 80) {
                $score += 5;
            }
        }

        // Personalization bonus
        if ($analysis['has_personalization']) {
            $score += 5;
        }

        // Call to action bonus
        if ($analysis['has_call_to_action']) {
            $score += 3;
        }

        // Unsubscribe link bonus (for email)
        if ($analysis['has_unsubscribe_link']) {
            $score += 5;
        }

        return max(0, min(100, round($score)));
    }

    /**
     * Generate improvement suggestions
     */
    protected function generateImprovements(array $analysis, string $channel): array
    {
        $improvements = [];

        // Spam score improvements
        if ($analysis['spam_score'] > 30) {
            $improvements[] = [
                'type' => 'warning',
                'title' => translate('High Spam Score'),
                'message' => translate('Your content may trigger spam filters. Consider removing promotional words.'),
            ];
        }

        // Personalization improvement
        if (!$analysis['has_personalization']) {
            $improvements[] = [
                'type' => 'suggestion',
                'title' => translate('Add Personalization'),
                'message' => translate('Adding personalization like @{{first_name}} can improve engagement rates.'),
            ];
        }

        // Call to action improvement
        if (!$analysis['has_call_to_action']) {
            $improvements[] = [
                'type' => 'suggestion',
                'title' => translate('Add Call to Action'),
                'message' => translate('Include a clear call to action to improve click-through rates.'),
            ];
        }

        // Email-specific improvements
        if ($channel === 'email') {
            if (!$analysis['has_unsubscribe_link']) {
                $improvements[] = [
                    'type' => 'warning',
                    'title' => translate('Missing Unsubscribe Link'),
                    'message' => translate('Include an unsubscribe option to comply with email regulations.'),
                ];
            }

            if (isset($analysis['subject_score']) && $analysis['subject_score'] < 50) {
                $improvements[] = [
                    'type' => 'suggestion',
                    'title' => translate('Improve Subject Line'),
                    'message' => translate('Keep subject lines 30-50 characters and avoid spam words.'),
                ];
            }
        }

        // SMS-specific improvements
        if ($channel === 'sms' && isset($analysis['sms_segments']) && $analysis['sms_segments'] > 2) {
            $improvements[] = [
                'type' => 'warning',
                'title' => translate('Long SMS Message'),
                'message' => translate('Your message will be sent as') . ' ' . $analysis['sms_segments'] . ' ' . translate('segments, increasing cost.'),
            ];
        }

        // Readability improvement
        if ($analysis['readability_score'] < 50) {
            $improvements[] = [
                'type' => 'suggestion',
                'title' => translate('Improve Readability'),
                'message' => translate('Use shorter sentences and simpler words for better engagement.'),
            ];
        }

        return $improvements;
    }

    /**
     * Generate AI analysis summary
     */
    protected function generateAiAnalysis(array $analysis, string $channel): array
    {
        $summary = [
            'overall_rating' => $this->getOverallRating($analysis['deliverability_score']),
            'channel' => $channel,
            'key_metrics' => [
                'deliverability' => $analysis['deliverability_score'],
                'spam_risk' => $analysis['spam_score'],
                'readability' => $analysis['readability_score'],
            ],
            'strengths' => [],
            'weaknesses' => [],
        ];

        // Identify strengths
        if ($analysis['has_personalization']) {
            $summary['strengths'][] = translate('Uses personalization');
        }
        if ($analysis['has_call_to_action']) {
            $summary['strengths'][] = translate('Has clear call to action');
        }
        if ($analysis['spam_score'] < 20) {
            $summary['strengths'][] = translate('Low spam risk');
        }
        if ($analysis['readability_score'] > 60) {
            $summary['strengths'][] = translate('Good readability');
        }

        // Identify weaknesses
        if ($analysis['spam_score'] > 40) {
            $summary['weaknesses'][] = translate('High spam risk');
        }
        if (!$analysis['has_personalization']) {
            $summary['weaknesses'][] = translate('No personalization');
        }
        if ($analysis['readability_score'] < 40) {
            $summary['weaknesses'][] = translate('Poor readability');
        }

        return $summary;
    }

    /**
     * Get overall rating based on deliverability score
     */
    protected function getOverallRating(float $score): string
    {
        if ($score >= 80) return 'excellent';
        if ($score >= 60) return 'good';
        if ($score >= 40) return 'fair';
        return 'poor';
    }

    /**
     * Quick analyze content without saving
     */
    public function quickAnalyze(string $content, string $channel = 'email', string $subject = ''): array
    {
        return [
            'spam_score' => $this->calculateSpamScore($content, $subject, $channel),
            'readability_score' => $this->calculateReadabilityScore($content),
            'spam_triggers' => $this->detectSpamTriggers($content, $subject),
            'has_personalization' => $this->hasPersonalization($content),
            'has_call_to_action' => $this->hasCallToAction($content),
            'character_count' => strlen($content),
            'word_count' => str_word_count(strip_tags($content)),
        ];
    }
}
