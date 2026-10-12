<?php

namespace App\Models;

use App\Enums\Common\Status;
use App\Enums\MetaApiEndpoints;
use App\Enums\StatusEnum;
use App\Enums\System\ChannelTypeEnum;
use App\Enums\System\Gateway\WhatsAppGatewayTypeEnum;
use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Notifications\Notifiable;

class Gateway extends Model
{
    use HasFactory, Notifiable, Filterable;

    // Health status constants
    const HEALTH_HEALTHY = 'healthy';
    const HEALTH_DEGRADED = 'degraded';
    const HEALTH_UNHEALTHY = 'unhealthy';
    const HEALTH_UNKNOWN = 'unknown';

    protected $fillable = [
        'user_id', 'uid', 'channel', 'type', 'name', 'address', 'meta_data', 'status',
        'is_default', 'per_message_min_delay', 'per_message_max_delay', 'delay_after_count',
        'bulk_contact_limit', 'delay_after_duration', 'reset_after_count',
        // Embedded signup fields (existing)
        'payload', 'api_version', 'last_sync_at', 'setup_method',
        // New Meta Enterprise fields
        'meta_configuration_id', 'onboarding_id', 'waba_id', 'phone_number_id', 'business_id',
        'verification_status', 'quality_rating', 'messaging_limit_tier', 'account_mode',
        'token_expires_at', 'token_refreshed_at', 'health_status', 'last_health_check_at',
        'health_check_history', 'consecutive_failures', 'webhook_subscribed', 'webhook_subscribed_at',
    ];

    protected $casts = [
        'channel' => ChannelTypeEnum::class,
        'meta_data' => 'array',
        'payload' => 'array',
        'health_check_history' => 'array',
        'status' => Status::class,
        'is_default' => 'boolean',
        'webhook_subscribed' => 'boolean',
        'per_message_delay' => 'float',
        'delay_after_count' => 'integer',
        'delay_after_duration' => 'float',
        'reset_after_count' => 'integer',
        'consecutive_failures' => 'integer',
        'last_sync_at' => 'datetime',
        'token_expires_at' => 'datetime',
        'token_refreshed_at' => 'datetime',
        'last_health_check_at' => 'datetime',
        'webhook_subscribed_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($gateway) {
            $gateway->uid = str_unique();
        });
    }

    public function scopeActive($query): Builder
    {
        return $query->where('status', Status::ACTIVE->value);
    }

    public function scopeInactive($query): Builder
    {
        return $query->where('status', Status::INACTIVE->value);
    }

    public function scopeMail($query)
    {
        return $query->whereNotNull('id');
    }
    public function scopeSms($query)
    {
        return $query->whereNotNull('id');
    }


    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function templates(): HasMany
    {
        return $this->hasMany(Template::class, "cloud_id");
    }

    /**
     * Get the Meta configuration (for Cloud API gateways)
     */
    public function metaConfiguration(): BelongsTo
    {
        return $this->belongsTo(MetaConfiguration::class);
    }

    /**
     * Get the onboarding record
     */
    public function onboarding(): BelongsTo
    {
        return $this->belongsTo(WhatsappClientOnboarding::class, 'onboarding_id');
    }

    /**
     * Scope for WhatsApp gateways
     */
    public function scopeWhatsapp(Builder $query): Builder
    {
        return $query->where('channel', ChannelTypeEnum::WHATSAPP->value);
    }

    /**
     * Scope for Cloud API gateways
     */
    public function scopeCloudApi(Builder $query): Builder
    {
        return $query->where('channel', ChannelTypeEnum::WHATSAPP->value)
            ->where('type', WhatsAppGatewayTypeEnum::CLOUD->value);
    }

    /**
     * Scope for Node-based gateways
     */
    public function scopeNodeBased(Builder $query): Builder
    {
        return $query->where('channel', ChannelTypeEnum::WHATSAPP->value)
            ->where('type', WhatsAppGatewayTypeEnum::NODE->value);
    }

    /**
     * Scope for healthy gateways
     */
    public function scopeHealthy(Builder $query): Builder
    {
        return $query->where('health_status', self::HEALTH_HEALTHY);
    }

    /**
     * Check if this is a Cloud API gateway
     */
    public function isCloudApi(): bool
    {
        return $this->type === WhatsAppGatewayTypeEnum::CLOUD->value;
    }

    /**
     * Check if this is a Node-based gateway
     */
    public function isNodeBased(): bool
    {
        return $this->type === WhatsAppGatewayTypeEnum::NODE->value;
    }

    /**
     * Check if gateway is healthy
     */
    public function isHealthy(): bool
    {
        return $this->health_status === self::HEALTH_HEALTHY;
    }

    /**
     * Get access token (works for both setup methods)
     */
    public function getAccessToken(): ?string
    {
        // For Cloud API with Meta Configuration
        if ($this->isCloudApi() && $this->metaConfiguration) {
            $token = $this->metaConfiguration->getActiveToken();
            if ($token) {
                return $token;
            }
        }

        // Fallback to meta_data token (legacy/manual setup)
        return $this->meta_data['user_access_token'] ?? null;
    }

    /**
     * Get phone number ID
     */
    public function getPhoneNumberId(): ?string
    {
        return $this->phone_number_id ?? ($this->meta_data['phone_number_id'] ?? null);
    }

    /**
     * Get WABA ID
     */
    public function getWabaId(): ?string
    {
        return $this->waba_id ?? ($this->meta_data['whatsapp_business_account_id'] ?? null);
    }

    /**
     * Get API version
     */
    public function getApiVersion(): string
    {
        if ($this->metaConfiguration) {
            return $this->metaConfiguration->api_version;
        }

        return $this->api_version ?? MetaApiEndpoints::CURRENT_VERSION;
    }

    /**
     * Check if token is expiring soon
     */
    public function isTokenExpiringSoon(int $hoursThreshold = 24): bool
    {
        if (!$this->token_expires_at) {
            return false;
        }

        return $this->token_expires_at->subHours($hoursThreshold)->isPast();
    }

    /**
     * Update health status
     */
    public function updateHealthStatus(string $status, ?string $errorMessage = null): self
    {
        $history = $this->health_check_history ?? [];
        $history[] = [
            'status' => $status,
            'error' => $errorMessage,
            'checked_at' => now()->toISOString(),
        ];

        // Keep only last 10 health checks
        $history = array_slice($history, -10);

        $this->update([
            'health_status' => $status,
            'last_health_check_at' => now(),
            'health_check_history' => $history,
            'consecutive_failures' => $status === self::HEALTH_HEALTHY ? 0 : $this->consecutive_failures + 1,
        ]);

        return $this;
    }

    /**
     * Get health badge class
     */
    public function getHealthBadgeClass(): string
    {
        return match ($this->health_status) {
            self::HEALTH_HEALTHY => 'success',
            self::HEALTH_DEGRADED => 'warning',
            self::HEALTH_UNHEALTHY => 'danger',
            default => 'secondary',
        };
    }

    /**
     * Get quality badge class
     */
    public function getQualityBadgeClass(): string
    {
        return match ($this->quality_rating) {
            'GREEN' => 'success',
            'YELLOW' => 'warning',
            'RED' => 'danger',
            default => 'secondary',
        };
    }

    /**
     * Get setup method display
     */
    public function getSetupMethodDisplay(): string
    {
        if ($this->isNodeBased()) {
            return translate('QR Code (Node)');
        }

        return match ($this->setup_method) {
            'embedded' => translate('Embedded Signup'),
            'manual' => translate('Manual Configuration'),
            default => translate('Unknown'),
        };
    }
}