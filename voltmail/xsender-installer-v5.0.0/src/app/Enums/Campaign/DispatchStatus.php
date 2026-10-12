<?php

namespace App\Enums\Campaign;

use App\Enums\EnumTrait;

enum DispatchStatus: string
{
    use EnumTrait;

    case PENDING = 'pending';
    case QUEUED = 'queued';
    case PROCESSING = 'processing';
    case SENT = 'sent';
    case DELIVERED = 'delivered';
    case FAILED = 'failed';
    case BOUNCED = 'bounced';
    case OPENED = 'opened';
    case CLICKED = 'clicked';
    case REPLIED = 'replied';

    /**
     * Get human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => translate('Pending'),
            self::QUEUED => translate('Queued'),
            self::PROCESSING => translate('Processing'),
            self::SENT => translate('Sent'),
            self::DELIVERED => translate('Delivered'),
            self::FAILED => translate('Failed'),
            self::BOUNCED => translate('Bounced'),
            self::OPENED => translate('Opened'),
            self::CLICKED => translate('Clicked'),
            self::REPLIED => translate('Replied'),
        };
    }

    /**
     * Get badge class
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::PENDING => 'badge--secondary',
            self::QUEUED => 'badge--info',
            self::PROCESSING => 'badge--primary',
            self::SENT => 'badge--info',
            self::DELIVERED => 'badge--success',
            self::FAILED => 'badge--danger',
            self::BOUNCED => 'badge--warning',
            self::OPENED => 'badge--success',
            self::CLICKED => 'badge--success',
            self::REPLIED => 'badge--success',
        };
    }

    /**
     * Check if status is a success state
     */
    public function isSuccess(): bool
    {
        return in_array($this, [
            self::SENT,
            self::DELIVERED,
            self::OPENED,
            self::CLICKED,
            self::REPLIED,
        ]);
    }

    /**
     * Check if status is a failure state
     */
    public function isFailure(): bool
    {
        return in_array($this, [self::FAILED, self::BOUNCED]);
    }

    /**
     * Check if status is in progress
     */
    public function isInProgress(): bool
    {
        return in_array($this, [self::PENDING, self::QUEUED, self::PROCESSING]);
    }

    /**
     * Check if dispatch can be retried
     */
    public function canRetry(): bool
    {
        return in_array($this, [self::FAILED, self::BOUNCED]);
    }

    /**
     * Get all success statuses
     */
    public static function successStatuses(): array
    {
        return [
            self::SENT,
            self::DELIVERED,
            self::OPENED,
            self::CLICKED,
            self::REPLIED,
        ];
    }

    /**
     * Get all failure statuses
     */
    public static function failureStatuses(): array
    {
        return [self::FAILED, self::BOUNCED];
    }
}
