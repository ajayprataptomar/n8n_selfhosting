<?php

namespace App\Enums\System;

use App\Enums\EnumTrait;

enum ConversationMessageStatusEnum: string
{
    use EnumTrait;

    case PENDING    = 'pending';
    case SENT       = 'sent';
    case DELIVERED  = 'delivered';
    case READ       = 'read';
    case FAILED     = 'failed';

    /**
     * values
     *
     * @return string
     */
    public function values(): string
    {
        return match($this) {
            self::PENDING       => 'Pending',
            self::SENT          => 'Sent',
            self::DELIVERED     => 'Delivered',
            self::READ          => 'Read',
            self::FAILED        => 'Failed',
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
            self::PENDING   => 'primary',
            self::SENT      => 'warning',
            self::DELIVERED => 'success',
            self::READ      => 'info',
            self::FAILED    => 'danger',
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