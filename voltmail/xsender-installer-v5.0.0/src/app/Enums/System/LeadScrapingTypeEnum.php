<?php

namespace App\Enums\System;

use App\Enums\EnumTrait;

enum LeadScrapingTypeEnum: string
{
    use EnumTrait;

    case GOOGLE_MAPS = 'google_maps';
    case WEBSITE     = 'website';
    case ENRICHMENT  = 'enrichment';

    /**
     * Get human-readable label
     *
     * @return string
     */
    public function label(): string
    {
        return match($this) {
            self::GOOGLE_MAPS => 'Google Maps',
            self::WEBSITE     => 'Website Scraper',
            self::ENRICHMENT  => 'Lead Enrichment',
        };
    }

    /**
     * Get icon for the type
     *
     * @return string
     */
    public function icon(): string
    {
        return match($this) {
            self::GOOGLE_MAPS => 'bi-geo-alt-fill',
            self::WEBSITE     => 'bi-globe',
            self::ENRICHMENT  => 'bi-person-badge-fill',
        };
    }

    /**
     * Get description
     *
     * @return string
     */
    public function description(): string
    {
        return match($this) {
            self::GOOGLE_MAPS => 'Scrape business details from Google Maps by location and category',
            self::WEBSITE     => 'Extract contact information from websites',
            self::ENRICHMENT  => 'Enrich existing leads with additional data',
        };
    }

    /**
     * Get all values
     *
     * @return array
     */
    public static function getValues(): array
    {
        return array_map(fn($case) => $case->value, self::cases());
    }
}
