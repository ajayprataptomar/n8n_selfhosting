<?php

namespace App\Models;

use App\Enums\Campaign\CampaignChannel;
use App\Enums\Campaign\CampaignType;
use App\Enums\Campaign\ChannelDetectionMode;
use App\Enums\Campaign\UnifiedCampaignStatus;
use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class UnifiedCampaign extends Model
{
    use HasFactory, Filterable;

    protected $fillable = [
        'uid',
        'user_id',
        'name',
        'description',
        'status',
        'type',
        'schedule_at',
        'timezone',
        'recurring_config',
        'contact_group_id',
        'contact_filter',
        'channels',
        'channel_priority',
        'channel_detection_mode',
        'total_contacts',
        'processed_contacts',
        'stats',
        'meta_data',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'status' => UnifiedCampaignStatus::class,
        'type' => CampaignType::class,
        'channel_detection_mode' => ChannelDetectionMode::class,
        'schedule_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'recurring_config' => 'array',
        'contact_filter' => 'array',
        'channels' => 'array',
        'channel_priority' => 'array',
        'stats' => 'array',
        'meta_data' => 'array',
        'total_contacts' => 'integer',
        'processed_contacts' => 'integer',
    ];

    protected static function booted()
    {
        static::creating(function ($campaign) {
            $campaign->uid = str_unique();
        });
    }

    // ============ Relationships ============

    /**
     * Get the user who owns this campaign
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the contact group
     */
    public function contactGroup(): BelongsTo
    {
        return $this->belongsTo(ContactGroup::class, 'contact_group_id');
    }

    /**
     * Get all messages for this campaign (per channel)
     */
    public function messages(): HasMany
    {
        return $this->hasMany(CampaignMessage::class, 'campaign_id');
    }

    /**
     * Get all dispatches for this campaign
     */
    public function dispatches(): HasMany
    {
        return $this->hasMany(CampaignDispatch::class, 'campaign_id');
    }

    /**
     * Get A/B test for this campaign
     */
    public function abTest(): HasOne
    {
        return $this->hasOne(CampaignAbTest::class, 'campaign_id');
    }

    /**
     * Get insights for this campaign
     */
    public function insight(): HasOne
    {
        return $this->hasOne(CampaignInsight::class, 'campaign_id');
    }

    // ============ Scopes ============

    /**
     * Scope to admin campaigns
     */
    public function scopeAdmin(Builder $query): Builder
    {
        return $query->whereNull('user_id');
    }

    /**
     * Scope to user campaigns
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to draft campaigns
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', UnifiedCampaignStatus::DRAFT);
    }

    /**
     * Scope to scheduled campaigns
     */
    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('status', UnifiedCampaignStatus::SCHEDULED);
    }

    /**
     * Scope to running campaigns
     */
    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('status', UnifiedCampaignStatus::RUNNING);
    }

    /**
     * Scope to completed campaigns
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', UnifiedCampaignStatus::COMPLETED);
    }

    /**
     * Scope to campaigns ready to run
     */
    public function scopeReadyToRun(Builder $query): Builder
    {
        return $query->where('status', UnifiedCampaignStatus::SCHEDULED)
            ->where('schedule_at', '<=', now());
    }

    /**
     * Scope to campaigns with specific channel
     */
    public function scopeWithChannel(Builder $query, string $channel): Builder
    {
        return $query->whereJsonContains('channels', $channel);
    }

    // ============ Accessors & Helpers ============

    /**
     * Get message for specific channel
     */
    public function getMessageForChannel(string $channel): ?CampaignMessage
    {
        return $this->messages()->where('channel', $channel)->first();
    }

    /**
     * Get active channels as enum array
     */
    public function getActiveChannels(): array
    {
        return collect($this->channels ?? [])
            ->map(fn($ch) => CampaignChannel::tryFrom($ch))
            ->filter()
            ->all();
    }

    /**
     * Check if campaign has specific channel
     */
    public function hasChannel(string $channel): bool
    {
        return in_array($channel, $this->channels ?? []);
    }

    /**
     * Get progress percentage
     */
    public function getProgressPercentage(): float
    {
        // v5.0 UX polish — three fixes vs the naive processed/total_contacts:
        //   1. COMPLETED / CANCELLED campaigns force to 100 — the campaign is
        //      over, showing 80% forever confuses customers (happens when the
        //      contact-group snapshot had 10 rows but only 8 had valid channel
        //      addresses, so only 8 dispatches were created).
        //   2. Denominator prefers actual dispatch count over the pre-launch
        //      total_contacts snapshot when the two disagree — same rationale.
        //   3. Hard cap at 100 so historical over-counted campaigns (which
        //      may pre-date the v4.7.0.7 UNIQUE-index dedupe migration) don't
        //      surface as "190%" and look broken.
        if ($this->status === UnifiedCampaignStatus::COMPLETED
            || $this->status === UnifiedCampaignStatus::CANCELLED) {
            return 100.0;
        }

        // Use the actual dispatches count if present — that's the true
        // denominator once channel eligibility has been resolved.
        $denominator = (int) $this->total_contacts;
        try {
            $dispatchCount = (int) $this->dispatches()->count();
            if ($dispatchCount > 0) {
                $denominator = $dispatchCount;
            }
        } catch (\Throwable $e) {
            // Relation not queryable in this context — fall back to total_contacts.
        }

        if ($denominator <= 0) {
            return 0;
        }

        $pct = ($this->processed_contacts / $denominator) * 100;
        return round(min(100.0, max(0.0, $pct)), 2);
    }

    /**
     * Get stats for specific channel — derived live from dispatches, cumulative.
     * The `stats` JSON is written at send-time only and never records opens or
     * clicks, so reading from it kept `opened`/`clicked` permanently at 0.
     */
    public function getChannelStats(string $channel): array
    {
        $counts = $this->dispatches()
            ->where('channel', $channel)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $get = fn (string $status): int => (int) ($counts[$status] ?? 0);

        $sent      = $get('sent');
        $delivered = $get('delivered');
        $opened    = $get('opened');
        $clicked   = $get('clicked');
        $replied   = $get('replied');
        $bounced   = $get('bounced');
        $failed    = $get('failed');

        $postSend = $sent + $delivered + $opened + $clicked + $replied;

        return [
            'total'     => (int) $counts->sum(),
            'sent'      => $postSend + $bounced,
            'delivered' => $postSend,
            'failed'    => $failed,
            'opened'    => $opened + $clicked + $replied,
            'clicked'   => $clicked + $replied,
        ];
    }

    /**
     * Get overall stats
     */
    public function getOverallStats(): array
    {
        // Read-side: derive counts from the live dispatch statuses, which is the single
        // source of truth. The denormalized `stats` JSON only tracks send-time events
        // (sent/delivered/failed) and never records post-send opens/clicks, so reading
        // from it left open/click rates permanently at 0%.
        $counts = $this->dispatches()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $get = fn (string $status): int => (int) ($counts[$status] ?? 0);

        $sent      = $get('sent');
        $delivered = $get('delivered');
        $opened    = $get('opened');
        $clicked   = $get('clicked');
        $replied   = $get('replied');
        $bounced   = $get('bounced');
        $failed    = $get('failed');

        // A higher status implies every lower one (clicked ⇒ opened ⇒ delivered ⇒ sent).
        $postSend = $sent + $delivered + $opened + $clicked + $replied;

        return [
            'total'     => (int) $counts->sum(),
            // Anything that left the system (including hard bounces, which were sent).
            'sent'      => $postSend + $bounced,
            // Email has no SMTP delivery receipt, so a successful send counts as delivered.
            'delivered' => $postSend,
            'failed'    => $failed,
            'opened'    => $opened + $clicked + $replied,
            'clicked'   => $clicked,
        ];
    }

    /**
     * Update channel stats
     */
    public function updateChannelStats(string $channel, array $updates): void
    {
        $stats = $this->stats ?? [];
        $channelStats = $stats[$channel] ?? [
            'total' => 0,
            'sent' => 0,
            'delivered' => 0,
            'failed' => 0,
            'opened' => 0,
            'clicked' => 0,
        ];

        foreach ($updates as $key => $value) {
            if (isset($channelStats[$key])) {
                $channelStats[$key] += $value;
            }
        }

        $stats[$channel] = $channelStats;
        $this->update(['stats' => $stats]);
    }

    /**
     * Check if campaign can be edited
     */
    public function canEdit(): bool
    {
        return $this->status->isEditable();
    }

    /**
     * Check if campaign can be started
     */
    public function canStart(): bool
    {
        return $this->status->canStart() && $this->messages()->exists();
    }

    /**
     * Check if campaign can be paused
     */
    public function canPause(): bool
    {
        return $this->status->canPause();
    }

    /**
     * Check if campaign can be cancelled
     */
    public function canCancel(): bool
    {
        return in_array($this->status, [
            UnifiedCampaignStatus::DRAFT,
            UnifiedCampaignStatus::SCHEDULED,
            UnifiedCampaignStatus::RUNNING,
            UnifiedCampaignStatus::PAUSED,
        ]);
    }

    /**
     * Mark campaign as started
     */
    public function markAsStarted(): void
    {
        $this->update([
            'status' => UnifiedCampaignStatus::RUNNING,
            'started_at' => now(),
        ]);
    }

    /**
     * Mark campaign as completed
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => UnifiedCampaignStatus::COMPLETED,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark campaign as paused
     */
    public function markAsPaused(): void
    {
        $this->update(['status' => UnifiedCampaignStatus::PAUSED]);
    }

    /**
     * Mark campaign as cancelled
     */
    public function markAsCancelled(): void
    {
        $this->update(['status' => UnifiedCampaignStatus::CANCELLED]);
    }

    /**
     * Increment processed contacts count
     */
    public function incrementProcessed(int $count = 1): void
    {
        $this->increment('processed_contacts', $count);
    }

    /**
     * Get delivery rate percentage
     */
    public function getDeliveryRate(): float
    {
        $stats = $this->getOverallStats();
        if ($stats['sent'] === 0) {
            return 0;
        }

        return round(($stats['delivered'] / $stats['sent']) * 100, 2);
    }

    /**
     * Get open rate percentage (for email)
     */
    public function getOpenRate(): float
    {
        $stats = $this->getOverallStats();
        if ($stats['delivered'] === 0) {
            return 0;
        }

        return round(($stats['opened'] / $stats['delivered']) * 100, 2);
    }
}
