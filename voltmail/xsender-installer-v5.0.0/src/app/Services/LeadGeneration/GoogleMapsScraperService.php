<?php

namespace App\Services\LeadGeneration;

use App\Models\LeadScrapingJob;
use App\Models\ScrapedLead;
use App\Models\LeadGenerationSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleMapsScraperService
{
    protected string $apiKey;
    protected string $baseUrl = 'https://maps.googleapis.com/maps/api/place';

    /**
     * Constructor
     */
    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?? config('services.google.places_api_key', '');
    }

    /**
     * Set API key
     */
    public function setApiKey(string $apiKey): self
    {
        $this->apiKey = $apiKey;
        return $this;
    }

    /**
     * Search for businesses using Google Places Text Search
     */
    public function searchBusinesses(string $query, string $location, array $options = []): array
    {
        $results = [];
        $nextPageToken = null;
        $maxResults = $options['max_results'] ?? 60; // Max 60 results (3 pages)
        $pageCount = 0;
        $maxPages = 3; // Google allows max 3 pages (20 results each)

        do {
            $response = $this->textSearch($query, $location, $nextPageToken);

            if (!$response['success']) {
                Log::error('Google Maps search failed', [
                    'error' => $response['error'] ?? 'Unknown error',
                    'query' => $query,
                    'location' => $location,
                ]);
                break;
            }

            $places = $response['results'] ?? [];

            foreach ($places as $place) {
                if (count($results) >= $maxResults) {
                    break 2;
                }

                // Get place details for more info
                $details = $this->getPlaceDetails($place['place_id']);

                $results[] = $this->formatPlaceData($place, $details);
            }

            $nextPageToken = $response['next_page_token'] ?? null;
            $pageCount++;

            // Wait for next page token to become valid
            if ($nextPageToken && $pageCount < $maxPages) {
                sleep(2);
            }

        } while ($nextPageToken && $pageCount < $maxPages && count($results) < $maxResults);

        return $results;
    }

    /**
     * Perform text search using Google Places API
     */
    protected function textSearch(string $query, string $location, ?string $pageToken = null): array
    {
        $params = [
            'query' => $query . ' in ' . $location,
            'key'   => $this->apiKey,
        ];

        if ($pageToken) {
            $params['pagetoken'] = $pageToken;
        }

        try {
            $response = Http::timeout(30)->get("{$this->baseUrl}/textsearch/json", $params);

            if ($response->successful()) {
                $data = $response->json();

                if ($data['status'] === 'OK' || $data['status'] === 'ZERO_RESULTS') {
                    return [
                        'success'         => true,
                        'results'         => $data['results'] ?? [],
                        'next_page_token' => $data['next_page_token'] ?? null,
                    ];
                }

                return [
                    'success' => false,
                    'error'   => $data['status'] . ': ' . ($data['error_message'] ?? 'Unknown error'),
                ];
            }

            return [
                'success' => false,
                'error'   => 'HTTP Error: ' . $response->status(),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Get detailed place information
     */
    protected function getPlaceDetails(string $placeId): array
    {
        $fields = implode(',', [
            'formatted_phone_number',
            'international_phone_number',
            'website',
            'url',
            'opening_hours',
            'reviews',
            'photos',
            'address_components',
        ]);

        try {
            $response = Http::timeout(30)->get("{$this->baseUrl}/details/json", [
                'place_id' => $placeId,
                'fields'   => $fields,
                'key'      => $this->apiKey,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if ($data['status'] === 'OK') {
                    return $data['result'] ?? [];
                }
            }

        } catch (\Exception $e) {
            Log::warning('Failed to get place details', [
                'place_id' => $placeId,
                'error'    => $e->getMessage(),
            ]);
        }

        return [];
    }

    /**
     * Format place data into a standardized structure
     */
    protected function formatPlaceData(array $place, array $details = []): array
    {
        // Parse address components
        $addressComponents = $this->parseAddressComponents(
            $details['address_components'] ?? []
        );

        // Extract phone number
        $phone = $details['international_phone_number']
            ?? $details['formatted_phone_number']
            ?? null;

        // Clean phone number
        if ($phone) {
            $phone = preg_replace('/[^0-9+]/', '', $phone);
        }

        // Get website
        $website = $details['website'] ?? null;

        // Try to find email from website (Google doesn't provide emails!)
        $email = null;
        $socials = [];

        if ($website) {
            $websiteData = $this->scrapeWebsiteForEmail($website);
            $email = $websiteData['email'] ?? null;
            $socials = $websiteData['socials'] ?? [];
        }

        return [
            'business_name' => $place['name'] ?? null,
            'email'         => $email,
            'phone'         => $phone,
            'website'       => $website,
            'address'       => $place['formatted_address'] ?? null,
            'city'          => $addressComponents['city'] ?? null,
            'state'         => $addressComponents['state'] ?? null,
            'country'       => $addressComponents['country'] ?? null,
            'postal_code'   => $addressComponents['postal_code'] ?? null,
            'latitude'      => $place['geometry']['location']['lat'] ?? null,
            'longitude'     => $place['geometry']['location']['lng'] ?? null,
            'category'      => $place['types'][0] ?? null,
            'rating'        => $place['rating'] ?? null,
            'reviews_count' => $place['user_ratings_total'] ?? null,
            'place_id'      => $place['place_id'] ?? null,
            'source_url'    => $details['url'] ?? null,
            'facebook'      => $socials['facebook'] ?? null,
            'instagram'     => $socials['instagram'] ?? null,
            'twitter'       => $socials['twitter'] ?? null,
            'linkedin'      => $socials['linkedin'] ?? null,
            'raw_data'      => [
                'types'         => $place['types'] ?? [],
                'opening_hours' => $details['opening_hours'] ?? null,
                'photos_count'  => count($details['photos'] ?? []),
            ],
        ];
    }

    /**
     * Scrape website for email and social profiles
     * This is critical because Google Places API does NOT provide emails
     */
    protected function scrapeWebsiteForEmail(string $websiteUrl): array
    {
        $result = [
            'email' => null,
            'emails' => [],
            'socials' => [],
        ];

        try {
            $websiteScraper = new WebsiteScraperService();
            $scraped = $websiteScraper->scrapeWebsite($websiteUrl, ['max_pages' => 5]);

            $result['email'] = $scraped['email'] ?? null;
            $result['emails'] = $scraped['raw_data']['all_emails'] ?? [];
            $result['socials'] = [
                'facebook' => $scraped['facebook'] ?? null,
                'instagram' => $scraped['instagram'] ?? null,
                'twitter' => $scraped['twitter'] ?? null,
                'linkedin' => $scraped['linkedin'] ?? null,
            ];

        } catch (\Exception $e) {
            Log::debug('Failed to scrape website for email', [
                'url' => $websiteUrl,
                'error' => $e->getMessage(),
            ]);
        }

        return $result;
    }

    /**
     * Parse Google address components into a structured format
     */
    protected function parseAddressComponents(array $components): array
    {
        $result = [
            'street_number' => null,
            'street'        => null,
            'city'          => null,
            'state'         => null,
            'country'       => null,
            'postal_code'   => null,
        ];

        foreach ($components as $component) {
            $types = $component['types'] ?? [];

            if (in_array('street_number', $types)) {
                $result['street_number'] = $component['long_name'];
            } elseif (in_array('route', $types)) {
                $result['street'] = $component['long_name'];
            } elseif (in_array('locality', $types)) {
                $result['city'] = $component['long_name'];
            } elseif (in_array('administrative_area_level_1', $types)) {
                $result['state'] = $component['short_name'];
            } elseif (in_array('country', $types)) {
                $result['country'] = $component['long_name'];
            } elseif (in_array('postal_code', $types)) {
                $result['postal_code'] = $component['long_name'];
            }
        }

        return $result;
    }

    /**
     * Process a scraping job
     */
    public function processJob(LeadScrapingJob $job): void
    {
        $job->markAsProcessing();

        $params = $job->parameters ?? [];
        $query = $params['query'] ?? '';
        $location = $params['location'] ?? '';
        $maxResults = $params['max_results'] ?? 60;

        if (empty($query) || empty($location)) {
            $job->markAsFailed('Missing query or location parameter');
            return;
        }

        // Get API key: check user settings first, then fall back to admin/global settings
        $settings = LeadGenerationSetting::getForUser($job->user_id);

        if ($settings->hasGoogleMapsKey()) {
            $this->setApiKey($settings->google_maps_api_key);
        } else {
            // Fall back to admin/global settings
            $globalSettings = LeadGenerationSetting::getForUser(null);
            if ($globalSettings->hasGoogleMapsKey()) {
                $this->setApiKey($globalSettings->google_maps_api_key);
            }
        }

        if (empty($this->apiKey)) {
            $job->markAsFailed(translate('Lead scraping service is currently unavailable. Please contact administrator.'));
            return;
        }

        try {
            $results = $this->searchBusinesses($query, $location, [
                'max_results' => $maxResults,
            ]);

            // Save results as scraped leads
            foreach ($results as $data) {
                $lead = ScrapedLead::create([
                    'job_id'        => $job->id,
                    'user_id'       => $job->user_id,
                    'business_name' => $data['business_name'],
                    'email'         => $data['email'],
                    'phone'         => $data['phone'],
                    'website'       => $data['website'],
                    'address'       => $data['address'],
                    'city'          => $data['city'],
                    'state'         => $data['state'],
                    'country'       => $data['country'],
                    'postal_code'   => $data['postal_code'],
                    'latitude'      => $data['latitude'],
                    'longitude'     => $data['longitude'],
                    'category'      => $data['category'],
                    'rating'        => $data['rating'],
                    'reviews_count' => $data['reviews_count'],
                    'place_id'      => $data['place_id'],
                    'source_url'    => $data['source_url'],
                    'facebook'      => $data['facebook'] ?? null,
                    'instagram'     => $data['instagram'] ?? null,
                    'twitter'       => $data['twitter'] ?? null,
                    'linkedin'      => $data['linkedin'] ?? null,
                    'raw_data'      => $data['raw_data'],
                ]);

                // Calculate and update quality score
                $lead->update([
                    'quality_score' => $lead->calculateQualityScore(),
                ]);

                $job->incrementProcessed();
            }

            // Update settings quota
            $settings->incrementScrapes(count($results));

            $job->markAsCompleted(count($results));

        } catch (\Exception $e) {
            Log::error('Google Maps scraping failed', [
                'job_id' => $job->id,
                'error'  => $e->getMessage(),
            ]);
            $job->markAsFailed($e->getMessage());
        }
    }

    /**
     * Validate API key
     */
    public function validateApiKey(?string $apiKey = null): bool
    {
        $key = $apiKey ?? $this->apiKey;

        if (empty($key)) {
            return false;
        }

        try {
            $response = Http::timeout(10)->get("{$this->baseUrl}/textsearch/json", [
                'query' => 'test',
                'key'   => $key,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                // Valid statuses that indicate API key works
                return in_array($data['status'], ['OK', 'ZERO_RESULTS']);
            }

        } catch (\Exception $e) {
            // Ignore
        }

        return false;
    }
}
