<?php

namespace App\Enums\System;

use App\Enums\EnumTrait;

enum SessionStatusEnum: string
{
    use EnumTrait;

    case INITIATED      = 'initiated';
    case CONNECTED      = 'connected';
    case DISCONNECTED   = 'disconnected';
    case EXPIRED        = 'expired';

    public function values(): string
    {
        return match($this) {
            self::INITIATED     => 'Initiated',
            self::CONNECTED     => 'Connected',
            self::DISCONNECTED  => 'Disconnected',
            self::EXPIRED       => 'Expired',
        };
    }

    public function badge(): void
    {
        $color = match($this) {
            self::INITIATED     => 'primary-soft',
            self::CONNECTED     => 'success-soft',
            self::DISCONNECTED  => 'danger-soft',
            self::EXPIRED       => 'warning-soft',
        };

        echo "<span class='i-badge dot pill {$color}'>{$this->values()}</span>";
    }

    public static function getValues(): array
    {
        return array_map(fn($case) => $case->value, self::cases());
    }
}