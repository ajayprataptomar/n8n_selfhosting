<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class WhatsappClientOnboarding extends Model
{
    use HasFactory, SoftDeletes;

    // Onboarding status constants
    const STATUS_INITIATED = 'initiated';
    const STATUS_OAUTH_COMPLETED = 'oauth_completed';
    const STATUS_PENDING_VERIFICATION = 'pending_verification';
    const STATUS_PHONE_REGISTERED = 'phone_registered';
    const STATUS_WEBHOOK_SUBSCRIBED = 'webhook_subscribed';
    const STATUS_VERIFIED = 'verified';
    const STATUS_REJECTED = 'rejected';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    // Quality rating constants
    const QUALITY_GREEN = 'GREEN';
    const QUALITY_YELLOW = 'YELLOW';
    const QUALITY_RED = 'RED';

    // Messaging tiers
    const TIER_1K = 'TIER_1K';
    const TIER_10K = 'TIER_10K';
    const TIER_100K = 'TIER_100K';
    const TIER_UNLIMITED = 'UNLIMITED';

    protected $fillable = [
        'uid',
        'user_id',
        'gateway_id',
        'meta_configuration_id',
        'onboarding_status',
        'waba_id',
        'waba_name',
        'waba_currency',
        'waba_timezone_id',
        'message_template_namespace',
        'phone_number_id',
        'phone_number',
        'display_name',
        'verified_name',
        'code_verification_status',
        'quality_rating',
        'messaging_limit_tier',
        'business_verification_status',
        'account_review_status',
        'user_access_token',
        'user_token_expires_at',
        'permissions_granted',
        'oauth_response',
        'error_log',
        'last_error_message',
        'retry_count',
        'initiated_at',
        'oauth_completed_at',
        'phone_registered_at',
        'webhook_subscribed_at',
        'verified_at',
        'completed_at',
        'last_health_check_at',
    ];

    protected $casts = [
        'permissions_granted' => 'array',
        'oauth_response' => 'array',
        'error_log' => 'array',
        'user_token_expires_at' => 'datetime',
        'initiated_at' => 'datetime',
        'oauth_completed_at' => 'datetime',
        'phone_registered_at' => 'datetime',
        'webhook_subscribed_at' => 'datetime',
        'verified_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_health_check_at' => 'datetime',
    ];

    protected $hidden = [
        'user_access_token',
    ];

    /**
     * Boot the model
     */
    protected static function booted(): void
    {
        static::creating(function ($model) {
            $model->uid = $model->uid ?? str_unique();
            $model->initiated_at = $model->initiated_at ?? now();
        });
    }

    /**
     * Encrypt user access token before saving
     */
    public function setUserAccessTokenAttribute($value): void
    {
        $this->attributes['user_access_token'] = $value ? Crypt::encryptString($value) : null;
    }

    /**
     * Decrypt user access token when retrieving
     */
    public function getUserAccessTokenAttribute($value): ?string
    {
        try {
            return $value ? Crypt::decryptString($value) : null;
        } catch (\Exception $e) {
            return $value;
        }
    }

    /**
     * Relationships
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(Gateway::class);
    }

    public function metaConfiguration(): BelongsTo
    {
        return $this->belongsTo(MetaConfiguration::class);
    }

    /**
     * Scopes
     */
    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('onboarding_status', $status);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('onboarding_status', [
            self::STATUS_INITIATED,
            self::STATUS_OAUTH_COMPLETED,
            self::STATUS_PENDING_VERIFICATION,
            self::STATUS_PHONE_REGISTERED,
            self::STATUS_WEBHOOK_SUBSCRIBED,
        ]);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('onboarding_status', self::STATUS_COMPLETED);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->whereIn('onboarding_status', [
            self::STATUS_FAILED,
            self::STATUS_REJECTED,
        ]);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Status helpers
     */
    public function isCompleted(): bool
    {
        return $this->onboarding_status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return in_array($this->onboarding_status, [self::STATUS_FAILED, self::STATUS_REJECTED]);
    }

    public function isPending(): bool
    {
        return in_array($this->onboarding_status, [
            self::STATUS_INITIATED,
            self::STATUS_OAUTH_COMPLETED,
            self::STATUS_PENDING_VERIFICATION,
            self::STATUS_PHONE_REGISTERED,
            self::STATUS_WEBHOOK_SUBSCRIBED,
        ]);
    }

    public function canRetry(): bool
    {
        return $this->isFailed() && $this->retry_count < 3;
    }

    /**
     * Status transition methods
     */
    public function markOAuthCompleted(array $oauthData): self
    {
        $this->update([
            'onboarding_status' => self::STATUS_OAUTH_COMPLETED,
            'oauth_response' => $oauthData,
            'oauth_completed_at' => now(),
        ]);

        return $this;
    }

    public function markPhoneRegistered(array $phoneData): self
    {
        $this->update([
            'onboarding_status' => self::STATUS_PHONE_REGISTERED,
            'phone_number_id' => $phoneData['phone_number_id'] ?? null,
            'phone_number' => $phoneData['phone_number'] ?? null,
            'display_name' => $phoneData['display_name'] ?? null,
            'verified_name' => $phoneData['verified_name'] ?? null,
            'phone_registered_at' => now(),
        ]);

        return $this;
    }

    public function markWebhookSubscribed(): self
    {
        $this->update([
            'onboarding_status' => self::STATUS_WEBHOOK_SUBSCRIBED,
            'webhook_subscribed_at' => now(),
        ]);

        return $this;
    }

    public function markVerified(): self
    {
        $this->update([
            'onboarding_status' => self::STATUS_VERIFIED,
            'verified_at' => now(),
        ]);

        return $this;
    }

    public function markCompleted(int $gatewayId): self
    {
        $this->update([
            'onboarding_status' => self::STATUS_COMPLETED,
            'gateway_id' => $gatewayId,
            'completed_at' => now(),
        ]);

        return $this;
    }

    public function markFailed(string $errorMessage, ?array $errorDetails = null): self
    {
        $errorLog = $this->error_log ?? [];
        $errorLog[] = [
            'message' => $errorMessage,
            'details' => $errorDetails,
            'timestamp' => now()->toISOString(),
        ];

        $this->update([
            'onboarding_status' => self::STATUS_FAILED,
            'last_error_message' => $errorMessage,
            'error_log' => $errorLog,
            'retry_count' => $this->retry_count + 1,
        ]);

        return $this;
    }

    /**
     * Update WABA info from API response
     */
    public function updateWabaInfo(array $wabaData): self
    {
        $this->update([
            'waba_id' => $wabaData['id'] ?? $this->waba_id,
            'waba_name' => $wabaData['name'] ?? $this->waba_name,
            'waba_currency' => $wabaData['currency'] ?? $this->waba_currency,
            'waba_timezone_id' => $wabaData['timezone_id'] ?? $this->waba_timezone_id,
            'message_template_namespace' => $wabaData['message_template_namespace'] ?? $this->message_template_namespace,
            'account_review_status' => $wabaData['account_review_status'] ?? $this->account_review_status,
        ]);

        return $this;
    }

    /**
     * Update phone info from API response
     */
    public function updatePhoneInfo(array $phoneData): self
    {
        $this->update([
            'phone_number_id' => $phoneData['id'] ?? $this->phone_number_id,
            'phone_number' => $phoneData['display_phone_number'] ?? $this->phone_number,
            'verified_name' => $phoneData['verified_name'] ?? $this->verified_name,
            'quality_rating' => $phoneData['quality_rating'] ?? $this->quality_rating,
            'code_verification_status' => $phoneData['code_verification_status'] ?? $this->code_verification_status,
        ]);

        return $this;
    }

    /**
     * Get quality rating badge class
     */
    public function getQualityBadgeClass(): string
    {
        return match ($this->quality_rating) {
            self::QUALITY_GREEN => 'success',
            self::QUALITY_YELLOW => 'warning',
            self::QUALITY_RED => 'danger',
            default => 'secondary',
        };
    }

    /**
     * Get messaging tier display text
     */
    public function getMessagingTierDisplay(): string
    {
        return match ($this->messaging_limit_tier) {
            self::TIER_1K => '1,000 conversations/day',
            self::TIER_10K => '10,000 conversations/day',
            self::TIER_100K => '100,000 conversations/day',
            self::TIER_UNLIMITED => 'Unlimited',
            default => 'Unknown',
        };
    }

    /**
     * Get status badge class
     */
    public function getStatusBadgeClass(): string
    {
        return match ($this->onboarding_status) {
            self::STATUS_COMPLETED => 'success',
            self::STATUS_VERIFIED => 'info',
            self::STATUS_FAILED, self::STATUS_REJECTED => 'danger',
            default => 'warning',
        };
    }

    /**
     * Get progress percentage
     */
    public function getProgressPercentage(): int
    {
        return match ($this->onboarding_status) {
            self::STATUS_INITIATED => 10,
            self::STATUS_OAUTH_COMPLETED => 30,
            self::STATUS_PENDING_VERIFICATION => 50,
            self::STATUS_PHONE_REGISTERED => 70,
            self::STATUS_WEBHOOK_SUBSCRIBED => 85,
            self::STATUS_VERIFIED => 95,
            self::STATUS_COMPLETED => 100,
            self::STATUS_FAILED, self::STATUS_REJECTED => 0,
            default => 0,
        };
    }
}
