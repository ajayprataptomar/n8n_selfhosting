<?php

namespace App\Enums\Campaign;

use App\Enums\EnumTrait;

enum CampaignType: string
{
    use EnumTrait;

    case INSTANT = 'instant';
    case SCHEDULED = 'scheduled';
    case RECURRING = 'recurring';

    /**
     * Get human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::INSTANT => translate('Instant'),
            self::SCHEDULED => translate('Scheduled'),
            self::RECURRING => translate('Recurring'),
        };
    }

    /**
     * Get description
     */
    public function description(): string
    {
        return match ($this) {
            self::INSTANT => translate('Send immediately after creation'),
            self::SCHEDULED => translate('Send at a specific date and time'),
            self::RECURRING => translate('Send repeatedly on a schedule'),
        };
    }

    /**
     * Get icon class
     */
    public function icon(): string
    {
        return match ($this) {
            self::INSTANT => 'bi bi-lightning',
            self::SCHEDULED => 'bi bi-calendar-event',
            self::RECURRING => 'bi bi-arrow-repeat',
        };
    }

    /**
     * Check if type requires scheduling
     */
    public function requiresSchedule(): bool
    {
        return in_array($this, [self::SCHEDULED, self::RECURRING]);
    }
}
