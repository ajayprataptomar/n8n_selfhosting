<?php

namespace App\Enums\System;

use App\Enums\EnumTrait;

enum ConversationStatusEnum: string
{
    use EnumTrait;

    case ACTIVE     = 'active';
    case ARCHIEVED  = 'archieved';
    case BLOCKED    = 'blocked';

    /**
     * values
     *
     * @return string
     */
    public function values(): string
    {
        return match($this) {
            self::ACTIVE    => 'Active',
            self::ARCHIEVED => 'Archieved',
            self::BLOCKED   => 'Blocked',
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
            self::ACTIVE    => 'primary',
            self::ARCHIEVED => 'warning',
            self::BLOCKED   => 'danger',
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