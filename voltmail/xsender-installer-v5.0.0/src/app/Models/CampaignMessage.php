<?php

namespace App\Models;

use App\Enums\Campaign\CampaignChannel;
use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CampaignMessage extends Model
{
    use HasFactory, Filterable;

    protected $fillable = [
        'uid',
        'campaign_id',
        'channel',
        'gateway_id',
        'android_session_id',
        'subject',
        'from_name',
        'reply_to',
        'content',
        'template_id',
        'attachments',
        'personalization_vars',
        'is_active',
        'meta_data',
    ];

    protected $casts = [
        'channel' => CampaignChannel::class,
        'attachments' => 'array',
        'personalization_vars' => 'array',
        'meta_data' => 'array',
        'is_active' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function ($message) {
            $message->uid = str_unique();
        });
    }

    /**
     * Display name for whichever sender (gateway OR android session) is bound to this message.
     */
    public function getGatewayDisplayNameAttribute(): ?string
    {
        if ($this->android_session_id) {
            $session = \App\Models\AndroidSession::find($this->android_session_id);
            return $session ? ($session->name . ' (Android)') : 'Android Session #' . $this->android_session_id;
        }
        return $this->gateway?->name;
    }

    // ============ Relationships ============

    /**
     * Get the campaign this message belongs to
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(UnifiedCampaign::class, 'campaign_id');
    }

    /**
     * Get the gateway for this message
     */
    public function gateway(): BelongsTo
    {
        return $this->belongsTo(Gateway::class);
    }

    /**
     * Get the template (if any)
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    /**
     * Get all dispatches for this message
     */
    public function dispatches(): HasMany
    {
        return $this->hasMany(CampaignDispatch::class, 'campaign_message_id');
    }

    /**
     * Get A/B test variants using this message
     */
    public function abVariants(): HasMany
    {
        return $this->hasMany(CampaignAbVariant::class, 'campaign_message_id');
    }

    /**
     * Get content analysis for this message
     */
    public function contentAnalysis(): HasOne
    {
        return $this->hasOne(ContentAnalysis::class, 'campaign_message_id');
    }

    // ============ Scopes ============

    /**
     * Scope to active messages
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to messages by channel
     */
    public function scopeForChannel(Builder $query, string $channel): Builder
    {
        return $query->where('channel', $channel);
    }

    /**
     * Scope to SMS messages
     */
    public function scopeSms(Builder $query): Builder
    {
        return $query->where('channel', CampaignChannel::SMS);
    }

    /**
     * Scope to Email messages
     */
    public function scopeEmail(Builder $query): Builder
    {
        return $query->where('channel', CampaignChannel::EMAIL);
    }

    /**
     * Scope to WhatsApp messages
     */
    public function scopeWhatsapp(Builder $query): Builder
    {
        return $query->where('channel', CampaignChannel::WHATSAPP);
    }

    // ============ Accessors & Helpers ============

    /**
     * Check if this is an SMS message
     */
    public function isSms(): bool
    {
        return $this->channel === CampaignChannel::SMS;
    }

    /**
     * Check if this is an Email message
     */
    public function isEmail(): bool
    {
        return $this->channel === CampaignChannel::EMAIL;
    }

    /**
     * Check if this is a WhatsApp message
     */
    public function isWhatsapp(): bool
    {
        return $this->channel === CampaignChannel::WHATSAPP;
    }

    /**
     * Get personalized content for a contact
     */
    public function getPersonalizedContent(Contact $contact): string
    {
        $content = $this->content;

        // Get all personalization variables
        $vars = $this->personalization_vars ?? [];

        // Add default variables
        $replacements = [
            '{{first_name}}' => $contact->first_name ?? '',
            '{{last_name}}' => $contact->last_name ?? '',
            '{{full_name}}' => $contact->full_name ?? '',
            '{{email}}' => $contact->email_contact ?? '',
            '{{phone}}' => $contact->sms_contact ?? $contact->whatsapp_contact ?? '',
            '{{whatsapp}}' => $contact->whatsapp_contact ?? '',
        ];

        // Add custom metadata variables
        if ($contact->meta_data) {
            $metaData = is_string($contact->meta_data)
                ? json_decode($contact->meta_data, true)
                : (array) $contact->meta_data;

            foreach ($metaData as $key => $value) {
                $varValue = is_array($value) && isset($value['value']) ? $value['value'] : $value;
                $replacements["{{$key}}"] = $varValue ?? '';
            }
        }

        // Add any custom variables from message config
        foreach ($vars as $key => $defaultValue) {
            if (!isset($replacements["{{$key}}"])) {
                $replacements["{{$key}}"] = $defaultValue;
            }
        }

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    /**
     * Get personalized subject for email
     */
    public function getPersonalizedSubject(Contact $contact): string
    {
        if (!$this->subject) {
            return '';
        }

        $subject = $this->subject;

        $replacements = [
            '{{first_name}}' => $contact->first_name ?? '',
            '{{last_name}}' => $contact->last_name ?? '',
            '{{full_name}}' => $contact->full_name ?? '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $subject);
    }

    /**
     * Get character count for SMS
     */
    public function getSmsCharacterCount(): int
    {
        return mb_strlen($this->content);
    }

    /**
     * Get estimated SMS segment count
     */
    public function getSmsSegmentCount(): int
    {
        $length = $this->getSmsCharacterCount();

        // GSM-7 encoding: 160 chars per segment, 153 for multi-part
        // Unicode: 70 chars per segment, 67 for multi-part
        $isUnicode = preg_match('/[^\x00-\x7F]/', $this->content);

        if ($isUnicode) {
            return $length <= 70 ? 1 : (int) ceil($length / 67);
        }

        return $length <= 160 ? 1 : (int) ceil($length / 153);
    }

    /**
     * Check if message has attachments
     */
    public function hasAttachments(): bool
    {
        return !empty($this->attachments);
    }

    /**
     * Get attachment count
     */
    public function getAttachmentCount(): int
    {
        return count($this->attachments ?? []);
    }

    /**
     * Get dispatch statistics
     */
    public function getDispatchStats(): array
    {
        return [
            'total' => $this->dispatches()->count(),
            'pending' => $this->dispatches()->where('status', 'pending')->count(),
            'sent' => $this->dispatches()->where('status', 'sent')->count(),
            'delivered' => $this->dispatches()->where('status', 'delivered')->count(),
            'failed' => $this->dispatches()->where('status', 'failed')->count(),
            'opened' => $this->dispatches()->where('status', 'opened')->count(),
            'clicked' => $this->dispatches()->where('status', 'clicked')->count(),
        ];
    }
}
