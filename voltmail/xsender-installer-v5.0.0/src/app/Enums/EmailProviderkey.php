<?php

namespace App\Enums;

use App\Enums\EnumTrait;

enum EmailProviderkey: string
{
    use EnumTrait;

    case SMTP       = "smtp";
    case SENDGRID   = "sendgrid";
    case AWS        = "aws";
    case MAILJET    = "mailjet";
    case MAILGUN    = "mailgun";
}
