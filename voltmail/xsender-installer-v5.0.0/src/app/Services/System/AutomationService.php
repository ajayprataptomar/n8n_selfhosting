<?php

namespace App\Services\System;

use App\Enums\System\CommunicationStatusEnum;
use App\Models\Setting;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Enterprise Automation Service
 *
 * This service provides:
 * - Health monitoring for the automation system
 * - Queue statistics and management
 * - Failed job handling
 * - Automation mode detection to prevent double execution
 *
 * AUTOMATION MODES:
 * - 'auto'       : Auto-detect (default) - System decides based on environment
 * - 'cron_url'   : cPanel/Shared hosting - Uses /automation/run URL, processes queues in-request
 * - 'scheduler'  : VPS/Local - Uses Laravel scheduler (artisan schedule:run), scheduler processes queues
 * - 'supervisor' : Enterprise - Uses supervisor for continuous queue workers, scheduler only handles campaigns
 *
 * NOTE: Actual message/campaign processing is handled by CronController
 * to avoid duplicate code and ensure backward compatibility.
 *
 * Works on: Shared Hosting (cPanel), VPS, Dedicated Servers
 */
class AutomationService
{
    /**
     * Automation mode constants
     */
    public const MODE_AUTO = 'auto';
    public const MODE_CRON_URL = 'cron_url';      // cPanel - single URL handles everything
    public const MODE_SCHEDULER = 'scheduler';     // VPS - Laravel scheduler + scheduler-based queue
    public const MODE_SUPERVISOR = 'supervisor';   // Enterprise - Supervisor continuous workers

    /**
     * Cache key for supervisor detection
     */
    private const SUPERVISOR_HEARTBEAT_KEY = 'supervisor_worker_heartbeat';
    private const SUPERVISOR_HEARTBEAT_TTL = 120; // 2 minutes

    /**
     * Get the current automation mode setting
     *
     * @return string
     */
    public static function getMode(): string
    {
        return site_settings('automation_mode', self::MODE_AUTO);
    }

    /**
     * Set the automation mode
     *
     * @param string $mode
     * @return void
     */
    public static function setMode(string $mode): void
    {
        $validModes = [self::MODE_AUTO, self::MODE_CRON_URL, self::MODE_SCHEDULER, self::MODE_SUPERVISOR];
        if (!in_array($mode, $validModes)) {
            $mode = self::MODE_AUTO;
        }

        Setting::updateOrCreate(
            ['key' => 'automation_mode'],
            ['value' => $mode]
        );

        Cache::forget('site_settings');
    }

    /**
     * Detect if external queue workers (supervisor) are running
     * Workers register heartbeat when processing jobs
     *
     * @return bool
     */
    public static function isExternalWorkerRunning(): bool
    {
        return Cache::has(self::SUPERVISOR_HEARTBEAT_KEY);
    }

    /**
     * Register heartbeat from queue worker
     * Called by queue jobs to indicate a worker is running
     *
     * @return void
     */
    public static function registerWorkerHeartbeat(): void
    {
        Cache::put(self::SUPERVISOR_HEARTBEAT_KEY, [
            'timestamp' => now()->toDateTimeString(),
            'pid' => getmypid(),
        ], self::SUPERVISOR_HEARTBEAT_TTL);
    }

    /**
     * Determine the effective automation mode
     * If mode is 'auto', detect based on environment
     *
     * @return string
     */
    public static function getEffectiveMode(): string
    {
        $mode = self::getMode();

        if ($mode !== self::MODE_AUTO) {
            return $mode;
        }

        // Auto-detect mode
        // Check if supervisor workers are running
        if (self::isExternalWorkerRunning()) {
            return self::MODE_SUPERVISOR;
        }

        // Check if we're being called from CLI (scheduler)
        if (app()->runningInConsole()) {
            return self::MODE_SCHEDULER;
        }

        // Default to cron URL mode (web request)
        return self::MODE_CRON_URL;
    }

    /**
     * Check if queue processing should be done by this request
     * Prevents double execution when multiple automation methods are configured
     *
     * @param string $caller 'cron_url' | 'scheduler' | 'worker'
     * @return bool
     */
    public static function shouldProcessQueues(string $caller): bool
    {
        $mode = self::getEffectiveMode();

        switch ($mode) {
            case self::MODE_CRON_URL:
                // Only cron URL should process queues
                return $caller === 'cron_url';

            case self::MODE_SCHEDULER:
                // Only scheduler should process queues
                return $caller === 'scheduler';

            case self::MODE_SUPERVISOR:
                // Only external workers should process queues
                // Scheduler and cron_url should NOT process queues
                return $caller === 'worker';

            default:
                // Auto mode - let the caller process (backward compatible)
                return true;
        }
    }

    /**
     * Check if campaign processing should be done by this request
     * Campaign processing (CronController->run()) should always happen
     * regardless of automation mode, but only ONCE
     *
     * @param string $caller 'cron_url' | 'scheduler'
     * @return bool
     */
    public static function shouldProcessCampaigns(string $caller): bool
    {
        $mode = self::getEffectiveMode();

        switch ($mode) {
            case self::MODE_CRON_URL:
                return $caller === 'cron_url';

            case self::MODE_SCHEDULER:
            case self::MODE_SUPERVISOR:
                // Scheduler handles campaigns
                return $caller === 'scheduler';

            default:
                return true;
        }
    }

    /**
     * Get automation mode options for UI
     *
     * @return array
     */
    public static function getModeOptions(): array
    {
        return [
            self::MODE_AUTO => [
                'label' => 'Auto Detect',
                'description' => 'System automatically detects the best mode based on your setup',
                'icon' => 'ri-magic-line',
            ],
            self::MODE_CRON_URL => [
                'label' => 'Cron URL (Shared Hosting)',
                'description' => 'Single URL handles campaigns and queue processing. Best for cPanel.',
                'icon' => 'ri-cloud-line',
            ],
            self::MODE_SCHEDULER => [
                'label' => 'Laravel Scheduler (VPS)',
                'description' => 'Uses artisan schedule:run for everything. Good for VPS without supervisor.',
                'icon' => 'ri-computer-line',
            ],
            self::MODE_SUPERVISOR => [
                'label' => 'Supervisor Workers (Enterprise)',
                'description' => 'Supervisor runs continuous queue workers. Scheduler only handles campaigns.',
                'icon' => 'ri-database-2-line',
            ],
        ];
    }


    /**
     * Process queued jobs
     * This is specifically for cPanel/shared hosting where continuous workers can't run
     * Processes jobs from all queues in a single HTTP request
     *
     * @param int $maxJobsPerQueue Maximum jobs to process per queue
     * @param int $maxTimePerQueue Maximum seconds per queue
     * @return array Statistics about processed jobs
     */
    public static function processQueues(int $maxJobsPerQueue = 5, int $maxTimePerQueue = 10): array
    {
        $stats = [
            'started_at' => now()->toDateTimeString(),
            'queues_processed' => 0,
            'jobs_processed' => 0,
            'errors' => [],
        ];

        $queues = [
            'worker-trigger',
            'regular-sms',
            'regular-email',
            'regular-whatsapp',
            'campaign-sms',
            'campaign-email',
            'campaign-whatsapp',
            'chat-whatsapp',
            'default',
            'dispatch-logs',
            'import-contacts',
            'verify-email',
            'lead-scraping',
            'automation',  // Workflow automation queue
        ];

        foreach ($queues as $queue) {
            try {
                // Check if there are jobs in this queue
                $jobCount = DB::table('jobs')->where('queue', $queue)->count();

                if ($jobCount === 0) {
                    continue;
                }

                // Process jobs (limited by time and count)
                $processed = 0;
                $startTime = time();

                while ($processed < $maxJobsPerQueue && (time() - $startTime) < $maxTimePerQueue) {
                    // Check if still have jobs
                    $hasJobs = DB::table('jobs')->where('queue', $queue)->exists();
                    if (!$hasJobs) {
                        break;
                    }

                    try {
                        Artisan::call('queue:work', [
                            'connection' => 'database',
                            '--queue' => $queue,
                            '--once' => true,
                            '--stop-when-empty' => true,
                            '--max-time' => 5,
                        ]);
                        $processed++;
                    } catch (Exception $e) {
                        // Log but continue
                        Log::warning("Queue {$queue} job error: " . $e->getMessage());
                        break;
                    }
                }

                if ($processed > 0) {
                    $stats['queues_processed']++;
                    $stats['jobs_processed'] += $processed;
                }

            } catch (Exception $e) {
                $stats['errors'][] = "Queue {$queue}: " . $e->getMessage();
            }
        }

        $stats['completed_at'] = now()->toDateTimeString();
        return $stats;
    }

    /**
     * Update last cron run time
     */
    public static function updateLastRun(): void
    {
        try {
            Setting::updateOrCreate(
                ['key' => 'last_cron_run'],
                ['value' => Carbon::now()->toDateTimeString()]
            );
        } catch (Exception $e) {
            // Silent fail
        }
    }

    /**
     * Get automation health status
     */
    public static function getHealthStatus(): array
    {
        $lastRun = site_settings('last_cron_run');
        $lastRunTime = $lastRun ? Carbon::parse($lastRun) : null;

        $pendingJobs = DB::table('jobs')->count();
        $failedJobs = DB::table('failed_jobs')->count();

        $pendingMessages = DB::table('dispatch_logs')
            ->whereIn('status', [
                CommunicationStatusEnum::SCHEDULE->value,
                CommunicationStatusEnum::PENDING->value,
            ])->count();

        $processingMessages = DB::table('dispatch_logs')
            ->where('status', CommunicationStatusEnum::PROCESSING->value)
            ->count();

        // Determine health status
        $isHealthy = true;
        $warnings = [];

        if (!$lastRunTime || $lastRunTime->diffInMinutes(now()) > 10) {
            $isHealthy = false;
            $warnings[] = 'Cron has not run in the last 10 minutes';
        }

        if ($pendingJobs > 1000) {
            $warnings[] = 'High number of pending queue jobs (' . $pendingJobs . ')';
        }

        if ($failedJobs > 100) {
            $warnings[] = 'High number of failed jobs (' . $failedJobs . ')';
        }

        if ($processingMessages > 100) {
            $warnings[] = 'Many messages stuck in processing state (' . $processingMessages . ')';
        }

        return [
            'is_healthy' => $isHealthy && empty($warnings),
            'last_run' => $lastRun,
            'last_run_ago' => $lastRunTime ? $lastRunTime->diffForHumans() : 'Never',
            'pending_queue_jobs' => $pendingJobs,
            'failed_queue_jobs' => $failedJobs,
            'pending_messages' => $pendingMessages,
            'processing_messages' => $processingMessages,
            'warnings' => $warnings,
        ];
    }

    /**
     * Get queue statistics
     */
    public static function getQueueStats(): array
    {
        $queues = [
            'default',
            'worker-trigger',
            'regular-sms',
            'regular-email',
            'regular-whatsapp',
            'campaign-sms',
            'campaign-email',
            'campaign-whatsapp',
            'chat-whatsapp',
            'dispatch-logs',
            'import-contacts',
            'verify-email',
            'lead-scraping',
            'automation',
        ];

        $stats = [];

        foreach ($queues as $queue) {
            $stats[$queue] = [
                'pending' => DB::table('jobs')->where('queue', $queue)->count(),
                'failed' => DB::table('failed_jobs')->where('queue', $queue)->count(),
            ];
        }

        return $stats;
    }

    /**
     * Retry failed jobs
     */
    public static function retryFailedJobs(?string $queue = null): int
    {
        try {
            $query = DB::table('failed_jobs');

            if ($queue) {
                $query->where('queue', $queue);
            }

            $count = $query->count();

            if ($count > 0) {
                Artisan::call('queue:retry', ['id' => 'all']);
            }

            return $count;

        } catch (Exception $e) {
            Log::error('Retry failed jobs error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Clear all failed jobs
     */
    public static function clearFailedJobs(?string $queue = null): int
    {
        try {
            $query = DB::table('failed_jobs');

            if ($queue) {
                $query->where('queue', $queue);
            }

            return $query->delete();

        } catch (Exception $e) {
            Log::error('Clear failed jobs error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Cleanup stale processing jobs
     * Marks jobs stuck in "processing" state as failed
     */
    public static function cleanupStaleJobs(int $hoursOld = 1): int
    {
        try {
            $staleThreshold = Carbon::now()->subHours($hoursOld);

            return DB::table('dispatch_logs')
                ->where('status', CommunicationStatusEnum::PROCESSING->value)
                ->where('updated_at', '<', $staleThreshold)
                ->update([
                    'status' => CommunicationStatusEnum::FAIL->value,
                    'response_message' => 'Timed out - no response received after ' . $hoursOld . ' hour(s)',
                    'updated_at' => now(),
                ]);

        } catch (Exception $e) {
            Log::error('Cleanup stale jobs error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Prune old failed jobs
     */
    public static function pruneOldFailedJobs(int $daysOld = 7): int
    {
        try {
            return DB::table('failed_jobs')
                ->where('failed_at', '<', Carbon::now()->subDays($daysOld))
                ->delete();

        } catch (Exception $e) {
            Log::error('Prune failed jobs error: ' . $e->getMessage());
            return 0;
        }
    }
}
