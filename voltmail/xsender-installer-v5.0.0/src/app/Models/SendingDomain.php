<?php

namespace App\Models;

use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SendingDomain extends Model
{
    use HasFactory, Filterable;

    protected $fillable = [
        'uid',
        'user_id',
        'domain',
        'dkim_selector',
        'dkim_private_key',
        'dkim_public_key',
        'dkim_dns_record',
        'spf_record',
        'dmarc_record',
        'dkim_verified',
        'spf_verified',
        'dmarc_verified',
        'status',
        'verified_at',
        'dns_checked_at',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
        'dns_checked_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $hidden = [
        'dkim_private_key',
    ];

    protected static function booted()
    {
        static::creating(function ($domain) {
            $domain->uid = str_unique();
        });
    }

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeAdmin(Builder $query): Builder
    {
        return $query->whereNull('user_id');
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    // Helpers

    public function isVerified(): bool
    {
        return $this->dkim_verified === 'yes';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isDkimConfigured(): bool
    {
        return !empty($this->dkim_private_key) && !empty($this->dkim_public_key);
    }

    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            'active' => 'success',
            'inactive' => 'danger',
            'pending' => 'warning',
            default => 'secondary',
        };
    }

    /**
     * Find a sending domain that matches the given email address.
     */
    public static function findForEmail(string $emailAddress, ?int $userId = null): ?self
    {
        $domain = substr($emailAddress, strpos($emailAddress, '@') + 1);

        $query = static::where('domain', $domain)
            ->where('status', 'active')
            ->where('dkim_verified', 'yes');

        if ($userId) {
            // Check user-specific first, then fall back to admin (global)
            $userDomain = (clone $query)->where('user_id', $userId)->first();
            if ($userDomain) return $userDomain;
        }

        return $query->whereNull('user_id')->first();
    }
}
