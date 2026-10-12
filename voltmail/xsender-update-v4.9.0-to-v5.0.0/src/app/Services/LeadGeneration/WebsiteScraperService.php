<?php

namespace App\Services\LeadGeneration;

use App\Models\LeadScrapingJob;
use App\Models\ScrapedLead;
use App\Models\LeadGenerationSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WebsiteScraperService
{
    protected int $timeout = 15;
    protected int $maxPages = 20;
    protected array $visitedUrls = [];

    // User agents to rotate (realistic browser agents)
    protected array $userAgents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    ];

    // Enhanced regex patterns for extraction
    protected array $patterns = [
        // More comprehensive email pattern
        'email' => '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,10}/i',
        // Stricter phone patterns - requires proper formatting hints
        'phone' => '/(?:\+\d{1,4}[\s\-.]?)?\(?\d{2,4}\)?[\s\-.]?\d{3,4}[\s\-.]?\d{3,4}/',
        'facebook' => '/(?:https?:\/\/)?(?:www\.|m\.)?facebook\.com\/(?:pages\/)?[a-zA-Z0-9._\-\/]+/i',
        'instagram' => '/(?:https?:\/\/)?(?:www\.)?instagram\.com\/[a-zA-Z0-9._\-]+/i',
        'twitter' => '/(?:https?:\/\/)?(?:www\.)?(?:twitter|x)\.com\/[a-zA-Z0-9._\-]+/i',
        'linkedin' => '/(?:https?:\/\/)?(?:www\.)?linkedin\.com\/(?:in|company)\/[a-zA-Z0-9._\-]+/i',
        'whatsapp' => '/(?:https?:\/\/)?(?:api\.)?wa\.me\/[0-9]+|(?:https?:\/\/)?(?:web\.)?whatsapp\.com\/send\?phone=[0-9]+/i',
    ];

    // Pages to prioritize for contact info
    protected array $contactPages = [
        'contact',
        'about',
        'about-us',
        'contact-us',
        'contactus',
        'aboutus',
        'impressum',
        'imprint',
        'legal',
        'team',
        'support',
        'help',
        'customer-service',
        'get-in-touch',
        'reach-us',
        'locations',
        'store-locator',
        'branches',
        'privacy',
        'privacy-policy',
        'terms',
    ];

    /**
     * Scrape a single website for contact information
     */
    public function scrapeWebsite(string $url, array $options = []): array
    {
        $this->visitedUrls = [];
        $maxPages = $options['max_pages'] ?? $this->maxPages;

        // Normalize URL
        $url = $this->normalizeUrl($url);
        $baseUrl = $this->getBaseUrl($url);

        $allData = [
            'emails'    => [],
            'phones'    => [],
            'socials'   => [],
            'metadata'  => [],
        ];

        // First, scrape the main page
        $mainPageData = $this->fetchAndParse($url);
        if ($mainPageData) {
            $this->mergeData($allData, $mainPageData);

            // Find contact pages
            $links = $this->extractLinks($mainPageData['html'] ?? '', $baseUrl);
            $contactLinks = $this->filterContactLinks($links);

            // Scrape contact pages first (priority)
            foreach ($contactLinks as $link) {
                if (count($this->visitedUrls) >= $maxPages) {
                    break;
                }

                $pageData = $this->fetchAndParse($link);
                if ($pageData) {
                    $this->mergeData($allData, $pageData);
                }
            }
        }

        return $this->formatResults($url, $allData);
    }

    /**
     * Scrape multiple websites
     */
    public function scrapeMultipleWebsites(array $urls, array $options = []): array
    {
        $results = [];

        foreach ($urls as $url) {
            $results[] = $this->scrapeWebsite($url, $options);
        }

        return $results;
    }

    /**
     * Get random user agent
     */
    protected function getRandomUserAgent(): string
    {
        return $this->userAgents[array_rand($this->userAgents)];
    }

    /**
     * Fetch URL and parse content
     */
    protected function fetchAndParse(string $url): ?array
    {
        if (in_array($url, $this->visitedUrls)) {
            return null;
        }

        $this->visitedUrls[] = $url;

        try {
            // Use realistic browser headers
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'User-Agent' => $this->getRandomUserAgent(),
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Accept-Encoding' => 'gzip, deflate, br',
                    'DNT' => '1',
                    'Connection' => 'keep-alive',
                    'Upgrade-Insecure-Requests' => '1',
                    'Sec-Fetch-Dest' => 'document',
                    'Sec-Fetch-Mode' => 'navigate',
                    'Sec-Fetch-Site' => 'none',
                    'Sec-Fetch-User' => '?1',
                    'Cache-Control' => 'max-age=0',
                ])
                ->withOptions([
                    'verify' => false, // Skip SSL verification for some sites
                    'allow_redirects' => true,
                ])
                ->get($url);

            if (!$response->successful()) {
                Log::debug('Failed to fetch URL', ['url' => $url, 'status' => $response->status()]);
                return null;
            }

            $html = $response->body();
            $contentType = $response->header('Content-Type', '');

            // Only parse HTML content
            if (!Str::contains($contentType, ['text/html', 'application/xhtml']) && !empty($contentType)) {
                return null;
            }

            return [
                'url'      => $url,
                'html'     => $html,
                'emails'   => $this->extractEmails($html),
                'phones'   => $this->extractPhones($html),
                'socials'  => $this->extractSocialProfiles($html),
                'metadata' => $this->extractMetadata($html),
            ];

        } catch (\Exception $e) {
            Log::warning('Failed to scrape URL', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Extract emails from HTML content
     */
    protected function extractEmails(string $html): array
    {
        $emails = [];
        $originalHtml = $html;

        // 1. Check for CloudFlare email protection and decode it
        $emails = array_merge($emails, $this->decodeCloudflareEmails($html));

        // 2. Extract from JSON-LD structured data
        $emails = array_merge($emails, $this->extractEmailsFromJsonLd($originalHtml));

        // 3. Extract from schema.org markup
        $emails = array_merge($emails, $this->extractEmailsFromSchema($originalHtml));

        // 4. Extract from meta tags
        preg_match_all('/<meta[^>]*(?:property|name)=["\'](?:og:email|email|contact:email)["\'][^>]*content=["\']([^"\']+)["\']|<meta[^>]*content=["\']([^"\']+)["\'][^>]*(?:property|name)=["\'](?:og:email|email|contact:email)["\']/i', $html, $metaMatches);
        foreach (array_merge($metaMatches[1], $metaMatches[2]) as $email) {
            if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $email;
            }
        }

        // Remove HTML comments, scripts, and styles for text extraction
        $html = preg_replace('/<!--.*?-->/s', '', $html);
        $html = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);
        $html = preg_replace('/<noscript[^>]*>.*?<\/noscript>/is', '', $html);

        // Decode HTML entities
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // 5. Handle various email obfuscation patterns
        $deobfuscated = $html;

        // [at] or (at) or {at} or " at " patterns
        $deobfuscated = preg_replace('/\s*[\[\(\{]\s*at\s*[\]\)\}]\s*/i', '@', $deobfuscated);
        $deobfuscated = preg_replace('/\s+at\s+/i', '@', $deobfuscated);

        // [dot] or (dot) or {dot} or " dot " patterns
        $deobfuscated = preg_replace('/\s*[\[\(\{]\s*dot\s*[\]\)\}]\s*/i', '.', $deobfuscated);
        $deobfuscated = preg_replace('/\s+dot\s+/i', '.', $deobfuscated);

        // Handle HTML encoded @ and .
        $deobfuscated = str_replace(['&#64;', '&#x40;', '%40'], '@', $deobfuscated);
        $deobfuscated = str_replace(['&#46;', '&#x2e;', '%2e'], '.', $deobfuscated);

        // Handle (arroba) or [arroba] (Spanish)
        $deobfuscated = preg_replace('/\s*[\[\(\{]\s*arroba\s*[\]\)\}]\s*/i', '@', $deobfuscated);

        // 6. Extract from mailto links (before cleaning HTML)
        preg_match_all('/mailto:([^"\'?&\s<>]+)/i', $originalHtml, $mailtoMatches);
        if (!empty($mailtoMatches[1])) {
            foreach ($mailtoMatches[1] as $email) {
                $email = urldecode($email);
                $emails[] = $email;
            }
        }

        // 7. Extract from href containing email
        preg_match_all('/href=["\'][^"\']*?([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})[^"\']*?["\']/i', $originalHtml, $hrefMatches);
        if (!empty($hrefMatches[1])) {
            $emails = array_merge($emails, $hrefMatches[1]);
        }

        // 8. Extract from data attributes
        preg_match_all('/data-(?:email|mail|contact)["\s]*[:=]\s*["\']?([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})/i', $originalHtml, $dataMatches);
        if (!empty($dataMatches[1])) {
            $emails = array_merge($emails, $dataMatches[1]);
        }

        // 9. Find all email patterns in deobfuscated text
        preg_match_all($this->patterns['email'], $deobfuscated, $matches);
        if (!empty($matches[0])) {
            $emails = array_merge($emails, $matches[0]);
        }

        // 10. Look for common email prefixes in text (info@, contact@, support@, etc.)
        $commonPrefixes = ['info', 'contact', 'support', 'hello', 'hi', 'sales', 'admin', 'help', 'service', 'enquiry', 'inquiry', 'mail', 'office', 'team'];
        $text = strip_tags($deobfuscated);
        foreach ($commonPrefixes as $prefix) {
            preg_match_all('/' . $prefix . '\s*[@]\s*[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/i', $text, $prefixMatches);
            if (!empty($prefixMatches[0])) {
                foreach ($prefixMatches[0] as $match) {
                    $clean = preg_replace('/\s+/', '', $match);
                    $emails[] = $clean;
                }
            }
        }

        // Clean and validate emails
        $emails = array_map(function($email) {
            $email = strtolower(trim($email));
            $email = preg_replace('/^mailto:/i', '', $email);
            $email = preg_replace('/\?.*$/', '', $email); // Remove query params
            return $email;
        }, $emails);

        $emails = array_unique($emails);
        $emails = array_filter($emails, [$this, 'isValidEmail']);

        // Filter out common false positives
        $emails = array_filter($emails, function ($email) {
            $excluded = [
                'example.com', 'example.org', 'test.com', 'domain.com',
                'email.com', 'yourdomain', 'company.com', 'website.com',
                'wixpress.com', 'sentry.io', 'sentry-next.wixpress.com',
                'your-email', 'youremail', 'user@', 'name@', 'email@',
                'someone@', 'username@', 'placeholder', 'sample.com',
                '.png', '.jpg', '.gif', '.svg', '.css', '.js',
                'jquery', 'bootstrap', 'webpack', 'bundle',
            ];

            foreach ($excluded as $pattern) {
                if (Str::contains($email, $pattern)) {
                    return false;
                }
            }

            // Must have at least 2 chars before @
            if (strpos($email, '@') < 2) {
                return false;
            }

            // Domain must have at least one dot
            $domain = substr($email, strpos($email, '@') + 1);
            if (strpos($domain, '.') === false) {
                return false;
            }

            return true;
        });

        return array_values($emails);
    }

    /**
     * Decode CloudFlare email protection
     */
    protected function decodeCloudflareEmails(string $html): array
    {
        $emails = [];

        // CloudFlare uses data-cfemail attribute with hex encoded email
        preg_match_all('/data-cfemail=["\']([a-f0-9]+)["\']/i', $html, $matches);

        foreach ($matches[1] as $encoded) {
            $decoded = $this->decodeCloudflareEmail($encoded);
            if ($decoded && filter_var($decoded, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $decoded;
            }
        }

        // Also check for __cf_email__ in href
        preg_match_all('/href=["\']\/cdn-cgi\/l\/email-protection#([a-f0-9]+)["\']/i', $html, $hrefMatches);
        foreach ($hrefMatches[1] as $encoded) {
            $decoded = $this->decodeCloudflareEmail($encoded);
            if ($decoded && filter_var($decoded, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $decoded;
            }
        }

        return $emails;
    }

    /**
     * Decode a single CloudFlare encoded email
     */
    protected function decodeCloudflareEmail(string $encoded): ?string
    {
        try {
            $key = hexdec(substr($encoded, 0, 2));
            $email = '';

            for ($i = 2; $i < strlen($encoded); $i += 2) {
                $char = hexdec(substr($encoded, $i, 2)) ^ $key;
                $email .= chr($char);
            }

            return $email;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Extract emails from JSON-LD structured data
     */
    protected function extractEmailsFromJsonLd(string $html): array
    {
        $emails = [];

        preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $matches);

        foreach ($matches[1] as $json) {
            try {
                $data = json_decode($json, true);
                if (is_array($data)) {
                    $emails = array_merge($emails, $this->extractEmailsFromArray($data));
                }
            } catch (\Exception $e) {
                // Invalid JSON, skip
            }
        }

        return $emails;
    }

    /**
     * Extract emails from schema.org markup
     */
    protected function extractEmailsFromSchema(string $html): array
    {
        $emails = [];

        // Look for itemprop="email"
        preg_match_all('/<[^>]*itemprop=["\']email["\'][^>]*>([^<]*)</i', $html, $matches);
        foreach ($matches[1] as $email) {
            $email = trim(strip_tags($email));
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $email;
            }
        }

        // Look for content attribute in itemprop="email"
        preg_match_all('/<[^>]*itemprop=["\']email["\'][^>]*content=["\']([^"\']+)["\']/i', $html, $contentMatches);
        foreach ($contentMatches[1] as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $email;
            }
        }

        return $emails;
    }

    /**
     * Recursively extract emails from array (for JSON-LD)
     */
    protected function extractEmailsFromArray(array $data): array
    {
        $emails = [];

        foreach ($data as $key => $value) {
            if (is_string($value)) {
                if (in_array(strtolower($key), ['email', 'mail', 'contactemail', 'e-mail'])) {
                    if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $emails[] = $value;
                    }
                } elseif (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $emails[] = $value;
                }
            } elseif (is_array($value)) {
                $emails = array_merge($emails, $this->extractEmailsFromArray($value));
            }
        }

        return $emails;
    }

    /**
     * Extract phone numbers from HTML content
     */
    protected function extractPhones(string $html): array
    {
        $phones = [];

        // Find tel: links first (highest priority)
        preg_match_all('/tel:\+?([0-9\s\-\.]+)/i', $html, $telMatches);
        if (!empty($telMatches[1])) {
            foreach ($telMatches[1] as $phone) {
                $cleaned = preg_replace('/[^0-9+]/', '', $phone);
                if ($this->isValidPhone($cleaned)) {
                    $phones[] = $cleaned;
                }
            }
        }

        // Find href with phone-like links
        preg_match_all('/href=["\'][^"\']*?(\+?[0-9][\s\-\.0-9]{8,})[^"\']*?["\']/i', $html, $hrefMatches);
        if (!empty($hrefMatches[1])) {
            foreach ($hrefMatches[1] as $phone) {
                $cleaned = preg_replace('/[^0-9+]/', '', $phone);
                if ($this->isValidPhone($cleaned)) {
                    $phones[] = $cleaned;
                }
            }
        }

        // Look for phone with indicators (most reliable)
        $text = strip_tags($html);
        $text = html_entity_decode($text);

        // Find phones near phone indicators
        $phoneIndicators = ['phone', 'tel', 'call', 'mobile', 'cell', 'whatsapp', 'hotline', 'fax'];
        foreach ($phoneIndicators as $indicator) {
            preg_match_all('/' . $indicator . '[:\s]*(\+?[0-9][\s\-\.0-9]{8,})/i', $text, $indicatorMatches);
            foreach ($indicatorMatches[1] as $phone) {
                $cleaned = preg_replace('/[^0-9+]/', '', $phone);
                if ($this->isValidPhone($cleaned)) {
                    $phones[] = $cleaned;
                }
            }
        }

        // Find international format phones (+XX XXX XXX XXXX)
        preg_match_all('/\+[1-9]\d{0,3}[\s\-\.]?\(?\d{2,4}\)?[\s\-\.]?\d{3,4}[\s\-\.]?\d{3,4}/', $text, $intlMatches);
        foreach ($intlMatches[0] as $phone) {
            $cleaned = preg_replace('/[^0-9+]/', '', $phone);
            if ($this->isValidPhone($cleaned)) {
                $phones[] = $cleaned;
            }
        }

        $phones = array_unique($phones);
        return array_values($phones);
    }

    /**
     * Validate phone number
     */
    protected function isValidPhone(string $phone): bool
    {
        // Remove + for length check
        $digits = ltrim($phone, '+');

        // Must be between 7 and 15 digits
        if (strlen($digits) < 7 || strlen($digits) > 15) {
            return false;
        }

        // Filter out timestamps (10 or 13 digits starting with 1)
        if (strlen($digits) >= 10 && preg_match('/^1[4-9]\d{8,}$/', $digits)) {
            return false;
        }

        // Filter out dates (YYYYMMDD format)
        if (preg_match('/^20[0-2][0-9](0[1-9]|1[0-2])(0[1-9]|[12][0-9]|3[01])/', $digits)) {
            return false;
        }

        // Filter out sequential numbers
        if (preg_match('/^(\d)\1{6,}$/', $digits)) {
            return false;
        }

        // Must start with valid country code or local format
        if (strlen($digits) >= 10) {
            // Common valid starting patterns
            $validStarts = [
                '/^1[2-9]/',     // US/Canada
                '/^44/',         // UK
                '/^49/',         // Germany
                '/^33/',         // France
                '/^880/',        // Bangladesh
                '/^91/',         // India
                '/^61/',         // Australia
                '/^81/',         // Japan
                '/^86/',         // China
                '/^971/',        // UAE
                '/^966/',        // Saudi
                '/^0[1-9]/',     // Local format
            ];

            $hasValidStart = false;
            foreach ($validStarts as $pattern) {
                if (preg_match($pattern, $digits)) {
                    $hasValidStart = true;
                    break;
                }
            }

            // For longer numbers, require valid country code format
            if (strlen($digits) >= 11 && !$hasValidStart && !str_starts_with($phone, '+')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Extract social media profiles from HTML content
     */
    protected function extractSocialProfiles(string $html): array
    {
        $socials = [
            'facebook'  => null,
            'instagram' => null,
            'twitter'   => null,
            'linkedin'  => null,
        ];

        foreach (['facebook', 'instagram', 'twitter', 'linkedin'] as $platform) {
            preg_match($this->patterns[$platform], $html, $match);
            if (!empty($match[0])) {
                $socials[$platform] = $this->normalizeUrl($match[0]);
            }
        }

        return $socials;
    }

    /**
     * Extract metadata from HTML
     */
    protected function extractMetadata(string $html): array
    {
        $metadata = [
            'title'       => null,
            'description' => null,
            'company'     => null,
        ];

        // Extract title
        if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $match)) {
            $metadata['title'] = trim(html_entity_decode($match[1]));
        }

        // Extract meta description
        if (preg_match('/<meta[^>]*name=["\']description["\'][^>]*content=["\']([^"\']+)["\']|<meta[^>]*content=["\']([^"\']+)["\'][^>]*name=["\']description["\']/i', $html, $match)) {
            $metadata['description'] = trim(html_entity_decode($match[1] ?: $match[2]));
        }

        // Try to extract company name from structured data
        if (preg_match('/"name"\s*:\s*"([^"]+)"/', $html, $match)) {
            $metadata['company'] = html_entity_decode($match[1]);
        }

        return $metadata;
    }

    /**
     * Extract links from HTML
     */
    protected function extractLinks(string $html, string $baseUrl): array
    {
        $links = [];

        preg_match_all('/<a[^>]+href=["\']([^"\']+)["\']/', $html, $matches);

        foreach ($matches[1] as $href) {
            $link = $this->resolveUrl($href, $baseUrl);
            if ($link && Str::startsWith($link, $baseUrl)) {
                $links[] = $link;
            }
        }

        return array_unique($links);
    }

    /**
     * Filter links to find contact pages
     */
    protected function filterContactLinks(array $links): array
    {
        $contactLinks = [];

        foreach ($links as $link) {
            $path = parse_url($link, PHP_URL_PATH) ?? '';
            $path = strtolower(trim($path, '/'));

            foreach ($this->contactPages as $keyword) {
                if (Str::contains($path, $keyword)) {
                    $contactLinks[] = $link;
                    break;
                }
            }
        }

        return array_unique($contactLinks);
    }

    /**
     * Normalize URL
     */
    protected function normalizeUrl(string $url): string
    {
        // Add protocol if missing
        if (!preg_match('~^(?:f|ht)tps?://~i', $url)) {
            $url = 'https://' . $url;
        }

        // Parse and rebuild URL
        $parsed = parse_url($url);
        if (!$parsed || !isset($parsed['host'])) {
            return $url;
        }

        $normalized = ($parsed['scheme'] ?? 'https') . '://' . $parsed['host'];

        if (isset($parsed['port'])) {
            $normalized .= ':' . $parsed['port'];
        }

        if (isset($parsed['path'])) {
            $normalized .= $parsed['path'];
        }

        return rtrim($normalized, '/');
    }

    /**
     * Get base URL from a full URL
     */
    protected function getBaseUrl(string $url): string
    {
        $parsed = parse_url($url);
        return ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');
    }

    /**
     * Resolve relative URL to absolute
     */
    protected function resolveUrl(string $href, string $baseUrl): ?string
    {
        // Skip certain types
        if (Str::startsWith($href, ['#', 'javascript:', 'mailto:', 'tel:'])) {
            return null;
        }

        // Already absolute
        if (preg_match('~^(?:f|ht)tps?://~i', $href)) {
            return $href;
        }

        // Protocol-relative
        if (Str::startsWith($href, '//')) {
            return 'https:' . $href;
        }

        // Root-relative
        if (Str::startsWith($href, '/')) {
            return rtrim($baseUrl, '/') . $href;
        }

        // Relative
        return rtrim($baseUrl, '/') . '/' . ltrim($href, '/');
    }

    /**
     * Validate email address
     */
    protected function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Merge extracted data
     */
    protected function mergeData(array &$allData, array $newData): void
    {
        $allData['emails'] = array_unique(array_merge(
            $allData['emails'],
            $newData['emails'] ?? []
        ));

        $allData['phones'] = array_unique(array_merge(
            $allData['phones'],
            $newData['phones'] ?? []
        ));

        foreach (['facebook', 'instagram', 'twitter', 'linkedin'] as $platform) {
            if (empty($allData['socials'][$platform]) && !empty($newData['socials'][$platform])) {
                $allData['socials'][$platform] = $newData['socials'][$platform];
            }
        }

        if (empty($allData['metadata']['title']) && !empty($newData['metadata']['title'])) {
            $allData['metadata']['title'] = $newData['metadata']['title'];
        }

        if (empty($allData['metadata']['company']) && !empty($newData['metadata']['company'])) {
            $allData['metadata']['company'] = $newData['metadata']['company'];
        }
    }

    /**
     * Format results into a standardized structure
     */
    protected function formatResults(string $url, array $data): array
    {
        $primaryEmail = $data['emails'][0] ?? null;
        $primaryPhone = $data['phones'][0] ?? null;

        return [
            'business_name' => $data['metadata']['company'] ?? $data['metadata']['title'] ?? parse_url($url, PHP_URL_HOST),
            'email'         => $primaryEmail,
            'phone'         => $primaryPhone,
            'website'       => $url,
            'facebook'      => $data['socials']['facebook'] ?? null,
            'instagram'     => $data['socials']['instagram'] ?? null,
            'twitter'       => $data['socials']['twitter'] ?? null,
            'linkedin'      => $data['socials']['linkedin'] ?? null,
            'source_url'    => $url,
            'raw_data'      => [
                'all_emails'   => $data['emails'],
                'all_phones'   => $data['phones'],
                'title'        => $data['metadata']['title'] ?? null,
                'description'  => $data['metadata']['description'] ?? null,
                'pages_scraped' => count($this->visitedUrls),
            ],
        ];
    }

    /**
     * Process a scraping job
     */
    public function processJob(LeadScrapingJob $job): void
    {
        $job->markAsProcessing();

        $params = $job->parameters ?? [];
        $urls = $params['urls'] ?? [];

        if (empty($urls)) {
            $job->markAsFailed('No URLs provided');
            return;
        }

        // Ensure urls is an array
        if (is_string($urls)) {
            $urls = array_filter(array_map('trim', explode("\n", $urls)));
        }

        // Set total_found to URL count so progress tracking works
        $job->update(['total_found' => count($urls)]);

        $settings = LeadGenerationSetting::getForUser($job->user_id);

        try {
            $totalLeads = 0;

            // Process each URL individually for better progress tracking
            foreach ($urls as $index => $url) {
                try {
                    $url = trim($url);
                    if (empty($url)) continue;

                    // Ensure URL has protocol
                    if (!preg_match('/^https?:\/\//i', $url)) {
                        $url = 'https://' . $url;
                    }

                    $results = $this->scrapeMultipleWebsites([$url], [
                        'max_pages' => $params['max_pages_per_site'] ?? 10,
                    ]);

                    foreach ($results as $data) {
                        if (empty($data['email']) && empty($data['phone'])) {
                            continue;
                        }

                        $lead = ScrapedLead::create([
                            'job_id'        => $job->id,
                            'user_id'       => $job->user_id,
                            'business_name' => $data['business_name'],
                            'email'         => $data['email'],
                            'phone'         => $data['phone'],
                            'website'       => $data['website'],
                            'facebook'      => $data['facebook'],
                            'instagram'     => $data['instagram'],
                            'twitter'       => $data['twitter'],
                            'linkedin'      => $data['linkedin'],
                            'source_url'    => $data['source_url'],
                            'raw_data'      => $data['raw_data'],
                        ]);

                        $lead->update(['quality_score' => $lead->calculateQualityScore()]);
                        $totalLeads++;
                    }
                } catch (\Exception $e) {
                    Log::warning("Website scraper: failed to process URL", [
                        'url' => $url, 'error' => $e->getMessage()
                    ]);
                }

                // Update progress after each URL (not just at the end)
                $job->update([
                    'processed_count' => $index + 1,
                    'total_found'     => max(count($urls), $totalLeads),
                ]);
            }

            $settings->incrementScrapes($totalLeads);

            // Final update with actual lead count
            $job->update(['total_found' => $totalLeads]);
            $job->markAsCompleted();

        } catch (\Exception $e) {
            Log::error('Website scraping failed', [
                'job_id' => $job->id,
                'error'  => $e->getMessage(),
            ]);
            $job->markAsFailed($e->getMessage());
        }
    }
}
