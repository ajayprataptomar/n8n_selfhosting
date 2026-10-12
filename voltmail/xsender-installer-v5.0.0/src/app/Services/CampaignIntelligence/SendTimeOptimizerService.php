<?php

namespace App\Services\CampaignIntelligence;

use App\Models\Contact;
use App\Models\ContactEngagement;
use App\Models\CampaignDispatch;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SendTimeOptimizerService
{
    /**
     * Default optimal hours when no data is available
     */
    protected array $defaultOptimalHours = [
        9 => 0.15,   // 9 AM
        10 => 0.20,  // 10 AM
        11 => 0.15,  // 11 AM
        14 => 0.15,  // 2 PM
        15 => 0.15,  // 3 PM
        16 => 0.10,  // 4 PM
        19 => 0.10,  // 7 PM
    ];

    /**
     * Default optimal days when no data is available
     */
    protected array $defaultOptimalDays = [
        1 => 0.18, // Monday
        2 => 0.20, // Tuesday
        3 => 0.20, // Wednesday
        4 => 0.18, // Thursday
        5 => 0.14, // Friday
        6 => 0.05, // Saturday
        0 => 0.05, // Sunday
    ];

    /**
     * Get optimal send time for a contact
     */
    public function getOptimalSendTime(Contact $contact, string $channel = 'email'): Carbon
    {
        $engagement = ContactEngagement::where('contact_id', $contact->id)
            ->where('channel', $channel)
            ->first();

        if ($engagement && $engagement->optimal_hours && $engagement->optimal_days) {
            return $this->calculateOptimalTime($engagement);
        }

        // Fall back to default optimal times
        return $this->getDefaultOptimalTime($contact);
    }

    /**
     * Calculate optimal time based on engagement data
     */
    protected function calculateOptimalTime(ContactEngagement $engagement): Carbon
    {
        $optimalHours = $engagement->optimal_hours ?? $this->defaultOptimalHours;
        $optimalDays = $engagement->optimal_days ?? $this->defaultOptimalDays;
        $timezone = $engagement->detected_timezone ?? config('app.timezone', 'UTC');

        // Find best day
        $bestDay = array_keys($optimalDays, max($optimalDays))[0];

        // Find best hour
        $bestHour = array_keys($optimalHours, max($optimalHours))[0];

        // Calculate next occurrence of this day/hour
        $now = Carbon::now($timezone);
        $targetDate = $now->copy();

        // Find the next occurrence of the best day
        while ($targetDate->dayOfWeek !== (int)$bestDay) {
            $targetDate->addDay();
        }

        // Set the best hour
        $targetDate->setTime((int)$bestHour, 0, 0);

        // If the time has already passed today, move to next week
        if ($targetDate->isPast()) {
            $targetDate->addWeek();
        }

        return $targetDate->setTimezone('UTC');
    }

    /**
     * Get default optimal time
     */
    protected function getDefaultOptimalTime(Contact $contact): Carbon
    {
        $now = Carbon::now();

        // Default to Tuesday at 10 AM in contact's timezone
        $timezone = $this->detectContactTimezone($contact);
        $target = Carbon::now($timezone)->next(Carbon::TUESDAY)->setTime(10, 0, 0);

        if ($target->isPast()) {
            $target->addWeek();
        }

        return $target->setTimezone('UTC');
    }

    /**
     * Detect contact timezone from phone number
     */
    public function detectContactTimezone(Contact $contact): string
    {
        $phone = $contact->sms_contact ?? $contact->whatsapp_contact ?? '';

        // Common country code to timezone mapping
        $timezoneMap = [
            '1' => 'America/New_York',      // USA/Canada
            '44' => 'Europe/London',         // UK
            '91' => 'Asia/Kolkata',          // India
            '880' => 'Asia/Dhaka',           // Bangladesh
            '971' => 'Asia/Dubai',           // UAE
            '966' => 'Asia/Riyadh',          // Saudi Arabia
            '61' => 'Australia/Sydney',      // Australia
            '81' => 'Asia/Tokyo',            // Japan
            '86' => 'Asia/Shanghai',         // China
            '49' => 'Europe/Berlin',         // Germany
            '33' => 'Europe/Paris',          // France
        ];

        foreach ($timezoneMap as $code => $timezone) {
            if (str_starts_with($phone, $code) || str_starts_with($phone, '+' . $code)) {
                return $timezone;
            }
        }

        return config('app.timezone', 'UTC');
    }

    /**
     * Analyze contact engagement and update optimal times
     */
    public function analyzeContactEngagement(Contact $contact, string $channel): ContactEngagement
    {
        // Get all dispatches for this contact
        $dispatches = CampaignDispatch::where('contact_id', $contact->id)
            ->where('channel', $channel)
            ->whereNotNull('sent_at')
            ->get();

        // Initialize engagement data
        $hourlyEngagement = array_fill(0, 24, 0);
        $hourlyTotal = array_fill(0, 24, 0);
        $dailyEngagement = array_fill(0, 7, 0);
        $dailyTotal = array_fill(0, 7, 0);

        $totalSent = 0;
        $totalDelivered = 0;
        $totalOpened = 0;
        $totalClicked = 0;
        $totalReplied = 0;
        $totalBounced = 0;
        $lastEngagementAt = null;

        foreach ($dispatches as $dispatch) {
            $sentAt = Carbon::parse($dispatch->sent_at);
            $hour = $sentAt->hour;
            $day = $sentAt->dayOfWeek;
            $status = $dispatch->status->value ?? 'pending';

            $hourlyTotal[$hour]++;
            $dailyTotal[$day]++;
            $totalSent++;

            $engaged = in_array($status, ['opened', 'clicked', 'replied']);

            if ($engaged) {
                $hourlyEngagement[$hour]++;
                $dailyEngagement[$day]++;
                $lastEngagementAt = $dispatch->delivered_at ?? $dispatch->sent_at;
            }

            if (in_array($status, ['delivered', 'opened', 'clicked', 'replied'])) {
                $totalDelivered++;
            }
            if (in_array($status, ['opened', 'clicked', 'replied'])) {
                $totalOpened++;
            }
            if (in_array($status, ['clicked', 'replied'])) {
                $totalClicked++;
            }
            if ($status === 'replied') {
                $totalReplied++;
            }
            if ($status === 'bounced') {
                $totalBounced++;
            }
        }

        // Calculate optimal hours (engagement rate per hour)
        $optimalHours = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $optimalHours[$hour] = $hourlyTotal[$hour] > 0
                ? round($hourlyEngagement[$hour] / $hourlyTotal[$hour], 3)
                : 0;
        }

        // Calculate optimal days (engagement rate per day)
        $optimalDays = [];
        for ($day = 0; $day < 7; $day++) {
            $optimalDays[$day] = $dailyTotal[$day] > 0
                ? round($dailyEngagement[$day] / $dailyTotal[$day], 3)
                : 0;
        }

        // Calculate engagement score (0-100)
        $engagementScore = $totalSent > 0
            ? min(100, round(($totalOpened / $totalSent) * 100 * 1.5, 2))
            : 0;

        // Create or update engagement record
        return ContactEngagement::updateOrCreate(
            ['contact_id' => $contact->id, 'channel' => $channel],
            [
                'user_id' => $contact->user_id,
                'engagement_score' => $engagementScore,
                'detected_timezone' => $this->detectContactTimezone($contact),
                'optimal_hours' => $optimalHours,
                'optimal_days' => $optimalDays,
                'total_sent' => $totalSent,
                'total_delivered' => $totalDelivered,
                'total_opened' => $totalOpened,
                'total_clicked' => $totalClicked,
                'total_replied' => $totalReplied,
                'total_bounced' => $totalBounced,
                'last_engagement_at' => $lastEngagementAt,
                'analyzed_at' => now(),
            ]
        );
    }

    /**
     * Batch analyze contacts for a user
     */
    public function batchAnalyzeContacts(int $userId, string $channel, int $limit = 1000): int
    {
        $contacts = Contact::where('user_id', $userId)
            ->whereHas('dispatches', function ($query) use ($channel) {
                $query->where('channel', $channel);
            })
            ->limit($limit)
            ->get();

        $processed = 0;
        foreach ($contacts as $contact) {
            $this->analyzeContactEngagement($contact, $channel);
            $processed++;
        }

        return $processed;
    }

    /**
     * Get contacts sorted by optimal send time
     */
    public function getContactsSortedByOptimalTime(Collection $contacts, string $channel): Collection
    {
        return $contacts->map(function ($contact) use ($channel) {
            return [
                'contact' => $contact,
                'optimal_time' => $this->getOptimalSendTime($contact, $channel),
            ];
        })->sortBy('optimal_time')->pluck('contact');
    }

    /**
     * Group contacts by optimal send hour
     */
    public function groupContactsByOptimalHour(Collection $contacts, string $channel): array
    {
        $groups = [];

        foreach ($contacts as $contact) {
            $optimalTime = $this->getOptimalSendTime($contact, $channel);
            $hour = $optimalTime->hour;

            if (!isset($groups[$hour])) {
                $groups[$hour] = [];
            }
            $groups[$hour][] = $contact;
        }

        ksort($groups);
        return $groups;
    }

    /**
     * Get engagement statistics for a user
     */
    public function getUserEngagementStats(int $userId, string $channel = null): array
    {
        $query = ContactEngagement::where('user_id', $userId);

        if ($channel) {
            $query->where('channel', $channel);
        }

        $engagements = $query->get();

        return [
            'total_contacts' => $engagements->count(),
            'avg_engagement_score' => round($engagements->avg('engagement_score'), 2),
            'high_engagement' => $engagements->where('engagement_score', '>=', 70)->count(),
            'medium_engagement' => $engagements->whereBetween('engagement_score', [30, 70])->count(),
            'low_engagement' => $engagements->where('engagement_score', '<', 30)->count(),
            'total_sent' => $engagements->sum('total_sent'),
            'total_opened' => $engagements->sum('total_opened'),
            'avg_open_rate' => $engagements->sum('total_sent') > 0
                ? round(($engagements->sum('total_opened') / $engagements->sum('total_sent')) * 100, 2)
                : 0,
        ];
    }

    /**
     * Get best sending windows for a campaign
     */
    public function getBestSendingWindows(int $userId, string $channel): array
    {
        $engagements = ContactEngagement::where('user_id', $userId)
            ->where('channel', $channel)
            ->whereNotNull('optimal_hours')
            ->get();

        if ($engagements->isEmpty()) {
            return $this->getDefaultSendingWindows();
        }

        // Aggregate optimal hours across all contacts
        $aggregatedHours = array_fill(0, 24, 0);

        foreach ($engagements as $engagement) {
            $optimalHours = $engagement->optimal_hours ?? [];
            foreach ($optimalHours as $hour => $score) {
                $aggregatedHours[$hour] += $score;
            }
        }

        // Normalize and find top windows
        $totalScore = array_sum($aggregatedHours);
        if ($totalScore > 0) {
            foreach ($aggregatedHours as $hour => $score) {
                $aggregatedHours[$hour] = round($score / $totalScore, 3);
            }
        }

        // Get top 5 hours
        arsort($aggregatedHours);
        $topHours = array_slice($aggregatedHours, 0, 5, true);

        return [
            'all_hours' => $aggregatedHours,
            'best_hours' => array_keys($topHours),
            'hour_scores' => $topHours,
        ];
    }

    /**
     * Get default sending windows
     */
    protected function getDefaultSendingWindows(): array
    {
        return [
            'all_hours' => $this->defaultOptimalHours + array_fill(0, 24, 0),
            'best_hours' => [10, 9, 14, 11, 15],
            'hour_scores' => $this->defaultOptimalHours,
        ];
    }
}
