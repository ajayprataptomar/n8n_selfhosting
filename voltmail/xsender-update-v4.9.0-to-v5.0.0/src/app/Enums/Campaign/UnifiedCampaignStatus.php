<?php

namespace App\Enums\Campaign;

use App\Enums\EnumTrait;

enum UnifiedCampaignStatus: string
{
    use EnumTrait;

    case DRAFT = 'draft';
    case SCHEDULED = 'scheduled';
    case RUNNING = 'running';
    case PAUSED = 'paused';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    /**
     * Get human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::DRAFT => translate('Draft'),
            self::SCHEDULED => translate('Scheduled'),
            self::RUNNING => translate('Running'),
            self::PAUSED => translate('Paused'),
            self::COMPLETED => translate('Completed'),
            self::CANCELLED => translate('Cancelled'),
        };
    }

    /**
     * Get badge color class
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::DRAFT => 'badge--secondary',
            self::SCHEDULED => 'badge--info',
            self::RUNNING => 'badge--primary',
            self::PAUSED => 'badge--warning',
            self::COMPLETED => 'badge--success',
            self::CANCELLED => 'badge--danger',
        };
    }

    /**
     * Get icon class
     */
    public function icon(): string
    {
        return match ($this) {
            self::DRAFT => 'bi bi-file-earmark',
            self::SCHEDULED => 'bi bi-calendar-event',
            self::RUNNING => 'bi bi-play-circle',
            self::PAUSED => 'bi bi-pause-circle',
            self::COMPLETED => 'bi bi-check-circle',
            self::CANCELLED => 'bi bi-x-circle',
        };
    }

    /**
     * Check if campaign can be edited
     * SCHEDULED campaigns can be edited since they haven't started sending yet
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::DRAFT, self::SCHEDULED, self::PAUSED]);
    }

    /**
     * Check if campaign can be started
     */
    public function canStart(): bool
    {
        return in_array($this, [self::DRAFT, self::SCHEDULED, self::PAUSED]);
    }

    /**
     * Check if campaign can be paused
     */
    public function canPause(): bool
    {
        return $this === self::RUNNING;
    }

    /**
     * Check if campaign is active (running or scheduled)
     */
    public function isActive(): bool
    {
        return in_array($this, [self::RUNNING, self::SCHEDULED]);
    }
}
