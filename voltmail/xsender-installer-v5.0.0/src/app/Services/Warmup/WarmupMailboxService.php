<?php

namespace App\Services\Warmup;

use App\Enums\Common\Status;
use App\Enums\System\ChannelTypeEnum;
use App\Exceptions\ApplicationException;
use App\Models\Gateway;
use App\Models\WarmupSchedule;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

class WarmupMailboxService
{
    /**
     * Get all active email gateways belonging to the authenticated tenant,
     * annotated with IMAP presence and any existing warmup schedule.
     */
    public function getEligibleGateways(int $userId): Collection
    {
        $gateways = Gateway::where('user_id', $userId)
            ->where('channel', ChannelTypeEnum::EMAIL->value)
            ->where('status', Status::ACTIVE->value)
            ->get();

        return $gateways->map(function (Gateway $gateway) use ($userId) {
            $gateway->has_imap = $this->hasImapConfig($gateway);
            $gateway->warmup_schedule = WarmupSchedule::where('user_id', $userId)
                ->where('gateway_id', $gateway->id)
                ->first();
            return $gateway;
        });
    }

    /**
     * Check if a gateway has complete IMAP configuration in meta_data
     */
    public function hasImapConfig(Gateway $gateway): bool
    {
        $meta = $gateway->meta_data ?? [];
        $imap = Arr::get($meta, 'imap');

        if (!is_array($imap)) {
            return false;
        }

        return !empty($imap['host']) &&
               !empty($imap['port']) &&
               !empty($imap['username']) &&
               !empty($imap['password']);
    }

    /**
     * Get decrypted IMAP credentials from gateway meta_data
     */
    public function getImapConfig(Gateway $gateway): ?array
    {
        $meta = $gateway->meta_data ?? [];
        $imap = Arr::get($meta, 'imap');

        if (!is_array($imap) || empty($imap['host'])) {
            return null;
        }

        $password = $imap['password'] ?? '';
        try {
            $decryptedPassword = Crypt::decryptString($password);
        } catch (\Exception $e) {
            // Fallback if stored unencrypted
            $decryptedPassword = $password;
        }

        return [
            'host'       => $imap['host'] ?? '',
            'port'       => (int) ($imap['port'] ?? 993),
            'encryption' => $imap['encryption'] ?? 'ssl',
            'username'   => $imap['username'] ?? '',
            'password'   => $decryptedPassword,
        ];
    }

    /**
     * Attach an existing configured gateway to a warmup schedule.
     * Enforces strict tenant isolation, validates existing credentials,
     * supplements missing IMAP if provided, and never asks for SMTP credentials again.
     *
     * @throws AuthorizationException If tenant does not own the gateway
     * @throws ApplicationException If gateway is invalid or unusable
     */
    public function attachWarmupToGateway(
        int $userId,
        int $gatewayId,
        array $scheduleSettings,
        ?array $imapSettings = null
    ): WarmupSchedule {
        $gateway = Gateway::find($gatewayId);

        if (!$gateway) {
            throw new ApplicationException('Selected email gateway was not found.');
        }

        // Strict Tenant Isolation: gateway must belong to authenticated user
        if ((int) $gateway->user_id !== (int) $userId) {
            throw new AuthorizationException('Unauthorized access to this email gateway.');
        }

        // Validate channel
        if ($gateway->channel !== ChannelTypeEnum::EMAIL && $gateway->channel->value !== ChannelTypeEnum::EMAIL->value) {
            throw new ApplicationException('Selected gateway is not an email gateway.');
        }

        // Validate existing SMTP credentials usability
        $this->validateUsableCredentials($gateway);

        // If IMAP settings are provided, save them into gateway meta_data without overwriting SMTP
        if (!empty($imapSettings) && !empty($imapSettings['host'])) {
            $this->saveImapConfigToGateway($gateway, $imapSettings);
        }

        // Create or update WarmupSchedule
        $schedule = WarmupSchedule::updateOrCreate(
            [
                'user_id'    => $userId,
                'gateway_id' => $gateway->id,
            ],
            [
                'daily_limit'         => (int) Arr::get($scheduleSettings, 'daily_limit', 40),
                'rampup_increment'    => (int) Arr::get($scheduleSettings, 'rampup_increment', 2),
                'current_daily_quota' => (int) Arr::get($scheduleSettings, 'starting_quota', 5),
                'reply_rate_percent'  => (int) Arr::get($scheduleSettings, 'reply_rate_percent', 30),
                'start_date'          => Arr::get($scheduleSettings, 'start_date', now()->toDateString()),
                'status'              => Arr::get($scheduleSettings, 'status', 'active'),
                'settings'            => Arr::get($scheduleSettings, 'settings', []),
            ]
        );

        return $schedule;
    }

    /**
     * Validate that existing gateway credentials are usable
     */
    public function validateUsableCredentials(Gateway $gateway): void
    {
        $meta = $gateway->meta_data ?? [];

        if (empty($meta)) {
            throw new ApplicationException('The selected email gateway has no saved credentials.');
        }

        // For SMTP gateways, verify host, port, username, password exist
        if ($gateway->type === 'smtp' || $gateway->type === 101 || $gateway->type === '101') {
            $required = ['host', 'port', 'username', 'password'];
            foreach ($required as $key) {
                if (empty($meta[$key])) {
                    throw new ApplicationException("Incomplete existing SMTP configuration: missing '{$key}'.");
                }
            }
        }
    }

    /**
     * Save IMAP configuration into existing gateway->meta_data
     */
    public function saveImapConfigToGateway(Gateway $gateway, array $imapSettings): void
    {
        $meta = $gateway->meta_data ?? [];

        $rawPassword = $imapSettings['password'] ?? '';
        $encryptedPassword = !empty($rawPassword) ? Crypt::encryptString($rawPassword) : ($meta['imap']['password'] ?? '');

        $meta['imap'] = [
            'host'       => $imapSettings['host'] ?? '',
            'port'       => (int) ($imapSettings['port'] ?? 993),
            'encryption' => $imapSettings['encryption'] ?? 'ssl',
            'username'   => $imapSettings['username'] ?? '',
            'password'   => $encryptedPassword,
            'updated_at' => now()->toIso8601String(),
        ];

        $gateway->meta_data = $meta;
        $gateway->save();
    }

    /**
     * Update an existing warmup schedule
     */
    public function updateSchedule(int $userId, int $scheduleId, array $data): WarmupSchedule
    {
        $schedule = WarmupSchedule::where('id', $scheduleId)
            ->where('user_id', $userId)
            ->first();

        if (!$schedule) {
            throw new AuthorizationException('Warmup schedule not found or unauthorized.');
        }

        $schedule->update([
            'daily_limit'        => (int) Arr::get($data, 'daily_limit', $schedule->daily_limit),
            'rampup_increment'   => (int) Arr::get($data, 'rampup_increment', $schedule->rampup_increment),
            'reply_rate_percent' => (int) Arr::get($data, 'reply_rate_percent', $schedule->reply_rate_percent),
            'status'             => Arr::get($data, 'status', $schedule->status),
        ]);

        return $schedule;
    }

    /**
     * Toggle status (active / paused)
     */
    public function toggleStatus(int $userId, int $scheduleId): WarmupSchedule
    {
        $schedule = WarmupSchedule::where('id', $scheduleId)
            ->where('user_id', $userId)
            ->first();

        if (!$schedule) {
            throw new AuthorizationException('Warmup schedule not found or unauthorized.');
        }

        $schedule->status = $schedule->status === 'active' ? 'paused' : 'active';
        $schedule->save();

        return $schedule;
    }

    /**
     * Detach / delete a warmup schedule
     */
    public function detachWarmup(int $userId, int $scheduleId): bool
    {
        $schedule = WarmupSchedule::where('id', $scheduleId)
            ->where('user_id', $userId)
            ->first();

        if (!$schedule) {
            throw new AuthorizationException('Warmup schedule not found or unauthorized.');
        }

        return (bool) $schedule->delete();
    }
}
