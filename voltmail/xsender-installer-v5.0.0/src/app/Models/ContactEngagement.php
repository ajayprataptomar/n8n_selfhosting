<?php

namespace App\Models;

use App\Enums\Campaign\CampaignChannel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactEngagement extends Model
{
    use HasFactory;

    protected $fillable = [
        'contact_id',
        'user_id',
        'channel',
        'engagement_score',
        'detected_timezone',
        'optimal_hours',
        'optimal_days',
        'total_sent',
        'total_delivered',
        'total_opened',
        'total_clicked',
        'total_replied',
        'last_engagement_at',
        'analyzed_at',
    ];

    protected $casts = [
        'channel' => CampaignChannel::class,
        'engagement_score' => 'float',
        'optimal_hours' => 'array',
        'optimal_days' => 'array',
        'total_sent' => 'integer',
        'total_delivered' => 'integer',
        'total_opened' => 'integer',
        'total_clicked' => 'integer',
        'total_replied' => 'integer',
        'last_engagement_at' => 'datetime',
        'analyzed_at' => 'datetime',
    ];

    // ============ Relationships ============

    /**
     * Get the contact
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Get the user (owner)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ============ Scopes ============

    /**
     * Scope by channel
     */
    public function scopeForChannel(Builder $query, string $channel): Builder
    {
        return $query->where('channel', $channel);
    }

    /**
     * Scope for high engagement
     */
    public function scopeHighEngagement(Builder $query, float $threshold = 70): Builder
    {
        return $query->where('engagement_score', '>=', $threshold);
    }

    /**
     * Scope for low engagement
     */
    public function scopeLowEngagement(Builder $query, float $threshold = 30): Builder
    {
        return $query->where('engagement_score', '<=', $threshold);
    }

    /**
     * Scope for stale data (needs re-analysis)
     */
    public function scopeNeedsAnalysis(Builder $query, int $daysOld = 7): Builder
    {
        return $query->where(function ($q) use ($daysOld) {
            $q->whereNull('analyzed_at')
                ->orWhere('analyzed_at', '<', now()->subDays($daysOld));
        });
    }

    // ============ Accessors & Helpers ============

    /**
     * Get delivery rate
     */
    public function getDeliveryRate(): float
    {
        if ($this->total_sent === 0) {
            return 0;
        }

        return round(($this->total_delivered / $this->total_sent) * 100, 2);
    }

    /**
     * Get open rate
     */
    public function getOpenRate(): float
    {
        if ($this->total_delivered === 0) {
            return 0;
        }

        return round(($this->total_opened / $this->total_delivered) * 100, 2);
    }

    /**
     * Get click rate
     */
    public function getClickRate(): float
    {
        if ($this->total_delivered === 0) {
            return 0;
        }

        return round(($this->total_clicked / $this->total_delivered) * 100, 2);
    }

    /**
     * Get reply rate
     */
    public function getReplyRate(): float
    {
        if ($this->total_delivered === 0) {
            return 0;
        }

        return round(($this->total_replied / $this->total_delivered) * 100, 2);
    }

    /**
     * Get engagement level label
     */
    public function getEngagementLevel(): string
    {
        if ($this->engagement_score >= 80) {
            return translate('Highly Engaged');
        } elseif ($this->engagement_score >= 60) {
            return translate('Engaged');
        } elseif ($this->engagement_score >= 40) {
            return translate('Moderate');
        } elseif ($this->engagement_score >= 20) {
            return translate('Low');
        } else {
            return translate('Inactive');
        }
    }

    /**
     * Get best hour to send
     */
    public function getBestHour(): ?int
    {
        if (empty($this->optimal_hours)) {
            return null;
        }

        $hours = $this->optimal_hours;
        arsort($hours);

        return (int) array_key_first($hours);
    }

    /**
     * Get best day to send (0 = Sunday, 6 = Saturday)
     */
    public function getBestDay(): ?int
    {
        if (empty($this->optimal_days)) {
            return null;
        }

        $days = $this->optimal_days;
        arsort($days);

        return (int) array_key_first($days);
    }

    /**
     * Get best day name
     */
    public function getBestDayName(): ?string
    {
        $dayIndex = $this->getBestDay();

        if ($dayIndex === null) {
            return null;
        }

        $days = [
            0 => translate('Sunday'),
            1 => translate('Monday'),
            2 => translate('Tuesday'),
            3 => translate('Wednesday'),
            4 => translate('Thursday'),
            5 => translate('Friday'),
            6 => translate('Saturday'),
        ];

        return $days[$dayIndex] ?? null;
    }

    /**
     * Calculate and update engagement score
     */
    public function calculateEngagementScore(): float
    {
        // Weighted scoring based on engagement actions
        $weights = [
            'delivery' => 0.2,
            'open' => 0.3,
            'click' => 0.3,
            'reply' => 0.2,
        ];

        $score = 0;
        $score += $this->getDeliveryRate() * $weights['delivery'];
        $score += $this->getOpenRate() * $weights['open'];
        $score += $this->getClickRate() * $weights['click'];
        $score += $this->getReplyRate() * $weights['reply'];

        $this->update([
            'engagement_score' => $score,
            'analyzed_at' => now(),
        ]);

        return $score;
    }

    /**
     * Record engagement event
     */
    public function recordEngagement(string $type): void
    {
        $column = match ($type) {
            'sent' => 'total_sent',
            'delivered' => 'total_delivered',
            'opened' => 'total_opened',
            'clicked' => 'total_clicked',
            'replied' => 'total_replied',
            default => null,
        };

        if (!$column) {
            return;
        }

        $this->increment($column);

        if (in_array($type, ['opened', 'clicked', 'replied'])) {
            $this->update(['last_engagement_at' => now()]);
        }

        // Downstream events IMPLY upstream — you can't open without receiving,
        // can't click without opening. Bump the upstream counters up to at
        // least the current downstream count so the rate math has a non-zero
        // denominator on installs where send-side tracking isn't wired into
        // ContactEngagement (only the tracking-pixel/click routes call this
        // path for unified campaigns).
        $this->refresh();
        $implies = match ($type) {
            'clicked'   => ['total_opened', 'total_delivered', 'total_sent'],
            'replied'   => ['total_opened', 'total_delivered', 'total_sent'],
            'opened'    => ['total_delivered', 'total_sent'],
            'delivered' => ['total_sent'],
            default     => [],
        };
        $dirty = false;
        $current = (int) $this->{$column};
        foreach ($implies as $col) {
            if ((int) $this->{$col} < $current) {
                $this->{$col} = $current;
                $dirty = true;
            }
        }
        if ($dirty) {
            $this->save();
        }

        // Recompute engagement_score every time so the Campaign Intelligence
        // dashboard's Avg Engagement card reflects live activity. Previously
        // this was only computed by the batch analyzer, so scores stayed at
        // 0 forever on installs that never ran that command.
        $this->calculateEngagementScore();
    }

    /**
     * Update optimal time patterns
     */
    public function updateOptimalPatterns(int $hour, int $dayOfWeek): void
    {
        // Update hours
        $hours = $this->optimal_hours ?? array_fill(0, 24, 0);
        $hours[$hour] = ($hours[$hour] ?? 0) + 1;

        // Normalize to percentages
        $totalHours = array_sum($hours);
        if ($totalHours > 0) {
            $hours = array_map(fn($v) => round($v / $totalHours, 3), $hours);
        }

        // Update days
        $days = $this->optimal_days ?? array_fill(0, 7, 0);
        $days[$dayOfWeek] = ($days[$dayOfWeek] ?? 0) + 1;

        // Normalize to percentages
        $totalDays = array_sum($days);
        if ($totalDays > 0) {
            $days = array_map(fn($v) => round($v / $totalDays, 3), $days);
        }

        $this->update([
            'optimal_hours' => $hours,
            'optimal_days' => $days,
        ]);
    }
}
