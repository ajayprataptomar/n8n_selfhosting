<?php

namespace App\Enums\Campaign;

use App\Enums\EnumTrait;

enum ChannelDetectionMode: string
{
    use EnumTrait;

    case AUTO = 'auto';
    case MANUAL = 'manual';
    case PRIORITY_FALLBACK = 'priority_fallback';

    /**
     * Get human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::AUTO => translate('Auto Detect'),
            self::MANUAL => translate('Selected Only'),
            self::PRIORITY_FALLBACK => translate('Priority Fallback'),
        };
    }

    /**
     * Get description
     */
    public function description(): string
    {
        return match ($this) {
            self::AUTO => translate('Smart delivery - automatically uses the best channel available for each contact'),
            self::MANUAL => translate('Send only via the channels you select above - contacts without those channels are skipped'),
            self::PRIORITY_FALLBACK => translate('Try channels in order (e.g., WhatsApp first, then SMS) until delivery succeeds'),
        };
    }

    /**
     * Get icon class
     */
    public function icon(): string
    {
        return match ($this) {
            self::AUTO => 'ri-magic-line',
            self::MANUAL => 'ri-checkbox-multiple-line',
            self::PRIORITY_FALLBACK => 'ri-list-ordered',
        };
    }
}
