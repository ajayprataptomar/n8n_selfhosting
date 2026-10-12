<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadGenerationSetting extends Model
{
    protected $fillable = [
        'user_id',
        'google_maps_api_key',
        'api_docs_url',
        'daily_scrape_limit',
        'monthly_scrape_limit',
        'scrapes_today',
        'scrapes_this_month',
        'last_daily_reset',
        'last_monthly_reset',
    ];

    protected $casts = [
        'daily_scrape_limit'   => 'integer',
        'monthly_scrape_limit' => 'integer',
        'scrapes_today'        => 'integer',
        'scrapes_this_month'   => 'integer',
        'last_daily_reset'     => 'date',
        'last_monthly_reset'   => 'date',
    ];

    protected $hidden = [
        'google_maps_api_key',
    ];

    /**
     * User relationship
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get or create settings for a user
     */
    public static function getForUser(?int $userId = null): self
    {
        $query = self::query();

        if ($userId) {
            $query->where('user_id', $userId);
        } else {
            $query->whereNull('user_id');
        }

        $settings = $query->first();

        if (!$settings) {
            $settings = self::create([
                'user_id'              => $userId,
                'daily_scrape_limit'   => 100,
                'monthly_scrape_limit' => 2000,
            ]);
        }

        return $settings;
    }

    /**
     * Reset daily counters if needed
     */
    public function resetDailyIfNeeded(): void
    {
        if (!$this->last_daily_reset || !$this->last_daily_reset->isToday()) {
            $this->update([
                'scrapes_today'    => 0,
                'last_daily_reset' => now()->toDateString(),
            ]);
        }
    }

    /**
     * Reset monthly counters if needed
     */
    public function resetMonthlyIfNeeded(): void
    {
        if (!$this->last_monthly_reset || !$this->last_monthly_reset->isCurrentMonth()) {
            $this->update([
                'scrapes_this_month'  => 0,
                'last_monthly_reset'  => now()->toDateString(),
            ]);
        }
    }

    /**
     * Check if can scrape more leads today
     */
    public function canScrapeToday(int $count = 1): bool
    {
        $this->resetDailyIfNeeded();
        return ($this->scrapes_today + $count) <= $this->daily_scrape_limit;
    }

    /**
     * Check if can scrape more leads this month
     */
    public function canScrapeThisMonth(int $count = 1): bool
    {
        $this->resetMonthlyIfNeeded();
        return ($this->scrapes_this_month + $count) <= $this->monthly_scrape_limit;
    }

    /**
     * Check if can scrape (both daily and monthly)
     */
    public function canScrape(int $count = 1): bool
    {
        return $this->canScrapeToday($count) && $this->canScrapeThisMonth($count);
    }

    /**
     * Get remaining daily quota
     */
    public function getRemainingDailyAttribute(): int
    {
        $this->resetDailyIfNeeded();
        return max(0, $this->daily_scrape_limit - $this->scrapes_today);
    }

    /**
     * Get remaining monthly quota
     */
    public function getRemainingMonthlyAttribute(): int
    {
        $this->resetMonthlyIfNeeded();
        return max(0, $this->monthly_scrape_limit - $this->scrapes_this_month);
    }

    /**
     * Increment scrape counters
     */
    public function incrementScrapes(int $count = 1): void
    {
        $this->resetDailyIfNeeded();
        $this->resetMonthlyIfNeeded();

        $this->increment('scrapes_today', $count);
        $this->increment('scrapes_this_month', $count);
    }

    /**
     * Check if Google Maps API key is configured
     */
    public function hasGoogleMapsKey(): bool
    {
        return !empty($this->google_maps_api_key);
    }
}
