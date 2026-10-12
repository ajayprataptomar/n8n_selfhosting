<?php

namespace App\Services;

/**
 * v4.8 — User-agent bot classifier for email open/click tracking.
 *
 * Every ESP has this problem: Gmail, Yahoo, Outlook, iCloud, and most
 * corporate mail security appliances (Mimecast, Proofpoint, Barracuda,
 * Google Advanced Protection, Microsoft SafeLinks) pre-fetch every
 * <img> and click every link on incoming mail to scan for malicious
 * content. Every one of those hits inflates open/click rates.
 *
 * We record ALL hits into `email_events` for auditability, but only
 * hits that DIDN'T come from a bot advance CampaignDispatch.status.
 * That gives us:
 *   - `raw_open_count`  = every pixel fetch (bots included)
 *   - `real_open_count` = human-actionable opens only
 *
 * The list below is deliberately conservative — false-negatives
 * (missing a bot) inflate opens slightly; false-positives (marking
 * a real user as bot) silently under-count and are much worse.
 * Extend via config/setting.php → 'email.extra_bot_user_agents'.
 */
class EmailBotDetector
{
    /**
     * Well-known bot user-agent substrings, case-insensitive.
     * Ordered roughly by frequency (Gmail prefetch is #1 by far).
     */
    protected const BUILT_IN_PATTERNS = [
        // Gmail image prefetch (this is the #1 source of fake opens).
        'GoogleImageProxy'            => 'gmail-image-proxy',
        // Yahoo Mail image scanner.
        'YahooMailProxy'              => 'yahoo-mail-proxy',
        // Microsoft SafeLinks click scanner.
        'Microsoft Office'            => 'ms-office',
        'Outlook-iOS'                 => 'outlook-mobile',
        'BingPreview'                 => 'bing-preview',
        // Corporate mail security scanners.
        'Mimecast'                    => 'mimecast',
        'Proofpoint'                  => 'proofpoint',
        'Barracuda'                   => 'barracuda',
        'Symantec'                    => 'symantec',
        'FireEye'                     => 'fireeye',
        'BitDefender'                 => 'bitdefender',
        'Sophos'                      => 'sophos',
        'TrendMicro'                  => 'trend-micro',
        'Fortinet'                    => 'fortinet',
        // Generic scanner/crawler markers.
        'HeadlessChrome'              => 'headless-chrome',
        'PhantomJS'                   => 'phantomjs',
        'python-requests'             => 'python-requests',
        'curl/'                       => 'curl',
        'wget/'                       => 'wget',
        'Go-http-client'              => 'go-http-client',
        'okhttp/'                     => 'okhttp',
        // Well-known crawlers that shouldn't be hitting a private tracking pixel.
        'Googlebot'                   => 'googlebot',
        'bingbot'                     => 'bingbot',
        'Slackbot'                    => 'slackbot',
        'facebookexternalhit'         => 'facebook-preview',
        'Twitterbot'                  => 'twitter-preview',
    ];

    /**
     * Classify a user-agent.
     *
     * @return array{is_bot: bool, bot_name: string|null}
     */
    public function classify(?string $userAgent): array
    {
        if ($userAgent === null || $userAgent === '') {
            // Empty UA = almost always a scanner. Treat as bot (safer default).
            return ['is_bot' => true, 'bot_name' => 'empty-ua'];
        }

        $patterns = array_merge(
            self::BUILT_IN_PATTERNS,
            $this->extraPatterns()
        );

        foreach ($patterns as $needle => $name) {
            if (stripos($userAgent, $needle) !== false) {
                return ['is_bot' => true, 'bot_name' => $name];
            }
        }

        return ['is_bot' => false, 'bot_name' => null];
    }

    /**
     * Extra patterns configured by the admin via
     * config/setting.php → 'email.extra_bot_user_agents' => ['needle' => 'name'].
     */
    protected function extraPatterns(): array
    {
        $extra = config('setting.email.extra_bot_user_agents', []);
        return is_array($extra) ? $extra : [];
    }
}
