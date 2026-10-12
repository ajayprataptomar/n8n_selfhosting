<?php

namespace App\Models;

use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScrapedLead extends Model
{
    use Filterable;

    protected $fillable = [
        'uid',
        'job_id',
        'user_id',
        'business_name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'website',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'latitude',
        'longitude',
        'category',
        'rating',
        'reviews_count',
        'place_id',
        'facebook',
        'instagram',
        'twitter',
        'linkedin',
        'source_url',
        'raw_data',
        'quality_score',
        'email_verified',
        'phone_verified',
        'imported_to_group_id',
        'imported_at',
    ];

    protected $casts = [
        'raw_data'       => 'array',
        'rating'         => 'decimal:1',
        'latitude'       => 'decimal:8',
        'longitude'      => 'decimal:8',
        'quality_score'  => 'integer',
        'reviews_count'  => 'integer',
        'email_verified' => 'boolean',
        'phone_verified' => 'boolean',
        'imported_at'    => 'datetime',
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
     * Job relationship
     */
    public function job(): BelongsTo
    {
        return $this->belongsTo(LeadScrapingJob::class, 'job_id');
    }

    /**
     * User relationship
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Contact group relationship (if imported)
     */
    public function importedGroup(): BelongsTo
    {
        return $this->belongsTo(ContactGroup::class, 'imported_to_group_id');
    }

    /**
     * Get full name
     */
    public function getFullNameAttribute(): string
    {
        if ($this->first_name || $this->last_name) {
            return trim("{$this->first_name} {$this->last_name}");
        }
        return $this->business_name ?? 'Unknown';
    }

    /**
     * Get display name (business name or full name)
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->business_name ?: $this->full_name;
    }

    /**
     * Get full address
     */
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address,
            $this->city,
            $this->state,
            $this->postal_code,
            $this->country,
        ]);
        return implode(', ', $parts);
    }

    /**
     * Check if lead is imported
     */
    public function isImported(): bool
    {
        return !is_null($this->imported_to_group_id);
    }

    /**
     * Check if lead has email
     */
    public function hasEmail(): bool
    {
        return !empty($this->email);
    }

    /**
     * Check if lead has phone
     */
    public function hasPhone(): bool
    {
        return !empty($this->phone);
    }

    /**
     * Get quality label
     */
    public function getQualityLabelAttribute(): string
    {
        if ($this->quality_score >= 80) {
            return 'Excellent';
        } elseif ($this->quality_score >= 60) {
            return 'Good';
        } elseif ($this->quality_score >= 40) {
            return 'Fair';
        } else {
            return 'Low';
        }
    }

    /**
     * Get quality badge color
     */
    public function getQualityBadgeColorAttribute(): string
    {
        if ($this->quality_score >= 80) {
            return 'success-soft';
        } elseif ($this->quality_score >= 60) {
            return 'primary-soft';
        } elseif ($this->quality_score >= 40) {
            return 'warning-soft';
        } else {
            return 'danger-soft';
        }
    }

    /**
     * Scope for leads with email
     */
    public function scopeWithEmail($query)
    {
        return $query->whereNotNull('email')->where('email', '!=', '');
    }

    /**
     * Scope for leads with phone
     */
    public function scopeWithPhone($query)
    {
        return $query->whereNotNull('phone')->where('phone', '!=', '');
    }

    /**
     * Scope for verified emails
     */
    public function scopeVerifiedEmail($query)
    {
        return $query->where('email_verified', true);
    }

    /**
     * Scope for verified phones
     */
    public function scopeVerifiedPhone($query)
    {
        return $query->where('phone_verified', true);
    }

    /**
     * Scope for not imported leads
     */
    public function scopeNotImported($query)
    {
        return $query->whereNull('imported_to_group_id');
    }

    /**
     * Scope for imported leads
     */
    public function scopeImported($query)
    {
        return $query->whereNotNull('imported_to_group_id');
    }

    /**
     * Scope by minimum quality score
     */
    public function scopeMinQuality($query, int $minScore)
    {
        return $query->where('quality_score', '>=', $minScore);
    }

    /**
     * Calculate quality score based on available data
     */
    public function calculateQualityScore(): int
    {
        $score = 0;

        // Contact information (max 40 points)
        if ($this->email) {
            $score += 15;
        }
        if ($this->email_verified) {
            $score += 10;
        }
        if ($this->phone) {
            $score += 10;
        }
        if ($this->phone_verified) {
            $score += 5;
        }

        // Business information (max 30 points)
        if ($this->business_name) {
            $score += 10;
        }
        if ($this->website) {
            $score += 5;
        }
        if ($this->category) {
            $score += 5;
        }
        if ($this->rating && $this->rating >= 4.0) {
            $score += 5;
        }
        if ($this->reviews_count && $this->reviews_count >= 10) {
            $score += 5;
        }

        // Location information (max 20 points)
        if ($this->address) {
            $score += 5;
        }
        if ($this->city) {
            $score += 5;
        }
        if ($this->country) {
            $score += 5;
        }
        if ($this->latitude && $this->longitude) {
            $score += 5;
        }

        // Social profiles (max 10 points)
        $socialCount = 0;
        if ($this->facebook) $socialCount++;
        if ($this->instagram) $socialCount++;
        if ($this->twitter) $socialCount++;
        if ($this->linkedin) $socialCount++;
        $score += min($socialCount * 3, 10);

        return min($score, 100);
    }

    /**
     * Mark as imported to a group
     */
    public function markAsImported(int $groupId): void
    {
        $this->update([
            'imported_to_group_id' => $groupId,
            'imported_at'          => now(),
        ]);
    }

    /**
     * Convert to contact array for import
     */
    public function toContactArray(): array
    {
        return [
            'first_name'       => $this->first_name ?: $this->business_name,
            'last_name'        => $this->last_name,
            'email_contact'    => $this->email,
            'sms_contact'      => $this->phone,
            'whatsapp_contact' => $this->phone,
            'country'          => $this->country,
            'city'             => $this->city,
            'address'          => $this->address,
            'meta_data'        => [
                'source'        => 'lead_scraper',
                'lead_id'       => $this->id,
                'business_name' => $this->business_name,
                'website'       => $this->website,
                'category'      => $this->category,
                'rating'        => $this->rating,
            ],
        ];
    }
}
