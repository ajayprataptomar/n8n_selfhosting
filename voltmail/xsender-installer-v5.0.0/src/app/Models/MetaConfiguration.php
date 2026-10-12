<?php

namespace App\Models;

use App\Enums\Common\Status;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class MetaConfiguration extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uid',
        'name',
        'config_id',
        'app_id',
        'app_secret',
        'system_user_id',
        'system_user_token',
        'system_user_token_expires_at',
        'business_manager_id',
        'tech_provider_id',
        'solution_id',
        'webhook_verify_token',
        'webhook_callback_url',
        'permissions',
        'allowed_features',
        'api_version',
        'environment',
        'status',
        'is_default',
        'rate_limits',
        'meta_response',
        'setup_instructions',
        'last_verified_at',
    ];

    protected $casts = [
        'permissions' => 'array',
        'allowed_features' => 'array',
        'rate_limits' => 'array',
        'meta_response' => 'array',
        'is_default' => 'boolean',
        'system_user_token_expires_at' => 'datetime',
        'last_verified_at' => 'datetime',
    ];

    protected $hidden = [
        'app_secret',
        'system_user_token',
    ];

    /**
     * Boot the model
     */
    protected static function booted(): void
    {
        static::creating(function ($model) {
            $model->uid = $model->uid ?? str_unique();
            $model->webhook_verify_token = $model->webhook_verify_token ?? bin2hex(random_bytes(16));
        });

        // Ensure only one default configuration
        static::saving(function ($model) {
            if ($model->is_default) {
                static::where('id', '!=', $model->id ?? 0)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        });
    }

    /**
     * Encrypt app secret before saving
     */
    public function setAppSecretAttribute($value): void
    {
        $this->attributes['app_secret'] = $value ? Crypt::encryptString($value) : null;
    }

    /**
     * Decrypt app secret when retrieving
     */
    public function getAppSecretAttribute($value): ?string
    {
        try {
            return $value ? Crypt::decryptString($value) : null;
        } catch (\Exception $e) {
            return $value;
        }
    }

    /**
     * Encrypt system user token before saving
     */
    public function setSystemUserTokenAttribute($value): void
    {
        $this->attributes['system_user_token'] = $value ? Crypt::encryptString($value) : null;
    }

    /**
     * Decrypt system user token when retrieving
     */
    public function getSystemUserTokenAttribute($value): ?string
    {
        try {
            return $value ? Crypt::decryptString($value) : null;
        } catch (\Exception $e) {
            return $value;
        }
    }

    /**
     * Scope for active configurations
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for production environment
     */
    public function scopeProduction(Builder $query): Builder
    {
        return $query->where('environment', 'production');
    }

    /**
     * Scope for default configuration
     */
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    /**
     * Get the gateways using this configuration
     */
    public function gateways(): HasMany
    {
        return $this->hasMany(Gateway::class, 'meta_configuration_id');
    }

    /**
     * Get the client onboardings using this configuration
     */
    public function clientOnboardings(): HasMany
    {
        return $this->hasMany(WhatsappClientOnboarding::class, 'meta_configuration_id');
    }

    /**
     * Check if system user token is expired or about to expire
     */
    public function isTokenExpiringSoon(int $hoursThreshold = 24): bool
    {
        if (!$this->system_user_token_expires_at) {
            return false;
        }

        return $this->system_user_token_expires_at->subHours($hoursThreshold)->isPast();
    }

    /**
     * Check if system user token is expired
     */
    public function isTokenExpired(): bool
    {
        if (!$this->system_user_token_expires_at) {
            return false;
        }

        return $this->system_user_token_expires_at->isPast();
    }

    /**
     * Get the active access token (System User token preferred)
     */
    public function getActiveToken(): ?string
    {
        if ($this->system_user_token && !$this->isTokenExpired()) {
            return $this->system_user_token;
        }

        return null;
    }

    /**
     * Get OAuth URL for embedded signup
     */
    public function getOAuthUrl(string $redirectUri, array $scopes = [], ?string $state = null): string
    {
        $defaultScopes = ['whatsapp_business_messaging', 'whatsapp_business_management', 'business_management'];
        $scopes = array_merge($defaultScopes, $scopes);

        $params = [
            'client_id' => $this->app_id,
            'redirect_uri' => $redirectUri,
            'state' => $state ?? bin2hex(random_bytes(16)),
            'scope' => implode(',', array_unique($scopes)),
            'response_type' => 'code',
            'extras' => json_encode([
                'feature' => 'whatsapp_embedded_signup',
                'version' => 2,
                'setup' => [
                    'solution' => 'whatsapp',
                    'flow' => 'signup',
                    'config_id' => $this->config_id, // CRITICAL: Meta 2025 requirement
                ],
            ]),
        ];

        return "https://www.facebook.com/{$this->api_version}/dialog/oauth?" . http_build_query($params);
    }

    /**
     * Get the default configuration
     */
    public static function getDefault(): ?self
    {
        return static::active()->default()->first() ?? static::active()->first();
    }

    /**
     * Get webhook callback URL
     */
    public function getWebhookUrl(): string
    {
        return $this->webhook_callback_url ?? route('webhook');
    }

    /**
     * Check if configuration has required Tech Provider fields
     */
    public function isTechProviderReady(): bool
    {
        return !empty($this->config_id)
            && !empty($this->app_id)
            && !empty($this->app_secret);
    }

    /**
     * Check if configuration has System User setup
     */
    public function hasSystemUser(): bool
    {
        return !empty($this->system_user_id) && !empty($this->system_user_token);
    }
}
