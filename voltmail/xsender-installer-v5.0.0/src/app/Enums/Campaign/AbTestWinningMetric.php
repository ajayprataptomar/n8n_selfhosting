<?php

namespace App\Enums\Campaign;

use App\Enums\EnumTrait;

enum AbTestWinningMetric: string
{
    use EnumTrait;

    case DELIVERED = 'delivered';
    case OPENED = 'opened';
    case CLICKED = 'clicked';
    case REPLIED = 'replied';

    /**
     * Get human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::DELIVERED => translate('Delivery Rate'),
            self::OPENED => translate('Open Rate'),
            self::CLICKED => translate('Click Rate'),
            self::REPLIED => translate('Reply Rate'),
        };
    }

    /**
     * Get description
     */
    public function description(): string
    {
        return match ($this) {
            self::DELIVERED => translate('Winner has highest delivery rate'),
            self::OPENED => translate('Winner has highest open rate'),
            self::CLICKED => translate('Winner has highest click rate'),
            self::REPLIED => translate('Winner has highest reply rate'),
        };
    }

    /**
     * Get icon
     */
    public function icon(): string
    {
        return match ($this) {
            self::DELIVERED => 'ri-send-plane-line',
            self::OPENED => 'ri-mail-open-line',
            self::CLICKED => 'ri-cursor-line',
            self::REPLIED => 'ri-reply-line',
        };
    }
}
