<?php

namespace App\Models;

use App\Enums\Common\Status;
use App\Enums\StatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PricingPlan extends Model
{
    use HasFactory, Filterable;

    protected $fillable = [
        'name',
        'type',
        'description',
        'amount',
        'affiliate_commission',
        'sms',
        'email',
        'whatsapp',
        'lead_generation',
        'automation',
        'ai_intelligence',
        'duration',
        'status',
        'carry_forward',
        'recommended_status'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'sms' => 'object',
        'email' => 'object',
        'whatsapp' => 'object',
        'lead_generation' => 'object',
        'automation' => 'object',
        'ai_intelligence' => 'object',
    ];

    /**
     * columnExists
     *
     * @param mixed $columnName
     * 
     * @return bool
     */
    public static function columnExists($columnName): bool
    {
        $table = (new static)->getTable();
        $columnExists = Schema::hasColumn($table, $columnName);

        return $columnExists;
    }

    /**
     * booted
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::creating(function ($plan) {
            
            $plan->status = StatusEnum::TRUE->status();
        });
    }
    
    
    /**
     * scopeActive
     *
     * @return Builder
     */
    public function scopeActive(): Builder|PricingPlan {
        return $this->where(function(Builder $q): Builder {
            return $q->where('status', StatusEnum::TRUE->status())
                        ->orWhere("status", Status::ACTIVE->value);
        });
    }

    /**
     * Get display features for this plan
     */
    public function displayFeatures(): BelongsToMany
    {
        return $this->belongsToMany(PlanDisplayFeature::class, 'plan_feature_assignments', 'pricing_plan_id', 'plan_display_feature_id')
            ->withPivot('is_included', 'custom_text')
            ->withTimestamps();
    }

    /**
     * Get all display features with inclusion status for this plan
     */
    public function getDisplayFeaturesWithStatus(): \Illuminate\Support\Collection
    {
        $allFeatures = PlanDisplayFeature::active()->ordered()->get();
        $assignedFeatures = $this->displayFeatures()->pluck('is_included', 'plan_display_feature_id')->toArray();

        return $allFeatures->map(function ($feature) use ($assignedFeatures) {
            $feature->is_included = $assignedFeatures[$feature->id] ?? false;
            return $feature;
        });
    }

    /**
     * Sync display features
     */
    public function syncDisplayFeatures(array $featureIds): void
    {
        $allFeatures = PlanDisplayFeature::active()->pluck('id')->toArray();
        $syncData = [];

        foreach ($allFeatures as $featureId) {
            $syncData[$featureId] = [
                'is_included' => in_array($featureId, $featureIds),
            ];
        }

        $this->displayFeatures()->sync($syncData);
    }
}
