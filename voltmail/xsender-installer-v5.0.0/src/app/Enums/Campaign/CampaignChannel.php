<?php

namespace App\Enums\Campaign;

use App\Enums\EnumTrait;

enum CampaignChannel: string
{
    use EnumTrait;

    case SMS = 'sms';
    case EMAIL = 'email';
    case WHATSAPP = 'whatsapp';

    /**
     * Get human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::SMS => translate('SMS'),
            self::EMAIL => translate('Email'),
            self::WHATSAPP => translate('WhatsApp'),
        };
    }

    /**
     * Get icon class
     */
    public function icon(): string
    {
        return match ($this) {
            self::SMS => 'ri-chat-1-line',
            self::EMAIL => 'ri-mail-line',
            self::WHATSAPP => 'ri-whatsapp-line',
        };
    }

    /**
     * Get color class for badges
     */
    public function colorClass(): string
    {
        return match ($this) {
            self::SMS => 'text-primary',
            self::EMAIL => 'text-info',
            self::WHATSAPP => 'text-success',
        };
    }

    /**
     * Get background color class
     */
    public function bgClass(): string
    {
        return match ($this) {
            self::SMS => 'bg-soft-primary',
            self::EMAIL => 'bg-soft-info',
            self::WHATSAPP => 'bg-soft-success',
        };
    }

    /**
     * Get the contact field name for this channel
     */
    public function contactField(): string
    {
        return match ($this) {
            self::SMS => 'sms_contact',
            self::EMAIL => 'email_contact',
            self::WHATSAPP => 'whatsapp_contact',
        };
    }

    /**
     * Get all channels as array for form options
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(function ($case) {
            return [$case->value => $case->label()];
        })->toArray();
    }
}
