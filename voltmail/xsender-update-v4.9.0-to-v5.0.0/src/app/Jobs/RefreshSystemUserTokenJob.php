<?php

namespace App\Jobs;

use App\Models\MetaConfiguration;
use App\Services\WhatsApp\SystemUserTokenService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RefreshSystemUserTokenJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 300; // 5 minutes between retries

    protected ?int $configurationId;

    /**
     * Create a new job instance.
     *
     * @param int|null $configurationId Specific configuration to refresh, or null for all
     */
    public function __construct(?int $configurationId = null)
    {
        $this->configurationId = $configurationId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Skip if MetaConfiguration table doesn't exist or has no records
        if (!\Illuminate\Support\Facades\Schema::hasTable('meta_configurations')
            || MetaConfiguration::count() === 0) {
            return;
        }

        $tokenService = new SystemUserTokenService();

        if ($this->configurationId) {
            // Refresh specific configuration
            $config = MetaConfiguration::find($this->configurationId);
            if ($config && $config->system_user_token) {
                $this->refreshToken($tokenService, $config);
            }
        } else {
            // Refresh all configurations with tokens expiring soon
            $configurations = MetaConfiguration::whereNotNull('system_user_token')
                ->where('status', 'active')
                ->where(function ($query) {
                    $query->whereNull('system_user_token_expires_at')
                        ->orWhere('system_user_token_expires_at', '<=', now()->addDays(7));
                })
                ->get();

            foreach ($configurations as $config) {
                $this->refreshToken($tokenService, $config);
            }
        }
    }

    /**
     * Refresh token for a specific configuration
     */
    protected function refreshToken(SystemUserTokenService $tokenService, MetaConfiguration $config): void
    {
        try {
            $result = $tokenService->extendToken($config);

            if ($result['success']) {
                Log::info("System User token extended successfully for configuration: {$config->name}");
            } else {
                Log::warning("Failed to extend System User token for configuration: {$config->name}", [
                    'message' => $result['message'] ?? 'Unknown error'
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Error extending System User token for configuration: {$config->name}", [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("RefreshSystemUserTokenJob failed", [
            'configuration_id' => $this->configurationId,
            'error' => $exception->getMessage()
        ]);
    }
}
