<?php

namespace App\Jobs;

use App\Models\Gateway;
use App\Enums\System\ChannelTypeEnum;
use App\Enums\System\Gateway\WhatsAppGatewayTypeEnum;
use App\Services\WhatsApp\HealthCheckService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunGatewayHealthChecksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $backoff = 60; // 1 minute between retries

    protected ?string $gatewayType;
    protected ?int $gatewayId;

    /**
     * Create a new job instance.
     *
     * @param string|null $gatewayType 'cloud' or 'node', null for all
     * @param int|null $gatewayId Specific gateway to check, or null for all
     */
    public function __construct(?string $gatewayType = null, ?int $gatewayId = null)
    {
        $this->gatewayType = $gatewayType;
        $this->gatewayId = $gatewayId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $healthService = new HealthCheckService();

        if ($this->gatewayId) {
            // Check specific gateway
            $gateway = Gateway::where('channel', ChannelTypeEnum::WHATSAPP->value)
                ->where('id', $this->gatewayId)
                ->first();

            if ($gateway) {
                $this->checkGateway($healthService, $gateway);
            }
        } else {
            // Get gateways based on type filter
            $query = Gateway::where('channel', ChannelTypeEnum::WHATSAPP->value)
                ->where('status', 'connected');

            if ($this->gatewayType === 'cloud') {
                $query->where('type', WhatsAppGatewayTypeEnum::CLOUD->value);
            } elseif ($this->gatewayType === 'node') {
                $query->where('type', WhatsAppGatewayTypeEnum::NODE->value);
            }

            // Prioritize gateways that haven't been checked recently
            $gateways = $query->orderByRaw('last_health_check_at IS NULL DESC')
                ->orderBy('last_health_check_at', 'asc')
                ->limit(50) // Process in batches to avoid timeout
                ->get();

            foreach ($gateways as $gateway) {
                $this->checkGateway($healthService, $gateway);
            }
        }
    }

    /**
     * Check a specific gateway's health
     */
    protected function checkGateway(HealthCheckService $healthService, Gateway $gateway): void
    {
        try {
            $result = $healthService->checkGateway($gateway);

            if ($result['success']) {
                Log::debug("Health check passed for gateway: {$gateway->name}", [
                    'status' => $result['health_status'] ?? 'unknown'
                ]);
            } else {
                Log::warning("Health check warning for gateway: {$gateway->name}", [
                    'message' => $result['message'] ?? 'Unknown issue'
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Error checking gateway health: {$gateway->name}", [
                'error' => $e->getMessage()
            ]);

            // Update gateway with error status
            $gateway->update([
                'health_status' => 'unhealthy',
                'last_health_check_at' => now(),
                'consecutive_failures' => ($gateway->consecutive_failures ?? 0) + 1,
            ]);
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("RunGatewayHealthChecksJob failed", [
            'gateway_type' => $this->gatewayType,
            'gateway_id' => $this->gatewayId,
            'error' => $exception->getMessage()
        ]);
    }
}
