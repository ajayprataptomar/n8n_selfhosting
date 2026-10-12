<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignAbVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'ab_test_id',
        'variant_label',
        'campaign_message_id',
        'contact_count',
        'delivered_count',
        'opened_count',
        'clicked_count',
        'replied_count',
        'is_winner',
        'meta_data',
    ];

    protected $casts = [
        'contact_count' => 'integer',
        'delivered_count' => 'integer',
        'opened_count' => 'integer',
        'clicked_count' => 'integer',
        'replied_count' => 'integer',
        'is_winner' => 'boolean',
        'meta_data' => 'array',
    ];

    // ============ Relationships ============

    /**
     * Get the A/B test
     */
    public function abTest(): BelongsTo
    {
        return $this->belongsTo(CampaignAbTest::class, 'ab_test_id');
    }

    /**
     * Get the campaign message
     */
    public function campaignMessage(): BelongsTo
    {
        return $this->belongsTo(CampaignMessage::class, 'campaign_message_id');
    }

    // ============ Accessors & Helpers ============

    /**
     * Get delivery rate
     */
    public function getDeliveryRate(): float
    {
        if ($this->contact_count === 0) {
            return 0;
        }

        return round(($this->delivered_count / $this->contact_count) * 100, 2);
    }

    /**
     * Get open rate
     */
    public function getOpenRate(): float
    {
        if ($this->delivered_count === 0) {
            return 0;
        }

        return round(($this->opened_count / $this->delivered_count) * 100, 2);
    }

    /**
     * Get click rate
     */
    public function getClickRate(): float
    {
        if ($this->delivered_count === 0) {
            return 0;
        }

        return round(($this->clicked_count / $this->delivered_count) * 100, 2);
    }

    /**
     * Get reply rate
     */
    public function getReplyRate(): float
    {
        if ($this->delivered_count === 0) {
            return 0;
        }

        return round(($this->replied_count / $this->delivered_count) * 100, 2);
    }

    /**
     * Get metric value based on metric type
     */
    public function getMetricValue(string $metric): float
    {
        return match ($metric) {
            'delivered' => $this->getDeliveryRate(),
            'opened' => $this->getOpenRate(),
            'clicked' => $this->getClickRate(),
            'replied' => $this->getReplyRate(),
            default => 0,
        };
    }

    /**
     * Increment stat
     */
    public function incrementStat(string $stat, int $count = 1): void
    {
        $column = match ($stat) {
            'contact' => 'contact_count',
            'delivered' => 'delivered_count',
            'opened' => 'opened_count',
            'clicked' => 'clicked_count',
            'replied' => 'replied_count',
            default => null,
        };

        if ($column) {
            $this->increment($column, $count);
        }
    }
}
