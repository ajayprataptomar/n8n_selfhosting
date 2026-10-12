<?php

namespace App\Models;

use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PlanDisplayFeature extends Model
{
    use Filterable;

    protected $table = 'plan_display_features';

    protected $fillable = [
        'uid',
        'name',
        'icon',
        'description',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uid)) {
                $model->uid = \Illuminate\Support\Str::uuid();
            }
        });
    }

    /**
     * Scope for active features
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for ordering by sort_order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc');
    }

    /**
     * Get plans that have this feature
     */
    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(PricingPlan::class, 'plan_feature_assignments', 'plan_display_feature_id', 'pricing_plan_id')
            ->withPivot('is_included', 'custom_text')
            ->withTimestamps();
    }

    /**
     * Check if feature is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
