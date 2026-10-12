<?php

namespace App\Enums;

use App\Enums\EnumTrait;

enum DefaultTemplateSlug: string
{
    use EnumTrait;
    
    case TEST_MAIL              = "TEST_MAIL";
    case PASSWORD_RESET         = "PASSWORD_RESET";
    case GLOBAL_TEMPLATE        = "GLOBAL_TEMPLATE";
    case PAYMENT_CONFIRMED      = "PAYMENT_CONFIRMED";
    case REGISTRATION_VERIFY    = "REGISTRATION_VERIFY";
    case INSUFFICIENT_CREDIT    = "INSUFFICIENT_CREDIT";
    case ADMIN_PASSWORD_RESET   = "ADMIN_PASSWORD_RESET";
    case SUPPORT_TICKET_REPLY   = "SUPPORT_TICKET_REPLY";
    case PASSWORD_RESET_CONFIRM  = "PASSWORD_RESET_CONFIRM";
    case RENEWAL_REMINDER        = "RENEWAL_REMINDER";
    case RENEWAL_SUCCESS         = "RENEWAL_SUCCESS";
    case RENEWAL_FAILED          = "RENEWAL_FAILED";
}
