<?php

namespace App\Services\CampaignIntelligence;

use App\Models\CampaignDispatch;
use App\Models\CampaignInsight;
use App\Models\UnifiedCampaign;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InsightsService
{
    /**
     * Generate insights for a campaign
     */
    public function generateInsights(UnifiedCampaign $campaign): CampaignInsight
    {
        $dispatches = $campaign->dispatches()
            ->whereNotNull('sent_at')
            ->get();

        $insight = CampaignInsight::updateOrCreate(
            ['campaign_id' => $campaign->id],
            [
                'hourly_stats' => $this->calculateHourlyStats($dispatches),
                'daily_stats' => $this->calculateDailyStats($dispatches),
                'channel_comparison' => $this->calculateChannelComparison($dispatches),
                'engagement_heatmap' => $this->generateEngagementHeatmap($dispatches),
                'trend_direction' => $this->determineTrendDirection($campaign),
                'delivery_rate' => $campaign->getDeliveryRate(),
                'open_rate' => $campaign->getOpenRate(),
                'click_rate' => $this->calculateClickRate($dispatches),
                'reply_rate' => $this->calculateReplyRate($dispatches),
                'bounce_rate' => $this->calculateBounceRate($dispatches),
                'ai_recommendations' => $this->generateRecommendations($campaign, $dispatches),
                'performance_summary' => $this->generatePerformanceSummary($campaign, $dispatches),
                'generated_at' => now(),
            ]
        );

        return $insight;
    }

    /**
     * Calculate hourly statistics
     */
    protected function calculateHourlyStats(Collection $dispatches): array
    {
        $hourlyStats = [];

        for ($hour = 0; $hour < 24; $hour++) {
            $hourlyStats[$hour] = [
                'sent' => 0,
                'delivered' => 0,
                'opened' => 0,
                'clicked' => 0,
                'failed' => 0,
            ];
        }

        foreach ($dispatches as $dispatch) {
            if (!$dispatch->sent_at) continue;

            $hour = Carbon::parse($dispatch->sent_at)->hour;
            $status = $dispatch->status->value ?? 'pending';

            $hourlyStats[$hour]['sent']++;

            // Email has no SMTP delivery receipt, so `sent` status IS the delivered
            // signal. Include it so the Delivered series doesn't sit at 0 forever.
            if (in_array($status, ['sent', 'delivered', 'opened', 'clicked', 'replied'])) {
                $hourlyStats[$hour]['delivered']++;
            }
            if (in_array($status, ['opened', 'clicked', 'replied'])) {
                $hourlyStats[$hour]['opened']++;
            }
            if (in_array($status, ['clicked', 'replied'])) {
                $hourlyStats[$hour]['clicked']++;
            }
            if ($status === 'failed') {
                $hourlyStats[$hour]['failed']++;
            }
        }

        return $hourlyStats;
    }

    /**
     * Calculate daily statistics
     */
    protected function calculateDailyStats(Collection $dispatches): array
    {
        $dailyStats = [];

        foreach ($dispatches as $dispatch) {
            if (!$dispatch->sent_at) continue;

            $date = Carbon::parse($dispatch->sent_at)->format('Y-m-d');

            if (!isset($dailyStats[$date])) {
                $dailyStats[$date] = [
                    'sent' => 0,
                    'delivered' => 0,
                    'opened' => 0,
                    'failed' => 0,
                ];
            }

            $status = $dispatch->status->value ?? 'pending';

            $dailyStats[$date]['sent']++;
            // Include `sent` in delivered — email has no SMTP delivery receipt.
            if (in_array($status, ['sent', 'delivered', 'opened', 'clicked', 'replied'])) {
                $dailyStats[$date]['delivered']++;
            }
            if (in_array($status, ['opened', 'clicked', 'replied'])) {
                $dailyStats[$date]['opened']++;
            }
            if ($status === 'failed') {
                $dailyStats[$date]['failed']++;
            }
        }

        // Sort by date
        ksort($dailyStats);

        return $dailyStats;
    }

    /**
     * Calculate channel comparison
     */
    protected function calculateChannelComparison(Collection $dispatches): array
    {
        $channelStats = [];

        foreach ($dispatches as $dispatch) {
            $channel = $dispatch->channel->value ?? 'unknown';

            if (!isset($channelStats[$channel])) {
                $channelStats[$channel] = [
                    'total' => 0,
                    'sent' => 0,
                    'delivered' => 0,
                    'opened' => 0,
                    'clicked' => 0,
                    'failed' => 0,
                    'delivery_rate' => 0,
                    'open_rate' => 0,
                ];
            }

            $status = $dispatch->status->value ?? 'pending';
            $channelStats[$channel]['total']++;

            if ($dispatch->sent_at) {
                $channelStats[$channel]['sent']++;
            }
            // Include `sent` in delivered — email has no SMTP delivery receipt,
            // so a `sent` status IS the strongest signal we have of delivery.
            if (in_array($status, ['sent', 'delivered', 'opened', 'clicked', 'replied'])) {
                $channelStats[$channel]['delivered']++;
            }
            if (in_array($status, ['opened', 'clicked', 'replied'])) {
                $channelStats[$channel]['opened']++;
            }
            if (in_array($status, ['clicked', 'replied'])) {
                $channelStats[$channel]['clicked']++;
            }
            if ($status === 'failed') {
                $channelStats[$channel]['failed']++;
            }
        }

        // Calculate rates
        foreach ($channelStats as $channel => &$stats) {
            $stats['delivery_rate'] = $stats['sent'] > 0
                ? round(($stats['delivered'] / $stats['sent']) * 100, 2)
                : 0;
            $stats['open_rate'] = $stats['delivered'] > 0
                ? round(($stats['opened'] / $stats['delivered']) * 100, 2)
                : 0;
        }

        return $channelStats;
    }

    /**
     * Generate engagement heatmap (hour x day of week)
     */
    protected function generateEngagementHeatmap(Collection $dispatches): array
    {
        $heatmap = [];

        // Initialize heatmap (7 days x 24 hours)
        for ($day = 0; $day < 7; $day++) {
            for ($hour = 0; $hour < 24; $hour++) {
                $heatmap[$day][$hour] = [
                    'sent' => 0,
                    'opened' => 0,
                    'engagement_score' => 0,
                ];
            }
        }

        foreach ($dispatches as $dispatch) {
            if (!$dispatch->sent_at) continue;

            $sentAt = Carbon::parse($dispatch->sent_at);
            $day = $sentAt->dayOfWeek; // 0 (Sunday) to 6 (Saturday)
            $hour = $sentAt->hour;
            $status = $dispatch->status->value ?? 'pending';

            $heatmap[$day][$hour]['sent']++;

            if (in_array($status, ['opened', 'clicked', 'replied'])) {
                $heatmap[$day][$hour]['opened']++;
            }
        }

        // Calculate engagement scores
        foreach ($heatmap as $day => &$hours) {
            foreach ($hours as $hour => &$data) {
                $data['engagement_score'] = $data['sent'] > 0
                    ? round(($data['opened'] / $data['sent']) * 100, 2)
                    : 0;
            }
        }

        return $heatmap;
    }

    /**
     * Determine trend direction
     */
    protected function determineTrendDirection(UnifiedCampaign $campaign): string
    {
        // Compare with previous campaigns
        $previousCampaigns = UnifiedCampaign::where('user_id', $campaign->user_id)
            ->where('id', '<', $campaign->id)
            ->where('status', 'completed')
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get();

        if ($previousCampaigns->isEmpty()) {
            return 'stable';
        }

        $currentRate = $campaign->getDeliveryRate();
        $avgPreviousRate = $previousCampaigns->avg(fn($c) => $c->getDeliveryRate());

        $difference = $currentRate - $avgPreviousRate;

        if ($difference > 5) {
            return 'improving';
        } elseif ($difference < -5) {
            return 'declining';
        }

        return 'stable';
    }

    /**
     * Calculate click rate
     */
    protected function calculateClickRate(Collection $dispatches): float
    {
        $delivered = $dispatches->filter(fn($d) =>
            in_array($d->status->value ?? '', ['delivered', 'opened', 'clicked', 'replied'])
        )->count();

        $clicked = $dispatches->filter(fn($d) =>
            in_array($d->status->value ?? '', ['clicked', 'replied'])
        )->count();

        return $delivered > 0 ? round(($clicked / $delivered) * 100, 2) : 0;
    }

    /**
     * Calculate reply rate
     */
    protected function calculateReplyRate(Collection $dispatches): float
    {
        $delivered = $dispatches->filter(fn($d) =>
            in_array($d->status->value ?? '', ['delivered', 'opened', 'clicked', 'replied'])
        )->count();

        $replied = $dispatches->filter(fn($d) => $d->status->value === 'replied')->count();

        return $delivered > 0 ? round(($replied / $delivered) * 100, 2) : 0;
    }

    /**
     * Calculate bounce rate
     */
    protected function calculateBounceRate(Collection $dispatches): float
    {
        $sent = $dispatches->filter(fn($d) => $d->sent_at)->count();
        $bounced = $dispatches->filter(fn($d) => $d->status->value === 'bounced')->count();

        return $sent > 0 ? round(($bounced / $sent) * 100, 2) : 0;
    }

    /**
     * Generate AI recommendations
     */
    protected function generateRecommendations(UnifiedCampaign $campaign, Collection $dispatches): array
    {
        $recommendations = [];

        // Analyze delivery rate
        $deliveryRate = $campaign->getDeliveryRate();
        if ($deliveryRate < 80) {
            $recommendations[] = [
                'type' => 'warning',
                'title' => translate('Low Delivery Rate'),
                'message' => translate('Your delivery rate is below 80%. Consider cleaning your contact list and verifying email addresses.'),
                'priority' => 'high',
            ];
        }

        // Analyze open rate
        $openRate = $campaign->getOpenRate();
        if ($openRate < 20) {
            $recommendations[] = [
                'type' => 'suggestion',
                'title' => translate('Improve Open Rate'),
                'message' => translate('Your open rate could be improved. Try more engaging subject lines and personalization.'),
                'priority' => 'medium',
            ];
        }

        // Analyze best send times from heatmap
        $heatmap = $this->generateEngagementHeatmap($dispatches);
        $bestSlot = $this->findBestTimeSlot($heatmap);
        if ($bestSlot) {
            $recommendations[] = [
                'type' => 'info',
                'title' => translate('Optimal Send Time'),
                'message' => translate('Your audience engages most on') . ' ' . $this->getDayName($bestSlot['day']) . ' ' . translate('at') . ' ' . $bestSlot['hour'] . ':00',
                'priority' => 'low',
            ];
        }

        // Channel-specific recommendations
        $channelStats = $this->calculateChannelComparison($dispatches);
        $bestChannel = collect($channelStats)->sortByDesc('delivery_rate')->keys()->first();
        if ($bestChannel) {
            $recommendations[] = [
                'type' => 'success',
                'title' => translate('Best Performing Channel'),
                'message' => ucfirst($bestChannel) . ' ' . translate('has the highest delivery rate. Consider prioritizing this channel.'),
                'priority' => 'low',
            ];
        }

        return $recommendations;
    }

    /**
     * Find best time slot from heatmap
     */
    protected function findBestTimeSlot(array $heatmap): ?array
    {
        $bestScore = 0;
        $bestSlot = null;

        foreach ($heatmap as $day => $hours) {
            foreach ($hours as $hour => $data) {
                if ($data['engagement_score'] > $bestScore && $data['sent'] >= 10) {
                    $bestScore = $data['engagement_score'];
                    $bestSlot = ['day' => $day, 'hour' => $hour, 'score' => $bestScore];
                }
            }
        }

        return $bestSlot;
    }

    /**
     * Get day name
     */
    protected function getDayName(int $day): string
    {
        $days = [
            0 => translate('Sunday'),
            1 => translate('Monday'),
            2 => translate('Tuesday'),
            3 => translate('Wednesday'),
            4 => translate('Thursday'),
            5 => translate('Friday'),
            6 => translate('Saturday'),
        ];

        return $days[$day] ?? '';
    }

    /**
     * Generate performance summary
     */
    protected function generatePerformanceSummary(UnifiedCampaign $campaign, Collection $dispatches): array
    {
        // Post-send statuses — everything that has left the system successfully.
        // A dispatch that upgrades from SENT to OPENED to CLICKED still counts as
        // sent + delivered. Previous logic filtered by sent_at only for `sent`
        // (correct) but excluded `sent` status from `delivered` (wrong: for email
        // SMTP has no delivery receipt, so `sent` IS the delivered signal).
        $postSendStatuses = ['sent', 'delivered', 'opened', 'clicked', 'replied'];

        $sent = $dispatches->filter(fn ($d) =>
            $d->sent_at || in_array($d->status->value ?? '', $postSendStatuses, true)
        )->count();

        $delivered = $dispatches->filter(fn ($d) =>
            in_array($d->status->value ?? '', $postSendStatuses, true)
        )->count();

        return [
            'total_contacts' => $campaign->total_contacts,
            'processed' => $campaign->processed_contacts,
            'sent' => $sent,
            'delivered' => $delivered,
            'failed' => $dispatches->filter(fn ($d) => ($d->status->value ?? '') === 'failed')->count(),
            'pending' => $dispatches->filter(fn ($d) => ($d->status->value ?? '') === 'pending')->count(),
            'opened' => $dispatches->filter(fn ($d) =>
                in_array($d->status->value ?? '', ['opened', 'clicked', 'replied'], true)
            )->count(),
            'clicked' => $dispatches->filter(fn ($d) => ($d->status->value ?? '') === 'clicked')->count(),
            'delivery_rate' => $campaign->getDeliveryRate(),
            'completion_percentage' => $campaign->getProgressPercentage(),
            'channels_used' => count($campaign->channels),
            'duration' => $campaign->started_at
                ? $campaign->started_at->diffForHumans($campaign->completed_at ?? now(), true)
                : null,
        ];
    }

    /**
     * Get real-time statistics for dashboard
     */
    public function getRealTimeStats(UnifiedCampaign $campaign): array
    {
        // Include `sent` in delivered — email has no SMTP delivery receipt.
        $deliveredStatuses = ['sent', 'delivered', 'opened', 'clicked', 'replied'];

        return [
            'total' => $campaign->total_contacts,
            'processed' => $campaign->processed_contacts,
            'sent' => $campaign->dispatches()->whereIn('status', $deliveredStatuses)->count(),
            'delivered' => $campaign->dispatches()->whereIn('status', $deliveredStatuses)->count(),
            'failed' => $campaign->dispatches()->where('status', 'failed')->count(),
            'pending' => $campaign->dispatches()->where('status', 'pending')->count(),
            'delivery_rate' => $campaign->getDeliveryRate(),
            'progress' => $campaign->getProgressPercentage(),
        ];
    }

    /**
     * Compare campaigns
     */
    public function compareCampaigns(array $campaignIds): array
    {
        $campaigns = UnifiedCampaign::whereIn('id', $campaignIds)->get();
        $comparison = [];

        foreach ($campaigns as $campaign) {
            $comparison[$campaign->id] = [
                'name' => $campaign->name,
                'total_contacts' => $campaign->total_contacts,
                'delivery_rate' => $campaign->getDeliveryRate(),
                'open_rate' => $campaign->getOpenRate(),
                'channels' => $campaign->channels,
                'status' => $campaign->status->value ?? 'unknown',
                'created_at' => $campaign->created_at,
            ];
        }

        return $comparison;
    }

    /**
     * Get optimal send hours for a user/channel
     */
    public function getOptimalSendHours(int $userId, string $channel = 'email'): array
    {
        $hours = [];
        for ($i = 0; $i < 24; $i++) {
            $hours[$i] = 0;
        }

        $dispatches = CampaignDispatch::whereHas('campaign', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->where('channel', $channel)
            ->whereNotNull('sent_at')
            ->whereIn('status', ['delivered', 'opened', 'clicked', 'replied'])
            ->get();

        $sentByHour = [];
        $openedByHour = [];

        foreach ($dispatches as $dispatch) {
            $hour = Carbon::parse($dispatch->sent_at)->hour;
            $sentByHour[$hour] = ($sentByHour[$hour] ?? 0) + 1;

            if (in_array($dispatch->status->value ?? '', ['opened', 'clicked', 'replied'])) {
                $openedByHour[$hour] = ($openedByHour[$hour] ?? 0) + 1;
            }
        }

        foreach ($hours as $hour => &$score) {
            if (isset($sentByHour[$hour]) && $sentByHour[$hour] > 0) {
                $score = round((($openedByHour[$hour] ?? 0) / $sentByHour[$hour]) * 100, 1);
            }
        }

        return $hours;
    }

    /**
     * Get optimal send days for a user/channel
     */
    public function getOptimalSendDays(int $userId, string $channel = 'email'): array
    {
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $days[$i] = 0;
        }

        $dispatches = CampaignDispatch::whereHas('campaign', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->where('channel', $channel)
            ->whereNotNull('sent_at')
            ->whereIn('status', ['delivered', 'opened', 'clicked', 'replied'])
            ->get();

        $sentByDay = [];
        $openedByDay = [];

        foreach ($dispatches as $dispatch) {
            $day = Carbon::parse($dispatch->sent_at)->dayOfWeek;
            $sentByDay[$day] = ($sentByDay[$day] ?? 0) + 1;

            if (in_array($dispatch->status->value ?? '', ['opened', 'clicked', 'replied'])) {
                $openedByDay[$day] = ($openedByDay[$day] ?? 0) + 1;
            }
        }

        foreach ($days as $day => &$score) {
            if (isset($sentByDay[$day]) && $sentByDay[$day] > 0) {
                $score = round((($openedByDay[$day] ?? 0) / $sentByDay[$day]) * 100, 1);
            }
        }

        return $days;
    }

    /**
     * Get campaign comparison data
     */
    public function getCampaignComparisonData(UnifiedCampaign $campaign): array
    {
        $dispatches = $campaign->dispatches;
        $sent = $dispatches->filter(fn($d) => $d->sent_at)->count();
        $delivered = $dispatches->filter(fn($d) =>
            in_array($d->status->value ?? '', ['delivered', 'opened', 'clicked', 'replied'])
        )->count();
        $opened = $dispatches->filter(fn($d) =>
            in_array($d->status->value ?? '', ['opened', 'clicked', 'replied'])
        )->count();
        $clicked = $dispatches->filter(fn($d) =>
            in_array($d->status->value ?? '', ['clicked', 'replied'])
        )->count();
        $bounced = $dispatches->filter(fn($d) => $d->status->value === 'bounced')->count();

        return [
            'name' => $campaign->name,
            'total_contacts' => $campaign->total_contacts,
            'processed' => $campaign->processed_contacts,
            'sent' => $sent,
            'delivered' => $delivered,
            'delivery_rate' => $sent > 0 ? round(($delivered / $sent) * 100, 1) : 0,
            'open_rate' => $delivered > 0 ? round(($opened / $delivered) * 100, 1) : 0,
            'click_rate' => $delivered > 0 ? round(($clicked / $delivered) * 100, 1) : 0,
            'bounce_rate' => $sent > 0 ? round(($bounced / $sent) * 100, 1) : 0,
            'channels' => $campaign->channels ?? [],
            'status' => $campaign->status->value ?? 'unknown',
            'created_at' => $campaign->created_at,
        ];
    }
}
