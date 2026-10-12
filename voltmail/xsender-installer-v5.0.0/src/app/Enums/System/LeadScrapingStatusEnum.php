<?php

namespace App\Enums\System;

use App\Enums\EnumTrait;

enum LeadScrapingStatusEnum: string
{
    use EnumTrait;

    case PENDING    = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED  = 'completed';
    case FAILED     = 'failed';
    case CANCELLED  = 'cancelled';

    /**
     * Get human-readable label
     *
     * @return string
     */
    public function label(): string
    {
        return match($this) {
            self::PENDING    => 'Pending',
            self::PROCESSING => 'Processing',
            self::COMPLETED  => 'Completed',
            self::FAILED     => 'Failed',
            self::CANCELLED  => 'Cancelled',
        };
    }

    /**
     * Display badge HTML
     *
     * @return void
     */
    public function badge(): void
    {
        $color = match($this) {
            self::PENDING    => 'primary-soft',
            self::PROCESSING => 'warning-soft',
            self::COMPLETED  => 'success-soft',
            self::FAILED     => 'danger-soft',
            self::CANCELLED  => 'secondary-soft',
        };

        echo "<span class='i-badge dot pill {$color}'>{$this->label()}</span>";
    }

    /**
     * Get all values
     *
     * @return array
     */
    public static function getValues(): array
    {
        return array_map(fn($case) => $case->value, self::cases());
    }
}
