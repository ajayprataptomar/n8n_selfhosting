<?php

namespace App\Enums\System;

use App\Enums\EnumTrait;

enum ChannelTypeEnum: string
{
    use EnumTrait;

    case EMAIL      = 'email';
    case SMS        = 'sms';
    case WHATSAPP   = 'whatsapp';

    /**
     * values
     *
     * @return string
     */
    public function values(): string
    {
        return match($this) {
            self::EMAIL     => 'Email',
            self::SMS       => 'SMS',
            self::WHATSAPP  => 'WhatsApp',
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
            self::EMAIL      => 'danger',
            self::SMS        => 'info',
            self::WHATSAPP   => 'success',
        };

        echo "<span class='i-badge dot pill {$color}'>{$this->values()}</span>";
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