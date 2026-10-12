<?php

namespace App\Enums;

use App\Enums\EnumTrait;

enum WithdrawLogEnum: string
{
    use EnumTrait;

    case PENDING    = "pending";
    case APPROVED   = "approved";
    case REJECTED   = "rejected";
}
