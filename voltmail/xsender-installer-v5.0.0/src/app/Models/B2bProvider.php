<?php

namespace App\Models;

use App\Enums\System\B2bProviderTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class B2bProvider extends Model
{
    protected $fillable = [
        'uid', 'user_id', 'type', 'name', 'meta_data',
        'is_active', 'last_synced_at', 'total_lookups',
        'monthly_lookups', 'last_monthly_reset', 'status',
    ];

    protected $casts = [
        'meta_data'          => 'object',
        'is_active'          => 'boolean',
        'last_synced_at'     => 'datetime',
        'last_monthly_reset' => 'date',
        'type'               => B2bProviderTypeEnum::class,
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            $model->uid = $model->uid ?: str_unique();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function searchLogs(): HasMany
    {
        return $this->hasMany(B2bSearchLog::class, 'provider_id');
    }

    public function getCredential(string $key): ?string
    {
        $meta = is_object($this->meta_data) ? (array) $this->meta_data : json_decode($this->meta_data, true);
        return $meta[$key] ?? null;
    }

    public function incrementLookups(int $count = 1): void
    {
        $this->increment('total_lookups', $count);
        $this->increment('monthly_lookups', $count);
    }

    public function resetMonthlyIfNeeded(): void
    {
        $now = now()->startOfMonth();
        if (!$this->last_monthly_reset || $this->last_monthly_reset->lt($now)) {
            $this->update([
                'monthly_lookups'    => 0,
                'last_monthly_reset' => $now,
            ]);
        }
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')->where('is_active', true);
    }

    public function scopeForUser($query, ?int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
