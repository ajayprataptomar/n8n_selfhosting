<?php

namespace App\Services\Warmup;

use App\Models\Gateway;
use App\Models\User;
use App\Models\WarmupDispatch;
use App\Models\WarmupSchedule;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WarmupSchedulerService
{
    /**
     * Process daily scheduling for all active warmup schedules.
     * Generates daily dispatch slots and advances ramp-up quotas.
     *
     * @param string|null $targetDate (YYYY-MM-DD, defaults to today)
     * @return array Summary of generated schedules and slots
     */
    public function scheduleDailyDispatches(?string $targetDate = null): array
    {
        $date = $targetDate ? Carbon::parse($targetDate)->toDateString() : Carbon::today()->toDateString();
        
        $activeSchedules = WarmupSchedule::with(['gateway', 'user'])
            ->where('status', 'active')
            ->get();

        $generatedCount = 0;
        $processedSchedules = 0;

        foreach ($activeSchedules as $schedule) {
            // Check gateway status
            $gatewayStatus = $schedule->gateway?->status instanceof \BackedEnum ? $schedule->gateway->status->value : (string) $schedule->gateway?->status;
            if (!$schedule->gateway || $gatewayStatus !== 'active') {
                continue;
            }

            $slots = $this->generateSlotsForSchedule($schedule, $date);
            $generatedCount += count($slots);
            $processedSchedules++;
        }

        return [
            'date'                => $date,
            'schedules_processed' => $processedSchedules,
            'slots_generated'     => $generatedCount,
        ];
    }

    /**
     * Generate dispatch slots for a specific schedule on a specific date.
     * Idempotent: If slots already exist for this date, does not duplicate.
     */
    public function generateSlotsForSchedule(WarmupSchedule $schedule, string $date): array
    {
        // 1. Check if slots already exist for this schedule on this date
        $existingCount = WarmupDispatch::where('warmup_schedule_id', $schedule->id)
            ->whereDate('dispatch_date', $date)
            ->count();

        if ($existingCount > 0) {
            return []; // Already generated for today
        }

        // 2. Calculate today's quota based on start date and rampup increment
        $startDate = $schedule->start_date ? Carbon::parse($schedule->start_date) : Carbon::parse($schedule->created_at)->startOfDay();
        $targetDate = Carbon::parse($date)->startOfDay();

        $dayIndex = max(1, (int) $startDate->diffInDays($targetDate) + 1);
        $dailyQuota = $schedule->calculateQuotaForDay($dayIndex, $schedule->current_daily_quota ?: 5);

        // Update current schedule metrics
        $schedule->update([
            'current_day'         => $dayIndex,
            'current_daily_quota' => $dailyQuota,
        ]);

        if ($dailyQuota <= 0) {
            return [];
        }

        // 3. Find eligible recipient peer mailboxes
        $peerRecipients = $this->getEligiblePeerRecipients($schedule);

        // 4. Distribute slots throughout the business hours (08:00 to 18:00) with jitter
        $slots = [];
        $windowStartMinutes = 8 * 60; // 08:00 AM
        $windowEndMinutes   = 18 * 60; // 06:00 PM
        $totalWindowMinutes = $windowEndMinutes - $windowStartMinutes;
        $intervalMinutes    = max(5, (int) floor($totalWindowMinutes / max(1, $dailyQuota)));

        DB::beginTransaction();
        try {
            for ($slotNumber = 1; $slotNumber <= $dailyQuota; $slotNumber++) {
                // Scheduled time for this slot with deterministic jitter
                $baseMinute = $windowStartMinutes + (($slotNumber - 1) * $intervalMinutes);
                $jitter     = ($slotNumber % 5) * 2 - 4; // between -4 and +4 minutes
                $slotMinute = max($windowStartMinutes, min($windowEndMinutes, $baseMinute + $jitter));

                $scheduledAt = Carbon::parse($date)->startOfDay()->addMinutes($slotMinute);

                // Select recipient from pool
                $recipientEmail = $this->selectRecipientForSlot($peerRecipients, $slotNumber, $schedule);

                $dispatch = WarmupDispatch::create([
                    'warmup_schedule_id' => $schedule->id,
                    'user_id'            => $schedule->user_id,
                    'gateway_id'         => $schedule->gateway_id,
                    'recipient_email'    => $recipientEmail,
                    'subject'            => 'Pending Content Generation', // Populated at dispatch time
                    'dispatch_date'      => $date,
                    'slot_number'        => $slotNumber,
                    'scheduled_at'       => $scheduledAt,
                    'status'             => WarmupDispatch::STATUS_PENDING,
                ]);

                $slots[] = $dispatch;
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $slots;
    }

    /**
     * Get pool of eligible peer recipient mailboxes.
     * Excludes the sender's own mailbox to ensure sender !== recipient.
     */
    protected function getEligiblePeerRecipients(WarmupSchedule $schedule): array
    {
        // 1. Look for other active gateways in the system belonging to other schedules
        $peerGateways = Gateway::where('channel', 'email')
            ->where('status', 'active')
            ->where('id', '!=', $schedule->gateway_id)
            ->whereNotNull('address')
            ->pluck('address')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (!empty($peerGateways)) {
            return $peerGateways;
        }

        // 2. Fallback: Configured seed/warmup addresses or default peer addresses
        $fallbackPeers = config('setting.warmup_peer_network', [
            'peer-inbox1@voltmail.io',
            'peer-inbox2@voltmail.io',
            'peer-inbox3@voltmail.io',
            'peer-inbox4@voltmail.io',
            'peer-inbox5@voltmail.io',
        ]);

        return array_values(array_filter($fallbackPeers, fn($addr) => $addr !== $schedule->gateway?->address));
    }

    /**
     * Deterministically select a recipient from the peer pool for a given slot.
     */
    protected function selectRecipientForSlot(array $peerRecipients, int $slotNumber, WarmupSchedule $schedule): string
    {
        if (empty($peerRecipients)) {
            return 'inbox-verifier@voltmail.io';
        }

        $index = ($schedule->id + $slotNumber) % count($peerRecipients);
        return $peerRecipients[$index];
    }
}
