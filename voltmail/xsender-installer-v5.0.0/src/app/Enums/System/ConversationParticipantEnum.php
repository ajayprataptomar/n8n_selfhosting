<?php

namespace App\Enums\System;

use App\Enums\EnumTrait;

enum ConversationParticipantEnum: string
{
    use EnumTrait;

    case SENDER     = 'sender';
    case RECEIVER   = 'receiver';

    /**
     * values
     *
     * @return string
     */
    public function values(): string
    {
        return match($this) {
            self::SENDER   => 'Sender',
            self::RECEIVER => 'Receiver',
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
            self::SENDER    => 'primary',
            self::RECEIVER  => 'secondary',
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