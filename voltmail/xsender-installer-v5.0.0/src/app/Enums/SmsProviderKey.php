<?php

namespace App\Enums;

use App\Enums\EnumTrait;

enum SmsProviderKey: string
{
    use EnumTrait;

    case CUSTOM         = "custom";
    case NEXMO          = "nexmo";
    case TWILIO         = "twilio";
    case MESSAGEBIRD    = "messagebird";
    case TEXTMAGIC      = "textmagic";
    case CLICKATELL     = "clickatell";
    case INFOBIP        = "infobip";
    case SMSBROADCAST   = "smsbroadcast";
    case MIMSMS         = "mimsms";
    case AJURASMS       = "ajurasms";
    case MSG91          = "msg91";
}
