<?php

namespace App\Models;

use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingDomain extends Model
{
    use Filterable;

    protected $fillable = [
        'uid',
        'user_id',
        'domain',
        'status',
        'verified_at',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->uid)) {
                $model->uid = str_unique();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeAdmin($query)
    {
        return $query->whereNull('user_id');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            'active' => 'success',
            'pending' => 'warning',
            'inactive' => 'danger',
            default => 'secondary',
        };
    }

    /**
     * Get the tracking base URL for a user.
     * Returns the user's verified tracking domain or falls back to app URL.
     */
    public static function getTrackingBaseUrl(?int $userId = null): string
    {
        $domain = null;

        if ($userId) {
            $domain = static::where('user_id', $userId)
                ->where('status', 'active')
                ->first();
        }

        if (!$domain) {
            $domain = static::whereNull('user_id')
                ->where('status', 'active')
                ->first();
        }

        if ($domain) {
            // v4.9.1 fix — the previous `request()->isSecure()` check produced
            // http:// URLs when getTrackingBaseUrl() was called from a queue job
            // or dispatch context (no request bound). Emails then contained
            // http:// tracking links even though the app was HTTPS-only, which
            // broke Cloudflare-proxied tracking domains with mixed-content
            // redirects. Trust the app URL's scheme first, then site_settings
            // FORCE_SSL, and only fall back to request() when neither exists.
            $scheme = static::detectPreferredScheme();
            return "{$scheme}://{$domain->domain}";
        }

        return config('app.url');
    }

    /**
     * Determine whether tracking links should use https:// or http://.
     * Priority: config(app.url) scheme → site_settings('force_ssl') →
     * request context → https default (Cloudflare proxied domains always
     * terminate TLS at the edge, so https is nearly always correct).
     */
    protected static function detectPreferredScheme(): string
    {
        $appScheme = parse_url((string) config('app.url'), PHP_URL_SCHEME);
        if ($appScheme === 'https' || $appScheme === 'http') {
            return $appScheme;
        }
        if ((string) site_settings('force_ssl') === '1') {
            return 'https';
        }
        try {
            if (request() && request()->isSecure()) {
                return 'https';
            }
        } catch (\Throwable $e) { /* no request bound */ }
        return 'https'; // Cloudflare-proxied CNAMEs terminate TLS at the edge.
    }

    /**
     * Verify CNAME DNS record points to this app.
     *
     * @return array{verified: bool, expected: string, found: array<string>, error: ?string}
     */
    public function verifyCname(): array
    {
        $appDomain = parse_url(config('app.url'), PHP_URL_HOST) ?: '';
        $expected  = strtolower(rtrim($appDomain, '.'));

        try {
            $records = @dns_get_record($this->domain, DNS_CNAME);

            if ($records === false) {
                return ['verified' => false, 'expected' => $expected, 'found' => [], 'error' => 'DNS lookup failed (server may be blocking outbound DNS).'];
            }

            if (empty($records)) {
                return ['verified' => false, 'expected' => $expected, 'found' => [], 'error' => null];
            }

            $found = [];
            foreach ($records as $record) {
                $target = strtolower(rtrim($record['target'] ?? '', '.'));
                if ($target !== '') {
                    $found[] = $target;
                }
                if (strcasecmp($target, $expected) === 0) {
                    return ['verified' => true, 'expected' => $expected, 'found' => $found, 'error' => null];
                }
            }

            return ['verified' => false, 'expected' => $expected, 'found' => $found, 'error' => null];
        } catch (\Exception $e) {
            return ['verified' => false, 'expected' => $expected, 'found' => [], 'error' => $e->getMessage()];
        }
    }
}
