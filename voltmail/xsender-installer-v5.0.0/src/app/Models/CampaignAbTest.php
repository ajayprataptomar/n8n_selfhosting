<?php

namespace App\Models;

use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CampaignAbTest extends Model
{
    use HasFactory, Filterable;

    protected $fillable = [
        'uid',
        'campaign_id',
        'name',
        'status',
        'test_percentage',
        'winning_metric',
        'confidence_level',
        'winning_variant_id',
        'winner_selected_at',
        'auto_select_winner',
        'test_duration_hours',
        'meta_data',
    ];

    protected $casts = [
        'test_percentage' => 'integer',
        'confidence_level' => 'float',
        'auto_select_winner' => 'boolean',
        'test_duration_hours' => 'integer',
        'winner_selected_at' => 'datetime',
        'meta_data' => 'array',
    ];

    const STATUS_DRAFT = 'draft';
    const STATUS_RUNNING = 'running';
    const STATUS_PAUSED = 'paused';
    const STATUS_COMPLETED = 'completed';
    const STATUS_WINNER_SELECTED = 'winner_selected';

    const METRIC_DELIVERED = 'delivered';
    const METRIC_OPENED = 'opened';
    const METRIC_CLICKED = 'clicked';
    const METRIC_REPLIED = 'replied';

    protected static function booted()
    {
        static::creating(function ($test) {
            $test->uid = str_unique();
        });
    }

    // ============ Relationships ============

    /**
     * Get the campaign
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(UnifiedCampaign::class, 'campaign_id');
    }

    /**
     * Get all variants
     */
    public function variants(): HasMany
    {
        return $this->hasMany(CampaignAbVariant::class, 'ab_test_id');
    }

    /**
     * Get the winning variant
     */
    public function winningVariant(): BelongsTo
    {
        return $this->belongsTo(CampaignAbVariant::class, 'winning_variant_id');
    }

    // ============ Scopes ============

    /**
     * Scope to running tests
     */
    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_RUNNING);
    }

    /**
     * Scope to tests ready for evaluation
     */
    public function scopeReadyForEvaluation(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_RUNNING)
            ->where('auto_select_winner', true)
            ->whereRaw('created_at <= DATE_SUB(NOW(), INTERVAL test_duration_hours HOUR)');
    }

    // ============ Accessors & Helpers ============

    /**
     * Get status label
     */
    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => translate('Draft'),
            self::STATUS_RUNNING => translate('Running'),
            self::STATUS_PAUSED => translate('Paused'),
            self::STATUS_COMPLETED => translate('Completed'),
            self::STATUS_WINNER_SELECTED => translate('Winner Selected'),
            default => translate('Unknown'),
        };
    }

    /**
     * Get status badge class
     */
    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'badge--secondary',
            self::STATUS_RUNNING => 'badge--primary',
            self::STATUS_PAUSED => 'badge--warning',
            self::STATUS_COMPLETED => 'badge--info',
            self::STATUS_WINNER_SELECTED => 'badge--success',
            default => 'badge--secondary',
        };
    }

    /**
     * Get winning metric label
     */
    public function getWinningMetricLabel(): string
    {
        return match ($this->winning_metric) {
            self::METRIC_DELIVERED => translate('Delivery Rate'),
            self::METRIC_OPENED => translate('Open Rate'),
            self::METRIC_CLICKED => translate('Click Rate'),
            self::METRIC_REPLIED => translate('Reply Rate'),
            default => translate('Unknown'),
        };
    }

    /**
     * Check if test is running
     */
    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    /**
     * Check if winner has been selected
     */
    public function hasWinner(): bool
    {
        return $this->winning_variant_id !== null;
    }

    /**
     * Calculate test contacts count
     */
    public function getTestContactsCount(): int
    {
        $totalContacts = $this->campaign->total_contacts ?? 0;
        return (int) ceil($totalContacts * ($this->test_percentage / 100));
    }

    /**
     * Calculate contacts per variant
     */
    public function getContactsPerVariant(): int
    {
        $variantCount = $this->variants()->count();
        if ($variantCount === 0) {
            return 0;
        }

        return (int) floor($this->getTestContactsCount() / $variantCount);
    }

    /**
     * Evaluate and select winner
     */
    public function evaluateAndSelectWinner(): ?CampaignAbVariant
    {
        $variants = $this->variants;

        if ($variants->isEmpty()) {
            return null;
        }

        $winner = $variants->sortByDesc(function ($variant) {
            return $variant->getMetricValue($this->winning_metric);
        })->first();

        if ($winner) {
            $winner->update(['is_winner' => true]);

            $this->update([
                'winning_variant_id' => $winner->id,
                'winner_selected_at' => now(),
                'status' => self::STATUS_WINNER_SELECTED,
            ]);
        }

        return $winner;
    }

    /**
     * Start the test
     */
    public function start(): void
    {
        $this->update(['status' => self::STATUS_RUNNING]);
    }

    /**
     * Pause the test
     */
    public function pause(): void
    {
        $this->update(['status' => self::STATUS_PAUSED]);
    }

    /**
     * Complete the test
     */
    public function complete(): void
    {
        $this->update(['status' => self::STATUS_COMPLETED]);
    }
}
