<?php

namespace App\Models;

use App\Enums\System\LeadScrapingStatusEnum;
use App\Enums\System\LeadScrapingTypeEnum;
use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeadScrapingJob extends Model
{
    use Filterable;

    protected $fillable = [
        'uid',
        'user_id',
        'type',
        'parameters',
        'status',
        'total_found',
        'processed_count',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'type'         => LeadScrapingTypeEnum::class,
        'status'       => LeadScrapingStatusEnum::class,
        'parameters'   => 'array',
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Boot function
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uid)) {
                $model->uid = str_unique();
            }
        });
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'uid';
    }

    /**
     * User relationship
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scraped leads relationship
     */
    public function leads(): HasMany
    {
        return $this->hasMany(ScrapedLead::class, 'job_id');
    }

    /**
     * Get progress percentage
     */
    public function getProgressAttribute(): int
    {
        if ($this->total_found === 0) {
            // If total_found is 0 but job is processing, show indeterminate progress
            if ($this->status === 'processing' && $this->processed_count > 0) {
                return min(90, $this->processed_count * 10);
            }
            return $this->status === 'completed' ? 100 : 0;
        }
        return min(100, (int) round(($this->processed_count / $this->total_found) * 100));
    }

    /**
     * Check if job is still running
     */
    public function isRunning(): bool
    {
        return $this->status === LeadScrapingStatusEnum::PROCESSING
            || $this->status === LeadScrapingStatusEnum::PENDING;
    }

    /**
     * Check if job is completed successfully
     */
    public function isCompleted(): bool
    {
        return $this->status === LeadScrapingStatusEnum::COMPLETED;
    }

    /**
     * Check if job failed
     */
    public function isFailed(): bool
    {
        return $this->status === LeadScrapingStatusEnum::FAILED;
    }

    /**
     * Get search query from parameters
     */
    public function getSearchQueryAttribute(): ?string
    {
        return $this->parameters['query'] ?? null;
    }

    /**
     * Get location from parameters
     */
    public function getLocationAttribute(): ?string
    {
        return $this->parameters['location'] ?? null;
    }

    /**
     * Scope for pending jobs
     */
    public function scopePending($query)
    {
        return $query->where('status', LeadScrapingStatusEnum::PENDING);
    }

    /**
     * Scope for processing jobs
     */
    public function scopeProcessing($query)
    {
        return $query->where('status', LeadScrapingStatusEnum::PROCESSING);
    }

    /**
     * Scope for completed jobs
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', LeadScrapingStatusEnum::COMPLETED);
    }

    /**
     * Scope by type
     */
    public function scopeOfType($query, LeadScrapingTypeEnum $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for admin (all jobs)
     */
    public function scopeAdmin($query)
    {
        return $query->whereNull('user_id');
    }

    /**
     * Scope for specific user
     */
    public function scopeOfUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Mark job as processing
     */
    public function markAsProcessing(): void
    {
        $this->update([
            'status'     => LeadScrapingStatusEnum::PROCESSING,
            'started_at' => now(),
        ]);
    }

    /**
     * Mark job as completed
     */
    public function markAsCompleted(?int $totalFound = null): void
    {
        $this->update([
            'status'       => LeadScrapingStatusEnum::COMPLETED,
            'completed_at' => now(),
            'total_found'  => $totalFound ?? $this->leads()->count(),
        ]);
    }

    /**
     * Mark job as failed
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status'        => LeadScrapingStatusEnum::FAILED,
            'error_message' => $errorMessage,
            'completed_at'  => now(),
        ]);
    }

    /**
     * Increment processed count
     */
    public function incrementProcessed(int $count = 1): void
    {
        $this->increment('processed_count', $count);
    }
}
