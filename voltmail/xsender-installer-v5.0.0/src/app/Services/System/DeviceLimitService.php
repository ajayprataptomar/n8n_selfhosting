<?php

namespace App\Services\System;

use App\Models\Gateway;
use App\Models\User;
use App\Models\PricingPlan;
use App\Models\Subscription;
use App\Models\AndroidSession;
use App\Enums\System\ChannelTypeEnum;
use App\Enums\System\SessionStatusEnum;
use App\Enums\Common\Status;
use App\Service\Admin\Gateway\WhatsappGatewayService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * DeviceLimitService - Enterprise-level device management for subscription changes
 *
 * Handles smart device activation/deactivation based on plan limits:
 * - Upgrade: No devices deactivated (user gains more capacity)
 * - Downgrade: Keep most recently active devices within new limit
 * - Renewal: Preserve all existing device connections
 * - Expiry: Optionally deactivate devices based on configuration
 *
 * @package App\Services\System
 * @version 1.0.0
 */
class DeviceLimitService
{
    protected ?WhatsappGatewayService $whatsappGatewayService = null;

    /**
     * Plan change types for better tracking and logging
     */
    const CHANGE_TYPE_UPGRADE = 'upgrade';
    const CHANGE_TYPE_DOWNGRADE = 'downgrade';
    const CHANGE_TYPE_RENEWAL = 'renewal';
    const CHANGE_TYPE_SAME = 'same';
    const CHANGE_TYPE_FIRST_SUBSCRIPTION = 'first_subscription';
    const CHANGE_TYPE_ADMIN_ASSIGN = 'admin_assign';

    /**
     * Get WhatsApp gateway service instance (lazy loading)
     */
    protected function getWhatsappGatewayService(): WhatsappGatewayService
    {
        if (!$this->whatsappGatewayService) {
            $this->whatsappGatewayService = new WhatsappGatewayService();
        }
        return $this->whatsappGatewayService;
    }

    /**
     * Handle device limits when subscription changes
     *
     * This is the main entry point for subscription changes.
     * It intelligently manages devices based on old vs new plan limits.
     *
     * @param User $user The user whose subscription is changing
     * @param PricingPlan|null $oldPlan The previous plan (null for first subscription)
     * @param PricingPlan $newPlan The new plan being applied
     * @param string $changeReason Reason for change (payment, admin, renewal, etc.)
     * @return array Summary of actions taken
     */
    public function handleSubscriptionChange(
        User $user,
        ?PricingPlan $oldPlan,
        PricingPlan $newPlan,
        string $changeReason = 'subscription_change'
    ): array {
        $summary = [
            'user_id' => $user->id,
            'old_plan' => $oldPlan?->name ?? 'None',
            'new_plan' => $newPlan->name,
            'change_type' => $this->determineChangeType($oldPlan, $newPlan),
            'change_reason' => $changeReason,
            'actions' => [],
            'whatsapp' => ['kept' => 0, 'deactivated' => 0],
            'sms' => ['kept' => 0, 'deactivated' => 0],
            'email' => ['kept' => 0, 'deactivated' => 0],
            'android' => ['kept' => 0, 'deactivated' => 0],
        ];

        try {
            DB::beginTransaction();

            // Handle WhatsApp devices
            $whatsappResult = $this->handleChannelDevices(
                $user,
                ChannelTypeEnum::WHATSAPP,
                $oldPlan?->whatsapp?->gateway_limit ?? 0,
                $newPlan->whatsapp?->gateway_limit ?? 0,
                $summary['change_type']
            );
            $summary['whatsapp'] = $whatsappResult;
            $summary['actions'] = array_merge($summary['actions'], $whatsappResult['actions'] ?? []);

            // Handle SMS gateways
            $smsResult = $this->handleChannelDevices(
                $user,
                ChannelTypeEnum::SMS,
                $oldPlan?->sms?->gateway_limit ?? 0,
                $newPlan->sms?->gateway_limit ?? 0,
                $summary['change_type']
            );
            $summary['sms'] = $smsResult;
            $summary['actions'] = array_merge($summary['actions'], $smsResult['actions'] ?? []);

            // Handle Email gateways
            $emailResult = $this->handleChannelDevices(
                $user,
                ChannelTypeEnum::EMAIL,
                $oldPlan?->email?->gateway_limit ?? 0,
                $newPlan->email?->gateway_limit ?? 0,
                $summary['change_type']
            );
            $summary['email'] = $emailResult;
            $summary['actions'] = array_merge($summary['actions'], $emailResult['actions'] ?? []);

            // Handle Android sessions (SMS Android)
            $androidResult = $this->handleAndroidSessions(
                $user,
                $oldPlan?->sms?->android?->gateway_limit ?? 0,
                $newPlan->sms?->android?->gateway_limit ?? 0,
                $summary['change_type']
            );
            $summary['android'] = $androidResult;
            $summary['actions'] = array_merge($summary['actions'], $androidResult['actions'] ?? []);

            DB::commit();

            // Log the summary
            Log::info('DeviceLimitService: Subscription change processed', $summary);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('DeviceLimitService: Error processing subscription change', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }

        return $summary;
    }

    /**
     * Determine the type of plan change
     *
     * @param PricingPlan|null $oldPlan
     * @param PricingPlan $newPlan
     * @return string
     */
    public function determineChangeType(?PricingPlan $oldPlan, PricingPlan $newPlan): string
    {
        if (!$oldPlan) {
            return self::CHANGE_TYPE_FIRST_SUBSCRIPTION;
        }

        // For renewal detection, check if both plans have IDs and they match
        // If both have IDs, compare them. If either doesn't have an ID, compare by limits
        if ($oldPlan->id && $newPlan->id && $oldPlan->id === $newPlan->id) {
            return self::CHANGE_TYPE_RENEWAL;
        }

        // Compare total gateway limits to determine upgrade vs downgrade
        $oldTotal = $this->calculateTotalLimits($oldPlan);
        $newTotal = $this->calculateTotalLimits($newPlan);

        // Also compare by plan amount as a secondary indicator
        $oldAmount = (float) ($oldPlan->amount ?? 0);
        $newAmount = (float) ($newPlan->amount ?? 0);

        if ($newTotal > $oldTotal) {
            return self::CHANGE_TYPE_UPGRADE;
        } elseif ($newTotal < $oldTotal) {
            return self::CHANGE_TYPE_DOWNGRADE;
        } elseif ($newAmount > $oldAmount) {
            return self::CHANGE_TYPE_UPGRADE;
        } elseif ($newAmount < $oldAmount) {
            return self::CHANGE_TYPE_DOWNGRADE;
        }

        return self::CHANGE_TYPE_SAME;
    }

    /**
     * Calculate total gateway limits for a plan
     * -1 (unlimited) is treated as a very high number for comparison
     *
     * @param PricingPlan $plan
     * @return int
     */
    protected function calculateTotalLimits(PricingPlan $plan): int
    {
        $total = 0;
        $unlimited = 10000; // Treat unlimited as high number for comparison

        $whatsappLimit = $plan->whatsapp?->gateway_limit ?? 0;
        $total += ($whatsappLimit == -1) ? $unlimited : $whatsappLimit;

        $smsLimit = $plan->sms?->gateway_limit ?? 0;
        $total += ($smsLimit == -1) ? $unlimited : $smsLimit;

        $emailLimit = $plan->email?->gateway_limit ?? 0;
        $total += ($emailLimit == -1) ? $unlimited : $emailLimit;

        $androidLimit = $plan->sms?->android?->gateway_limit ?? 0;
        $total += ($androidLimit == -1) ? $unlimited : $androidLimit;

        return $total;
    }

    /**
     * Handle device limits for a specific channel
     *
     * @param User $user
     * @param ChannelTypeEnum $channel
     * @param int $oldLimit (-1 = unlimited, 0 = none)
     * @param int $newLimit (-1 = unlimited, 0 = none)
     * @param string $changeType
     * @return array
     */
    protected function handleChannelDevices(
        User $user,
        ChannelTypeEnum $channel,
        int $oldLimit,
        int $newLimit,
        string $changeType
    ): array {
        $result = [
            'kept' => 0,
            'deactivated' => 0,
            'actions' => []
        ];

        // Get all active devices for this channel
        $activeDevices = Gateway::where('user_id', $user->id)
            ->where('channel', $channel->value)
            ->where('status', Status::ACTIVE->value)
            ->orderByDesc('updated_at') // Most recently used first
            ->get();

        $activeCount = $activeDevices->count();
        $result['kept'] = $activeCount;

        // If new limit is unlimited (-1) or no active devices, keep all
        if ($newLimit == -1 || $activeCount == 0) {
            $result['actions'][] = "Channel {$channel->value}: Kept all {$activeCount} devices (new limit: unlimited)";
            return $result;
        }

        // If new limit is 0 (not allowed), deactivate all
        if ($newLimit == 0) {
            $this->deactivateGateways($activeDevices, $channel);
            $result['deactivated'] = $activeCount;
            $result['kept'] = 0;
            $result['actions'][] = "Channel {$channel->value}: Deactivated all {$activeCount} devices (service not allowed in new plan)";
            return $result;
        }

        // For upgrades and renewals, keep all existing devices (they're within old limit)
        if (in_array($changeType, [self::CHANGE_TYPE_UPGRADE, self::CHANGE_TYPE_RENEWAL, self::CHANGE_TYPE_SAME])) {
            $result['actions'][] = "Channel {$channel->value}: Kept all {$activeCount} devices ({$changeType})";
            return $result;
        }

        // For downgrades, check if we need to deactivate excess devices
        if ($activeCount > $newLimit) {
            $devicesToKeep = $activeDevices->take($newLimit);
            $devicesToDeactivate = $activeDevices->slice($newLimit);

            $this->deactivateGateways($devicesToDeactivate, $channel);

            $result['kept'] = $devicesToKeep->count();
            $result['deactivated'] = $devicesToDeactivate->count();
            $result['actions'][] = "Channel {$channel->value}: Kept {$result['kept']} most recent devices, deactivated {$result['deactivated']} (limit: {$newLimit})";
        } else {
            $result['actions'][] = "Channel {$channel->value}: Kept all {$activeCount} devices (within new limit: {$newLimit})";
        }

        return $result;
    }

    /**
     * Handle Android session limits
     *
     * @param User $user
     * @param int $oldLimit
     * @param int $newLimit
     * @param string $changeType
     * @return array
     */
    protected function handleAndroidSessions(
        User $user,
        int $oldLimit,
        int $newLimit,
        string $changeType
    ): array {
        $result = [
            'kept' => 0,
            'deactivated' => 0,
            'actions' => []
        ];

        // Get all connected Android sessions
        $activeSessions = AndroidSession::where('user_id', $user->id)
            ->where('status', SessionStatusEnum::CONNECTED)
            ->orderByDesc('updated_at')
            ->get();

        $activeCount = $activeSessions->count();
        $result['kept'] = $activeCount;

        // If new limit is unlimited (-1) or no active sessions, keep all
        if ($newLimit == -1 || $activeCount == 0) {
            $result['actions'][] = "Android: Kept all {$activeCount} sessions (new limit: unlimited)";
            return $result;
        }

        // If new limit is 0 (not allowed), disconnect all
        if ($newLimit == 0) {
            AndroidSession::where('user_id', $user->id)
                ->where('status', SessionStatusEnum::CONNECTED)
                ->update(['status' => SessionStatusEnum::DISCONNECTED]);

            $result['deactivated'] = $activeCount;
            $result['kept'] = 0;
            $result['actions'][] = "Android: Disconnected all {$activeCount} sessions (service not allowed in new plan)";
            return $result;
        }

        // For upgrades and renewals, keep all
        if (in_array($changeType, [self::CHANGE_TYPE_UPGRADE, self::CHANGE_TYPE_RENEWAL, self::CHANGE_TYPE_SAME])) {
            $result['actions'][] = "Android: Kept all {$activeCount} sessions ({$changeType})";
            return $result;
        }

        // For downgrades, deactivate excess
        if ($activeCount > $newLimit) {
            $sessionsToKeep = $activeSessions->take($newLimit);
            $sessionsToDeactivate = $activeSessions->slice($newLimit);

            $sessionIds = $sessionsToDeactivate->pluck('id')->toArray();
            AndroidSession::whereIn('id', $sessionIds)
                ->update(['status' => SessionStatusEnum::DISCONNECTED]);

            $result['kept'] = $sessionsToKeep->count();
            $result['deactivated'] = $sessionsToDeactivate->count();
            $result['actions'][] = "Android: Kept {$result['kept']} most recent sessions, disconnected {$result['deactivated']} (limit: {$newLimit})";
        } else {
            $result['actions'][] = "Android: Kept all {$activeCount} sessions (within new limit: {$newLimit})";
        }

        return $result;
    }

    /**
     * Deactivate gateway devices
     *
     * @param \Illuminate\Support\Collection $gateways
     * @param ChannelTypeEnum $channel
     * @return void
     */
    protected function deactivateGateways($gateways, ChannelTypeEnum $channel): void
    {
        foreach ($gateways as $gateway) {
            $gateway->status = Status::INACTIVE->value;
            $gateway->is_default = 0;

            // For WhatsApp, also disconnect the session from the server
            if ($channel === ChannelTypeEnum::WHATSAPP) {
                try {
                    $whatsappService = $this->getWhatsappGatewayService();
                    if ($whatsappService->checkServerStatus()) {
                        $whatsappService->sessionDelete($gateway->name);
                    }
                } catch (\Exception $e) {
                    Log::warning('DeviceLimitService: Failed to disconnect WhatsApp session', [
                        'gateway_id' => $gateway->id,
                        'name' => $gateway->name,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $gateway->save();
        }
    }

    /**
     * Get current device counts for a user
     *
     * @param User $user
     * @return array
     */
    public function getDeviceCounts(User $user): array
    {
        return [
            'whatsapp' => Gateway::where('user_id', $user->id)
                ->where('channel', ChannelTypeEnum::WHATSAPP->value)
                ->where('status', Status::ACTIVE->value)
                ->count(),
            'sms' => Gateway::where('user_id', $user->id)
                ->where('channel', ChannelTypeEnum::SMS->value)
                ->where('status', Status::ACTIVE->value)
                ->count(),
            'email' => Gateway::where('user_id', $user->id)
                ->where('channel', ChannelTypeEnum::EMAIL->value)
                ->where('status', Status::ACTIVE->value)
                ->count(),
            'android' => AndroidSession::where('user_id', $user->id)
                ->where('status', SessionStatusEnum::CONNECTED)
                ->count(),
        ];
    }

    /**
     * Check if user can add more devices for a channel
     *
     * @param User $user
     * @param ChannelTypeEnum $channel
     * @return array ['allowed' => bool, 'current' => int, 'limit' => int, 'message' => string]
     */
    public function canAddDevice(User $user, ChannelTypeEnum $channel): array
    {
        $subscription = $user->runningSubscription();
        if (!$subscription) {
            return [
                'allowed' => false,
                'current' => 0,
                'limit' => 0,
                'message' => translate('No active subscription')
            ];
        }

        $plan = $subscription->currentPlan();
        if (!$plan) {
            return [
                'allowed' => false,
                'current' => 0,
                'limit' => 0,
                'message' => translate('No plan found')
            ];
        }

        $limit = match($channel) {
            ChannelTypeEnum::WHATSAPP => $plan->whatsapp?->gateway_limit ?? 0,
            ChannelTypeEnum::SMS => $plan->sms?->gateway_limit ?? 0,
            ChannelTypeEnum::EMAIL => $plan->email?->gateway_limit ?? 0,
        };

        $current = Gateway::where('user_id', $user->id)
            ->where('channel', $channel->value)
            ->where('status', Status::ACTIVE->value)
            ->count();

        // -1 means unlimited
        if ($limit == -1) {
            return [
                'allowed' => true,
                'current' => $current,
                'limit' => -1,
                'message' => translate('Unlimited devices allowed')
            ];
        }

        // 0 means not allowed
        if ($limit == 0) {
            return [
                'allowed' => false,
                'current' => $current,
                'limit' => 0,
                'message' => translate('This service is not included in your plan')
            ];
        }

        $allowed = $current < $limit;
        return [
            'allowed' => $allowed,
            'current' => $current,
            'limit' => $limit,
            'message' => $allowed
                ? translate("You can add") . ' ' . ($limit - $current) . ' ' . translate("more device(s)")
                : translate("Device limit reached. Your plan allows") . ' ' . $limit . ' ' . translate("device(s)")
        ];
    }

    /**
     * Handle subscription expiration
     * By default, we don't deactivate devices immediately on expiry
     * This can be configured based on business requirements
     *
     * @param User $user
     * @param bool $deactivateDevices Whether to deactivate devices on expiry
     * @return array
     */
    public function handleSubscriptionExpiry(User $user, bool $deactivateDevices = false): array
    {
        $result = [
            'user_id' => $user->id,
            'action' => 'expiry',
            'devices_deactivated' => false,
            'message' => ''
        ];

        if (!$deactivateDevices) {
            $result['message'] = 'Subscription expired - devices kept active for grace period';
            Log::info('DeviceLimitService: Subscription expired, devices kept', ['user_id' => $user->id]);
            return $result;
        }

        // If configured to deactivate on expiry
        try {
            DB::beginTransaction();

            // Deactivate all user gateways
            Gateway::where('user_id', $user->id)
                ->where('status', Status::ACTIVE->value)
                ->update([
                    'status' => Status::INACTIVE->value,
                    'is_default' => 0
                ]);

            // Disconnect Android sessions
            AndroidSession::where('user_id', $user->id)
                ->where('status', SessionStatusEnum::CONNECTED)
                ->update(['status' => SessionStatusEnum::DISCONNECTED]);

            DB::commit();

            $result['devices_deactivated'] = true;
            $result['message'] = 'Subscription expired - all devices deactivated';
            Log::info('DeviceLimitService: Subscription expired, devices deactivated', ['user_id' => $user->id]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('DeviceLimitService: Error handling subscription expiry', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }

        return $result;
    }

    /**
     * Reactivate devices after subscription renewal (if they were deactivated on expiry)
     *
     * @param User $user
     * @param PricingPlan $plan
     * @return array
     */
    public function reactivateDevicesAfterRenewal(User $user, PricingPlan $plan): array
    {
        $result = [
            'user_id' => $user->id,
            'reactivated' => [
                'whatsapp' => 0,
                'sms' => 0,
                'email' => 0,
                'android' => 0
            ]
        ];

        // Reactivate devices up to the plan limits
        foreach ([ChannelTypeEnum::WHATSAPP, ChannelTypeEnum::SMS, ChannelTypeEnum::EMAIL] as $channel) {
            $limit = match($channel) {
                ChannelTypeEnum::WHATSAPP => $plan->whatsapp?->gateway_limit ?? 0,
                ChannelTypeEnum::SMS => $plan->sms?->gateway_limit ?? 0,
                ChannelTypeEnum::EMAIL => $plan->email?->gateway_limit ?? 0,
            };

            if ($limit == 0) continue;

            $query = Gateway::where('user_id', $user->id)
                ->where('channel', $channel->value)
                ->where('status', Status::INACTIVE->value)
                ->orderByDesc('updated_at');

            if ($limit > 0) {
                $query->limit($limit);
            }

            $count = $query->update(['status' => Status::ACTIVE->value]);
            $result['reactivated'][strtolower($channel->value)] = $count;
        }

        // Reactivate Android sessions
        $androidLimit = $plan->sms?->android?->gateway_limit ?? 0;
        if ($androidLimit != 0) {
            $query = AndroidSession::where('user_id', $user->id)
                ->where('status', SessionStatusEnum::DISCONNECTED)
                ->orderByDesc('updated_at');

            if ($androidLimit > 0) {
                $query->limit($androidLimit);
            }

            $count = $query->update(['status' => SessionStatusEnum::CONNECTED]);
            $result['reactivated']['android'] = $count;
        }

        Log::info('DeviceLimitService: Devices reactivated after renewal', $result);
        return $result;
    }
}
