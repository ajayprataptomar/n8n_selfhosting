<?php

namespace App\Enums\System;

enum B2bProviderTypeEnum: string
{
    case APOLLO       = 'apollo';
    case HUNTER       = 'hunter';
    case SNOV         = 'snov';
    case ROCKETREACH  = 'rocketreach';

    public function label(): string
    {
        return match ($this) {
            self::APOLLO      => 'Apollo.io',
            self::HUNTER      => 'Hunter.io',
            self::SNOV        => 'Snov.io',
            self::ROCKETREACH => 'RocketReach',
        };
    }

    public function baseUrl(): string
    {
        return match ($this) {
            self::APOLLO      => 'https://api.apollo.io/api/v1',
            self::HUNTER      => 'https://api.hunter.io/v2',
            self::SNOV        => 'https://api.snov.io',
            self::ROCKETREACH => 'https://api.rocketreach.co/api/v2',
        };
    }

    public function credentialFields(): array
    {
        return match ($this) {
            self::APOLLO      => ['api_key'],
            self::HUNTER      => ['api_key'],
            self::SNOV        => ['client_id', 'client_secret'],
            self::ROCKETREACH => ['api_key'],
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::APOLLO      => 'ri-rocket-line',
            self::HUNTER      => 'ri-search-eye-line',
            self::SNOV        => 'ri-mail-check-line',
            self::ROCKETREACH => 'ri-space-ship-line',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::APOLLO      => '218M+ contacts, free tier with 250 emails/day',
            self::HUNTER      => 'Domain email finder, 25 free searches/month',
            self::SNOV        => '1.5B+ emails, OAuth authentication',
            self::ROCKETREACH => '700M+ contacts, professional enrichment',
        };
    }
}
