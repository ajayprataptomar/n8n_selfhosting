<?php

namespace App\Enums\Campaign;

use App\Enums\EnumTrait;

enum AbTestStatus: string
{
    use EnumTrait;

    case DRAFT = 'draft';
    case RUNNING = 'running';
    case PAUSED = 'paused';
    case COMPLETED = 'completed';
    case WINNER_SELECTED = 'winner_selected';

    /**
     * Get human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::DRAFT => translate('Draft'),
            self::RUNNING => translate('Running'),
            self::PAUSED => translate('Paused'),
            self::COMPLETED => translate('Completed'),
            self::WINNER_SELECTED => translate('Winner Selected'),
        };
    }

    /**
     * Get badge class
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::DRAFT => 'badge--secondary',
            self::RUNNING => 'badge--primary',
            self::PAUSED => 'badge--warning',
            self::COMPLETED => 'badge--info',
            self::WINNER_SELECTED => 'badge--success',
        };
    }

    /**
     * Get icon
     */
    public function icon(): string
    {
        return match ($this) {
            self::DRAFT => 'ri-draft-line',
            self::RUNNING => 'ri-play-circle-line',
            self::PAUSED => 'ri-pause-circle-line',
            self::COMPLETED => 'ri-check-double-line',
            self::WINNER_SELECTED => 'ri-trophy-line',
        };
    }
}
