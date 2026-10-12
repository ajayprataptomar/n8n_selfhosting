<?php

namespace App\Enums\System;

use App\Enums\EnumTrait;

enum TemplateApprovalStatusEnum: string
{
    use EnumTrait;

    case PENDING    = 'pending';
    case APPROVED   = 'approved';
    case REJECTED   = 'rejected';

    /**
     * values
     *
     * @return string
     */
    public function values(): string
    {
        return match($this) {
            self::PENDING   => 'Pending',
            self::APPROVED  => 'Approved',
            self::REJECTED  => 'Rejected'
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
            self::PENDING   => 'primary-soft',
            self::APPROVED  => 'success-soft',
            self::REJECTED  => 'danger-soft',
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