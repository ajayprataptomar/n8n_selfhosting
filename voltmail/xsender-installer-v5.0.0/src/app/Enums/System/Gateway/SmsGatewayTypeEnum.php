<?php

namespace App\Enums\System\Gateway;

use App\Enums\EnumTrait;

enum SmsGatewayTypeEnum: string
{
    use EnumTrait;

    case API      = 'api';
    case ANDROID  = 'android';

    /**
     * values
     *
     * @return string
     */
    public function values(): string
    {
        return match($this) {
            self::API         => 'API',
            self::ANDROID     => 'Android'
        };
    }

    /**
     * badge
     *
     * @return void
     */
    public function badge(): void
    {
        $color = match($this) {
            self::API         => 'info',
            self::ANDROID     => 'success',
        };

        echo "<span class='i-badge {$color}'>{$this->values()}</span>";
    }

    /**
     * getValues
     *
     * @return array
     */
    public static function getValues(): array
    {
        return array_map(fn($case) => $case->value, self::cases());
    }
}