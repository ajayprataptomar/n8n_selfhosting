<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentAnalysis extends Model
{
    use HasFactory;

    protected $table = 'content_analyses';

    protected $fillable = [
        'campaign_message_id',
        'spam_score',
        'deliverability_score',
        'subject_score',
        'spam_triggers',
        'improvements',
        'ai_analysis',
    ];

    protected $casts = [
        'spam_score' => 'float',
        'deliverability_score' => 'float',
        'subject_score' => 'float',
        'spam_triggers' => 'array',
        'improvements' => 'array',
        'ai_analysis' => 'array',
    ];

    // ============ Relationships ============

    /**
     * Get the campaign message
     */
    public function campaignMessage(): BelongsTo
    {
        return $this->belongsTo(CampaignMessage::class, 'campaign_message_id');
    }

    // ============ Accessors & Helpers ============

    /**
     * Get overall score
     */
    public function getOverallScore(): float
    {
        $scores = array_filter([
            $this->deliverability_score,
            $this->subject_score,
        ]);

        if (empty($scores)) {
            return 0;
        }

        return round(array_sum($scores) / count($scores), 2);
    }

    /**
     * Get spam risk level
     */
    public function getSpamRiskLevel(): string
    {
        if ($this->spam_score >= 70) {
            return 'high';
        } elseif ($this->spam_score >= 40) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Get spam risk label
     */
    public function getSpamRiskLabel(): string
    {
        return match ($this->getSpamRiskLevel()) {
            'high' => translate('High Risk'),
            'medium' => translate('Medium Risk'),
            'low' => translate('Low Risk'),
            default => translate('Unknown'),
        };
    }

    /**
     * Get spam risk badge class
     */
    public function getSpamRiskBadgeClass(): string
    {
        return match ($this->getSpamRiskLevel()) {
            'high' => 'badge--danger',
            'medium' => 'badge--warning',
            'low' => 'badge--success',
            default => 'badge--secondary',
        };
    }

    /**
     * Get deliverability rating
     */
    public function getDeliverabilityRating(): string
    {
        if ($this->deliverability_score >= 80) {
            return 'excellent';
        } elseif ($this->deliverability_score >= 60) {
            return 'good';
        } elseif ($this->deliverability_score >= 40) {
            return 'fair';
        } else {
            return 'poor';
        }
    }

    /**
     * Get deliverability label
     */
    public function getDeliverabilityLabel(): string
    {
        return match ($this->getDeliverabilityRating()) {
            'excellent' => translate('Excellent'),
            'good' => translate('Good'),
            'fair' => translate('Fair'),
            'poor' => translate('Poor'),
            default => translate('Unknown'),
        };
    }

    /**
     * Get subject rating (for email)
     */
    public function getSubjectRating(): string
    {
        if ($this->subject_score === null) {
            return 'n/a';
        }

        if ($this->subject_score >= 80) {
            return 'excellent';
        } elseif ($this->subject_score >= 60) {
            return 'good';
        } elseif ($this->subject_score >= 40) {
            return 'fair';
        } else {
            return 'poor';
        }
    }

    /**
     * Get formatted spam triggers
     */
    public function getFormattedSpamTriggers(): array
    {
        if (empty($this->spam_triggers)) {
            return [];
        }

        return array_map(function ($trigger) {
            return [
                'keyword' => $trigger['keyword'] ?? '',
                'reason' => $trigger['reason'] ?? '',
                'severity' => $trigger['severity'] ?? 'low',
            ];
        }, $this->spam_triggers);
    }

    /**
     * Get formatted improvements
     */
    public function getFormattedImprovements(): array
    {
        if (empty($this->improvements)) {
            return [];
        }

        return array_map(function ($improvement) {
            return [
                'suggestion' => $improvement['suggestion'] ?? '',
                'impact' => $improvement['impact'] ?? 'low',
                'category' => $improvement['category'] ?? 'general',
            ];
        }, $this->improvements);
    }

    /**
     * Check if content needs improvement
     */
    public function needsImprovement(): bool
    {
        return $this->spam_score >= 40 || $this->deliverability_score < 60;
    }

    /**
     * Get top improvements (sorted by impact)
     */
    public function getTopImprovements(int $limit = 3): array
    {
        $improvements = $this->getFormattedImprovements();

        // Sort by impact (high > medium > low)
        usort($improvements, function ($a, $b) {
            $order = ['high' => 0, 'medium' => 1, 'low' => 2];
            return ($order[$a['impact']] ?? 2) <=> ($order[$b['impact']] ?? 2);
        });

        return array_slice($improvements, 0, $limit);
    }

    /**
     * Get AI analysis summary
     */
    public function getAiSummary(): ?string
    {
        return $this->ai_analysis['summary'] ?? null;
    }
}
