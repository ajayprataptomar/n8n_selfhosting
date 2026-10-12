<?php

namespace App\Managers;

use App\Models\B2bProvider;
use App\Enums\System\B2bProviderTypeEnum;
use App\Services\B2bDatabase\Contracts\B2bDriverInterface;
use App\Services\B2bDatabase\Drivers\ApolloDriver;
use App\Services\B2bDatabase\Drivers\HunterDriver;
use App\Services\B2bDatabase\Drivers\SnovDriver;
use App\Services\B2bDatabase\Drivers\RocketReachDriver;

class B2bProviderManager
{
    /**
     * Resolve driver instance for a provider
     */
    public function driver(B2bProvider $provider): B2bDriverInterface
    {
        $type = $provider->type instanceof B2bProviderTypeEnum
            ? $provider->type
            : B2bProviderTypeEnum::from($provider->type);

        $driver = match ($type) {
            B2bProviderTypeEnum::APOLLO      => new ApolloDriver(),
            B2bProviderTypeEnum::HUNTER      => new HunterDriver(),
            B2bProviderTypeEnum::SNOV        => new SnovDriver(),
            B2bProviderTypeEnum::ROCKETREACH => new RocketReachDriver(),
        };

        return $driver->setProvider($provider);
    }

    /**
     * Get all available provider types
     */
    public static function availableTypes(): array
    {
        return array_map(fn(B2bProviderTypeEnum $type) => [
            'value'       => $type->value,
            'label'       => $type->label(),
            'icon'        => $type->icon(),
            'description' => $type->description(),
            'fields'      => $type->credentialFields(),
        ], B2bProviderTypeEnum::cases());
    }
}
