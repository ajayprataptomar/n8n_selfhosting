<?php

namespace App\Models;

use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentResource extends Model
{
    use HasFactory, Filterable;

    protected $table = 'content_resources';

    protected $fillable = [
        'uid',
        'user_id',
        'name',
        'company_name',
        'business_type',
        'company_description',
        'website',
        'industry',
        'target_audience',
        'brand_voice',
        'writing_preferences',
        'products_services',
        'faqs',
        'templates',
        'status',
        'is_default',
    ];

    protected $casts = [
        'writing_preferences' => 'array',
        'products_services'   => 'array',
        'faqs'                => 'array',
        'templates'           => 'array',
        'is_default'          => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->uid)) {
                $model->uid = str_unique(30);
            }
        });
    }

    /**
     * Relationship: Resource belongs to a User (Tenant)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relationship: Resource has many generated history records
     */
    public function generationHistories(): HasMany
    {
        return $this->hasMany(ContentGenerationHistory::class, 'content_resource_id');
    }

    /**
     * Scope: Filter active resources
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope: Tenant isolation filter
     */
    public function scopeUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: Default resource for tenant
     */
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }
}
