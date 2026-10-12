<?php

namespace App\Enums;

use App\Enums\EnumTrait;

enum WithdrawDurationEnum: string
{
    use EnumTrait;

    case MONTH = "month";
    case week  = "week";
    case DAY   = "day";
    case HOUR  = "hour";
}
