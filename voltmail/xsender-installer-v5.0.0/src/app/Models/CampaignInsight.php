<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignInsight extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'hourly_stats',
        'daily_stats',
        'channel_comparison',
        'engagement_heatmap',
        'trend_direction',
        // Rate columns must be fillable — InsightsService::generateInsights()
        // passes them to updateOrCreate(). Missing from $fillable meant mass
        // assignment silently dropped every rate value, leaving all cards at
        // 0.0% forever even after the cumulative-stats fix.
        'delivery_rate',
        'open_rate',
        'click_rate',
        'reply_rate',
        'bounce_rate',
        'ai_recommendations',
        'performance_summary',
        'generated_at',
    ];

    protected $casts = [
        'hourly_stats' => 'array',
        'daily_stats' => 'array',
        'channel_comparison' => 'array',
        'engagement_heatmap' => 'array',
        'ai_recommendations' => 'array',
        'performance_summary' => 'array',
        'delivery_rate' => 'decimal:2',
        'open_rate' => 'decimal:2',
        'click_rate' => 'decimal:2',
        'reply_rate' => 'decimal:2',
        'bounce_rate' => 'decimal:2',
        'generated_at' => 'datetime',
    ];

    const TREND_IMPROVING = 'improving';
    const TREND_STABLE = 'stable';
    const TREND_DECLINING = 'declining';

    // ============ Relationships ============

    /**
     * Get the campaign
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(UnifiedCampaign::class, 'campaign_id');
    }

    // ============ Accessors & Helpers ============

    /**
     * Get trend label
     */
    public function getTrendLabel(): string
    {
        return match ($this->trend_direction) {
            self::TREND_IMPROVING => translate('Improving'),
            self::TREND_STABLE => translate('Stable'),
            self::TREND_DECLINING => translate('Declining'),
            default => translate('Unknown'),
        };
    }

    /**
     * Get trend icon
     */
    public function getTrendIcon(): string
    {
        return match ($this->trend_direction) {
            self::TREND_IMPROVING => 'bi bi-graph-up-arrow text-success',
            self::TREND_STABLE => 'bi bi-dash-lg text-info',
            self::TREND_DECLINING => 'bi bi-graph-down-arrow text-danger',
            default => 'bi bi-question-circle text-secondary',
        };
    }

    /**
     * Get best performing channel
     */
    public function getBestChannel(): ?string
    {
        if (empty($this->channel_comparison)) {
            return null;
        }

        $best = null;
        $bestRate = 0;

        foreach ($this->channel_comparison as $channel => $stats) {
            $deliveryRate = isset($stats['delivered'], $stats['sent']) && $stats['sent'] > 0
                ? ($stats['delivered'] / $stats['sent']) * 100
                : 0;

            if ($deliveryRate > $bestRate) {
                $bestRate = $deliveryRate;
                $best = $channel;
            }
        }

        return $best;
    }

    /**
     * Get peak hour
     */
    public function getPeakHour(): ?int
    {
        if (empty($this->hourly_stats)) {
            return null;
        }

        $peakHour = null;
        $peakEngagement = 0;

        foreach ($this->hourly_stats as $hour => $stats) {
            $engagement = $stats['opened'] ?? 0;

            if ($engagement > $peakEngagement) {
                $peakEngagement = $engagement;
                $peakHour = (int) $hour;
            }
        }

        return $peakHour;
    }

    /**
     * Get peak day
     */
    public function getPeakDay(): ?string
    {
        if (empty($this->daily_stats)) {
            return null;
        }

        $peakDay = null;
        $peakEngagement = 0;

        foreach ($this->daily_stats as $day => $stats) {
            $engagement = $stats['opened'] ?? 0;

            if ($engagement > $peakEngagement) {
                $peakEngagement = $engagement;
                $peakDay = $day;
            }
        }

        return $peakDay;
    }

    /**
     * Get formatted recommendations
     */
    public function getRecommendations(): array
    {
        if (empty($this->ai_recommendations)) {
            return [];
        }

        return array_map(function ($rec) {
            return [
                'type' => $rec['type'] ?? 'info',
                'title' => $rec['title'] ?? '',
                'description' => $rec['description'] ?? '',
                'priority' => $rec['priority'] ?? 'low',
            ];
        }, $this->ai_recommendations);
    }

    /**
     * Check if insights are fresh
     */
    public function isFresh(int $hoursOld = 1): bool
    {
        if (!$this->generated_at) {
            return false;
        }

        return $this->generated_at->diffInHours(now()) < $hoursOld;
    }

    /**
     * Get heatmap data for chart
     */
    public function getHeatmapChartData(): array
    {
        if (empty($this->engagement_heatmap)) {
            return [];
        }

        $data = [];

        foreach ($this->engagement_heatmap as $day => $hours) {
            foreach ($hours as $hour => $value) {
                $data[] = [
                    'day' => $day,
                    'hour' => $hour,
                    'value' => $value,
                ];
            }
        }

        return $data;
    }
}
