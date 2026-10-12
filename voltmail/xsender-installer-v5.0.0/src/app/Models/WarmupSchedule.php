<?php

namespace App\Models;

use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarmupSchedule extends Model
{
    use HasFactory, Filterable;

    protected $table = 'warmup_schedules';

    protected $fillable = [
        'uid',
        'user_id',
        'gateway_id',
        'status',
        'daily_limit',
        'rampup_increment',
        'current_day',
        'current_daily_quota',
        'reply_rate_percent',
        'start_date',
        'last_dispatched_at',
        'imap_watermark',
        'settings',
    ];

    protected $casts = [
        'daily_limit' => 'integer',
        'rampup_increment' => 'integer',
        'current_day' => 'integer',
        'current_daily_quota' => 'integer',
        'reply_rate_percent' => 'integer',
        'start_date' => 'date',
        'last_dispatched_at' => 'datetime',
        'imap_watermark' => 'array',
        'settings' => 'array',
    ];

    protected static function booted()
    {
        static::creating(function ($schedule) {
            if (empty($schedule->uid)) {
                $schedule->uid = str_unique();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(Gateway::class);
    }

    public function dispatches(): HasMany
    {
        return $this->hasMany(WarmupDispatch::class, 'warmup_schedule_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Compute target quota for a given day index based on start quota, rampup increment, and daily limit.
     *
     * @param int $dayIndex 1-based index of warmup day
     * @param int $startingQuota Starting daily quota (defaults to 5)
     * @return int Computed quota capped at daily_limit
     * @throws \InvalidArgumentException When negative values are encountered for quotas or configuration
     */
    public function calculateQuotaForDay(int $dayIndex, int $startingQuota = 5): int
    {
        if ($startingQuota < 0) {
            throw new \InvalidArgumentException("Starting quota cannot be negative: {$startingQuota}");
        }

        if ($this->daily_limit !== null && $this->daily_limit < 0) {
            throw new \InvalidArgumentException("WarmupSchedule ID [{$this->id}] has invalid negative daily_limit: {$this->daily_limit}");
        }

        if ($this->rampup_increment !== null && $this->rampup_increment < 0) {
            throw new \InvalidArgumentException("WarmupSchedule ID [{$this->id}] has invalid negative rampup_increment: {$this->rampup_increment}");
        }

        $effectiveDay = max(1, $dayIndex);
        $increment = $this->rampup_increment !== null ? (int) $this->rampup_increment : 2;
        $limit = $this->daily_limit !== null ? (int) $this->daily_limit : 40;

        $calculated = $startingQuota + (($effectiveDay - 1) * $increment);

        return max(0, min($calculated, $limit));
    }
}
