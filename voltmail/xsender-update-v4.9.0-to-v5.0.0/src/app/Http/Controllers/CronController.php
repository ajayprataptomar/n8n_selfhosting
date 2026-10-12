<?php

namespace App\Http\Controllers;

use Throwable;
use Exception;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Gateway;
use App\Models\Campaign;
use App\Enums\StatusEnum;
use App\Enums\ServiceType;
use App\Models\AndroidSim;
use App\Models\DispatchLog;
use App\Models\Subscription;
use App\Enums\SubscriptionStatus;

use App\Models\CampaignUnsubscribe;
use Illuminate\Support\Facades\Bus;
use App\Enums\System\RepeatTimeEnum;
use App\Jobs\ProcessDispatchLogBatch;
use App\Enums\System\ChannelTypeEnum;
use Illuminate\Support\LazyCollection;
use App\Enums\System\CampaignStatusEnum;
use App\Service\Admin\Core\SettingService;
use App\Service\Admin\Core\CustomerService;
use App\Enums\System\CommunicationStatusEnum;
use App\Models\DispatchDelay;
use App\Enums\System\DispatchTypeEnum;
use App\Services\System\Communication\DispatchService;
use App\Services\System\Communication\GatewayService;
use App\Services\System\AutomationService;
use App\Jobs\CheckWorkflowTriggersJob;
use Illuminate\Http\JsonResponse;

class CronController extends Controller
{
    public SettingService $settingService;
    public CustomerService $customerService;
    public GatewayService $gatewayService;
    public DispatchService $dispatchService;

    public function __construct()
    {
        $this->settingService   = new SettingService;
        $this->customerService  = new CustomerService;
        $this->gatewayService   = new GatewayService;
        $this->dispatchService   = new DispatchService;
    }

    /**
     * run - Legacy method for backward compatibility
     *
     * @return void
     */
    public function run(): void
    {
        try {

            $this->settingService->updateSettings([
                "last_cron_run" => Carbon::now()
            ]);


            $this->smsApiSchedule();
            $this->smsAndroidSchedule();
            $this->whatsappSchedule();
            $this->emailSchedule();

            $this->processActiveCampaign();
            $this->processOngoingCampaign();
            $this->processCompletedCampaign();

            // Android gateway update
            // $this->updateAndroidGateway();

            $this->checkPlanExpiration();
        } catch (Throwable $throwable) {
        }
    }

    /**
     * Enterprise automation - Single endpoint for all tasks
     * This is the recommended method for production
     * Works on: cPanel (cron job), VPS, Dedicated servers
     *
     * IMPORTANT: This endpoint detects the automation mode and only processes
     * what it should based on the mode to prevent double execution.
     *
     * @return JsonResponse
     */
    public function automation(): JsonResponse
    {
        try {
            $mode = AutomationService::getEffectiveMode();
            $queueStats = ['queues_processed' => 0, 'jobs_processed' => 0];
            $campaignsProcessed = false;
            $cleanedUp = 0;

            // Check if this endpoint should process campaigns
            // (Mode is cron_url OR auto mode detected as cron_url)
            if (AutomationService::shouldProcessCampaigns('cron_url')) {
                $this->run();

                // Process unified campaigns (scheduled + running)
                \Illuminate\Support\Facades\Artisan::call('campaigns:process');

                $campaignsProcessed = true;
            }

            // Check if this endpoint should process queues
            // Only process queues if mode allows cron_url to do so
            if (AutomationService::shouldProcessQueues('cron_url')) {
                // Dispatch workflow triggers check (birthday, schedule, no-response)
                try {
                    CheckWorkflowTriggersJob::dispatch();
                } catch (\Exception $e) {
                    // Log but don't fail the entire cron
                    \Log::warning('Workflow triggers dispatch failed: ' . $e->getMessage());
                }

                $queueStats = AutomationService::processQueues(5, 10);
                $cleanedUp = AutomationService::cleanupStaleJobs(1);
            }

            return response()->json([
                'success' => true,
                'message' => 'Automation completed successfully',
                'data' => [
                    'mode' => $mode,
                    'campaigns_processed' => $campaignsProcessed,
                    'queues_processed' => $queueStats['queues_processed'],
                    'jobs_processed' => $queueStats['jobs_processed'],
                    'stale_jobs_cleaned' => $cleanedUp,
                    'timestamp' => now()->toDateTimeString(),
                    'note' => $this->getModeNote($mode),
                ],
            ]);

        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Automation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get note explaining what was skipped based on mode
     */
    private function getModeNote(string $mode): string
    {
        switch ($mode) {
            case AutomationService::MODE_SUPERVISOR:
                return 'Queue processing skipped - handled by supervisor workers';
            case AutomationService::MODE_SCHEDULER:
                return 'Queue processing skipped - handled by Laravel scheduler';
            case AutomationService::MODE_CRON_URL:
                return 'Full processing - campaigns and queues handled by this URL';
            default:
                return 'Auto mode - processing based on detected environment';
        }
    }

    /**
     * Get automation health status
     *
     * @return JsonResponse
     */
    public function health(): JsonResponse
    {
        try {
            $health = AutomationService::getHealthStatus();
            $queues = AutomationService::getQueueStats();

            return response()->json([
                'success' => true,
                'data' => [
                    'health' => $health,
                    'queues' => $queues,
                ],
            ]);

        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retry failed jobs
     *
     * @return JsonResponse
     */
    public function retryFailed(): JsonResponse
    {
        try {
            $count = AutomationService::retryFailedJobs();

            return response()->json([
                'success' => true,
                'message' => "Retried {$count} failed jobs",
            ]);

        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Clear failed jobs
     *
     * @return JsonResponse
     */
    public function clearFailed(): JsonResponse
    {
        try {
            $count = AutomationService::clearFailedJobs();

            return response()->json([
                'success' => true,
                'message' => "Cleared {$count} failed jobs",
            ]);

        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * getServiceType
     *
     * @param ChannelTypeEnum $type
     * 
     * @return ServiceType
     */
    public function getServiceType(ChannelTypeEnum $type): ServiceType
    {
        return match ($type) {
            ChannelTypeEnum::EMAIL => ServiceType::EMAIL,
            ChannelTypeEnum::SMS => ServiceType::SMS,
            ChannelTypeEnum::WHATSAPP => ServiceType::WHATSAPP,
        };
    }

    /**
     * checkDailyLimit
     *
     * @param ChannelTypeEnum $channel
     * @param User $user
     * 
     * @return bool
     */
    private function checkDailyLimit(ChannelTypeEnum $channel, User $user): bool
    {
        $planAccess = (object) planAccess($user);
        $type = $this->getServiceType($channel);
        return checkCredit($user, $channel->value) && $this->customerService->canSpendCredits($user, $planAccess, $type->value);
    }

    /**
     * smsApiSchedule
     *
     * @return void
     */
    protected function smsApiSchedule(): void
    {
        $query = DispatchLog::where('type', ChannelTypeEnum::SMS)
                                        ->where('status', CommunicationStatusEnum::SCHEDULE)
                                        ->where('gatewayable_type', Gateway::class)
                                        ->whereNotNull(['scheduled_at'])
                                        ->where('scheduled_at', '<=', Carbon::now())
                                        ->with(['user', 'gatewayable']);
                                        
        $regularLogs = (clone $query)->whereNull('campaign_id')
                                            ->get()
                                            ->toArray();

        $campaignLogs = (clone $query)->whereNotNull('campaign_id')
                                            ->get()
                                            ->toArray();
        $this->processRegularScheduledLogs($regularLogs, ChannelTypeEnum::SMS);
        $this->processCampaignScheduledLogs($campaignLogs, ChannelTypeEnum::SMS);
    }

    /**
     * smsAndroidSchedule
     *
     * @return void
     */
    protected function smsAndroidSchedule(): void
    {
        try {

            $query = DispatchLog::where('type', ChannelTypeEnum::SMS)
                                        ->where('status', CommunicationStatusEnum::SCHEDULE)
                                        ->where('gatewayable_type', AndroidSim::class)
                                        ->whereNotNull(['scheduled_at'])
                                        ->where('scheduled_at', '<=', Carbon::now())
                                        ->with(['user', 'gatewayable']);
                                        
            $regularLogs = (clone $query)->whereNull('campaign_id')
                                            ->get()
                                            ->toArray();

            $campaignLogs = (clone $query)->whereNotNull('campaign_id')
                                                ->get()
                                                ->toArray();

            $this->processRegularScheduledLogs($regularLogs, ChannelTypeEnum::SMS, true);
            $this->processCampaignScheduledLogs($campaignLogs, ChannelTypeEnum::SMS, true);
        } catch (Throwable $throwable) {}
    }

    /**
     * whatsappSchedule
     *
     * @return void
     */
    protected function whatsappSchedule(): void
    {
        try {
            $query = DispatchLog::where('type', ChannelTypeEnum::WHATSAPP)
                                        ->where('status', CommunicationStatusEnum::SCHEDULE)
                                        ->where('gatewayable_type', Gateway::class)
                                        ->whereNotNull(['scheduled_at'])
                                        ->where('scheduled_at', '<=', Carbon::now())
                                        ->with(['user', 'gatewayable']);
                                        
            $regularLogs = (clone $query)->whereNull('campaign_id')
                                            ->get()
                                            ->toArray();

            $campaignLogs = (clone $query)->whereNotNull('campaign_id')
                                                ->get()
                                                ->toArray();
                                    
            $this->processRegularScheduledLogs($regularLogs, ChannelTypeEnum::WHATSAPP);
            $this->processCampaignScheduledLogs($campaignLogs, ChannelTypeEnum::WHATSAPP);
        } catch (Throwable $throwable) {}
    }

    /**
     * emailSchedule
     *
     * @return void
     */
    protected function emailSchedule(): void
    {
        try {
            $query = DispatchLog::where('type', ChannelTypeEnum::EMAIL)
                                        ->where('status', CommunicationStatusEnum::SCHEDULE)
                                        ->where('gatewayable_type', Gateway::class)
                                        ->whereNotNull(['scheduled_at'])
                                        ->where('scheduled_at', '<=', Carbon::now())
                                        ->with(['user', 'gatewayable']);
                                        
            $regularLogs = (clone $query)->whereNull('campaign_id')
                                            ->get()
                                            ->toArray();

            $campaignLogs = (clone $query)->whereNotNull('campaign_id')
                                                ->get()
                                                ->toArray();
            
            $this->processRegularScheduledLogs($regularLogs, ChannelTypeEnum::EMAIL);
            $this->processCampaignScheduledLogs($campaignLogs, ChannelTypeEnum::EMAIL);
        } catch (Exception $throwable) {
        }
    }

    /**
     * processScheduledLogs
     *
     * @param array $logs
     * @param ChannelTypeEnum $channel
     * @param bool $isAndroid
     * 
     * @return void
     */
    protected function processRegularScheduledLogs(array $logs, ChannelTypeEnum $channel, bool $isAndroid = false): void
    {
        $logIds = array_column($logs, 'id');
        if ($isAndroid) {
            if (!empty($logIds)) {
                DispatchLog::whereIn('id', $logIds)
                    ->update(['status' => CommunicationStatusEnum::PENDING->value]);
            }
            return;
        }
        if (!empty($logIds)) { 

            $this->dispatchService->queueGatewayLogs($logs, $channel, "regular");
            DispatchLog::whereIn('id', $logIds)
                            ->update(['status' => CommunicationStatusEnum::PENDING->value]);
        }

    }
    
    /**
     * processCampaignScheduledLogs
     *
     * @param array $logs
     * @param ChannelTypeEnum $channel
     * @param bool $isAndroid
     * 
     * @return void
     */
    protected function processCampaignScheduledLogs(array $logs, ChannelTypeEnum $channel, bool $isAndroid = false): void
    {
        $logIds = array_column($logs, 'id');
        if ($isAndroid) {
            if (!empty($logIds)) {
                DispatchLog::whereIn('id', $logIds)
                    ->update(['status' => CommunicationStatusEnum::PENDING->value]);
            }
            return;
        }
        if (!empty($logIds)) { 

            $this->dispatchService->queueGatewayLogs($logs, $channel, "campaign");
            DispatchLog::whereIn('id', $logIds)
                            ->update(['status' => CommunicationStatusEnum::PENDING->value]);
        }
    }

    /**
     * processActiveCampaign
     *
     * @return void
     */
    protected function processActiveCampaign(): void
    {
        try {
            $campaigns = Campaign::with([
                                        'dispatchLogs' => fn($query) => $query->where('status', CommunicationStatusEnum::PENDING->value)])
                                    ->where('status', CampaignStatusEnum::ACTIVE->value)
                                    ->where('schedule_at', '<=', Carbon::now())
                                    ->get();
            
            foreach ($campaigns as $campaign) {
                $logs = $campaign->dispatchLogs;
                if ($logs->isEmpty()) {
                    $campaign->status = CampaignStatusEnum::ONGOING->value;
                    $campaign->save();
                    continue;
                }

                // $this->processScheduledLogs($logs->lazy(), $campaign->type, 'campaign');
                $campaign->status = CampaignStatusEnum::ONGOING->value;
                $campaign->save();
            }
        } catch (Throwable $throwable) {}
    }

    /**
     * processOngoingCampaign
     *
     * @return void
     */
    protected function processOngoingCampaign(): void
    {
        try {
            $campaigns = Campaign::with(['dispatchLogs'])
                ->where('status', CampaignStatusEnum::ONGOING->value)
                ->get();

            foreach ($campaigns as $campaign) {
                $isProcessed = $campaign->dispatchLogs->contains(fn($log) => in_array($log->status->value, [
                    CommunicationStatusEnum::DELIVERED->value,
                    CommunicationStatusEnum::FAIL->value
                ]));

                if ($isProcessed) {
                    $campaign->status = CampaignStatusEnum::COMPLETED->value;
                    $campaign->save();
                }
            }
        } catch (Throwable $throwable) {}
    }

    /**
     * processCompletedCampaign
     *
     * @return void
     */
    protected function processCompletedCampaign(): void
    {
        try {
            $campaigns = Campaign::with(['dispatchLogs'])
                ->where('status', CampaignStatusEnum::COMPLETED->value)
                ->where('repeat_time', '>', 0)
                ->get();
            
            $validStatuses = [
                CommunicationStatusEnum::DELIVERED->value,
                CommunicationStatusEnum::FAIL->value,
            ];

            foreach ($campaigns as $campaign) {
                $user = $campaign->user_id ? User::find($campaign->user_id) : null;
                $canProceed = $user ? $this->checkDailyLimit(ChannelTypeEnum::from($campaign->type), $user) : true;

                if (!$canProceed) {
                    continue;
                }
                $scheduleAt = $this->getNewSchedule($campaign);
                
                $logs = $campaign->dispatchLogs->filter(fn($log) => in_array($log->status->value, $validStatuses));
                
                $processedContacts = [];

                foreach ($logs as $log) {
                    if (site_settings('filter_duplicate_contact') == StatusEnum::TRUE->status() && in_array($log->contact_id, $processedContacts)) {
                        continue;
                    }

                    $isUnsubscribed = CampaignUnsubscribe::where('contact_uid', $log->contact?->uid)
                        ->where('campaign_id', $log->campaign_id)
                        ->where('channel', $campaign->type)
                        ->exists();

                    if ($isUnsubscribed) {
                        continue;
                    }

                    $newLog = $log->replicate();
                    $newLog->scheduled_at = $scheduleAt;
                    $newLog->status = CommunicationStatusEnum::SCHEDULE->value;
                    $newLog->sent_at = null;
                    $newLog->response_message = null;
                    $newLog->retry_count = 0;
                    $newLog->save();

                    $processedContacts[] = $log->contact_id;

                    if ($user) {
                        $totalCredit = $this->calculateCredit($newLog);
                        $this->customerService->deductCreditLog($user, $totalCredit, $newLog->type);
                    }
                }

                $campaign->schedule_at = $scheduleAt;
                $campaign->status = CampaignStatusEnum::ACTIVE->value;
                $campaign->save();
            }
        } catch (Throwable $throwable) {}
    }

    /**
     * calculateCredit
     *
     * @param DispatchLog $log
     * 
     * @return int
     */
    private function calculateCredit(DispatchLog $log): int
    {
        // Delegate to canonical helper — GSM 03.38 standard (160/153/70/67),
        // multibyte-safe, extension-set aware, emoji-aware. Matches how
        // Twilio / Vonage / Infobip / MessageBird actually bill the message.
        // Previously used site_settings('sms_word_count') (defaulting to 320)
        // with byte-level str_split — 400-char messages were billed as 2 segments
        // instead of the correct 3, and multibyte GSM chars were mis-counted.
        $body = (string) ($log->message->message ?? '');
        if ($log->type == ServiceType::SMS->value || $log->type == ServiceType::WHATSAPP->value) {
            return sms_segment_count($body);
        }
        return 1;
    }

    /**
     * getNewSchedule
     *
     * @param Campaign $campaign
     * 
     * @return string
     */
    private function getNewSchedule(Campaign $campaign): string
    {
        try {
            $scheduleAt = Carbon::parse($campaign->schedule_at);
            $repeatTime = $campaign->repeat_time;
            
            match ($campaign->repeat_format->value) {
                RepeatTimeEnum::DAILY->value => $scheduleAt->addDays($repeatTime),
                RepeatTimeEnum::WEEKLY->value => $scheduleAt->addWeeks($repeatTime),
                RepeatTimeEnum::MONTHLY->value => $scheduleAt->addMonths($repeatTime),
                RepeatTimeEnum::YEARLY->value => $scheduleAt->addYears($repeatTime),
                default => null,
            };
            return $scheduleAt->toDateTimeString();
        } catch (Exception $th) {
            return $campaign->schedule_at; 
        }
    }

    // Android gateway update (unchanged as requested)
    // protected function updateAndroidGateway()
    // {
    //     try {
    //         $logs = CommunicationLog::where('type', ServiceType::SMS->value)
    //             ->whereNotNull('campaign_id')
    //             ->where(function ($query) {
    //                 $query->where('status', '!=', CommunicationStatusEnum::DELIVERED)
    //                     ->orWhere('status', '!=', CommunicationStatusEnum::FAIL);
    //             })
    //             ->whereNull('response_message')
    //             ->whereNull('gateway_id')
    //             ->whereNotNull("android_gateway_sim_id")
    //             ->get();

    //         foreach ($logs as $log) {
    //             if ($log->user_id) {
    //                 $user = User::where("id", $log->user_id)->first();
    //                 if ($user) {
    //                     $plan_access = planAccess($user);
    //                     if (count($plan_access) > 0) {
    //                         $plan_access = (object) planAccess($user);
    //                         $sim = $plan_access->type == StatusEnum::FALSE->status() ? $this->androidUserGatewayUpdate($log) : $this->androidAdminGatewayUpdate($log);
    //                         $meta_data = $log->meta_data;
    //                         $meta_data["gateway"] = $sim->androidGateway->name;
    //                         $meta_data["gateway_name"] = $sim->sim_number;
    //                         $log->android_gateway_sim_id = $sim->id;
    //                         $log->meta_data = $meta_data;
    //                         $log->save();
    //                     }
    //                 }
    //             } else {
    //                 $sim = $this->androidAdminGatewayUpdate($log);
    //                 $meta_data = $log->meta_data;
    //                 if ($sim->androidGateway) {
    //                     $meta_data["gateway"] = $sim->androidGateway->name;
    //                     $meta_data["gateway_name"] = $sim->sim_number;
    //                     $log->android_gateway_sim_id = $sim->id;
    //                     $log->meta_data = $meta_data;
    //                     $log->save();
    //                 }
    //             }
    //         }
    //     } catch (Exception $th) {}
    // }

    // private function androidUserGatewayUpdate($log)
    // {
    //     try {
    //         $sim = AndroidApiSimInfo::where("id", $log->android_gateway_sim_id)->first();
    //         if (!$sim || $sim->status == AndroidApiSimEnum::INACTIVE->value) {
    //             $gateway = AndroidApi::where("user_id", $log->user_id)->inRandomOrder()->first();
    //             $new_sim = AndroidApiSimInfo::where("android_gateway_id", $gateway->id)->where("status", AndroidApiSimEnum::ACTIVE)->first();
    //             if ($new_sim) {
    //                 $sim = $new_sim;
    //             }
    //         }
    //         return $sim;
    //     } catch (Exception $th) {}
    // }

    // private function androidAdminGatewayUpdate($log)
    // {
    //     try {
    //         $sim = AndroidApiSimInfo::where("id", $log->android_gateway_sim_id)->first();
    //         if (!$sim || $sim->status == AndroidApiSimEnum::INACTIVE->value) {
    //             $gateway = AndroidApi::whereNull("user_id")->inRandomOrder()->first();
    //             $new_sim = AndroidApiSimInfo::where("android_gateway_id", $gateway->id)->where("status", AndroidApiSimEnum::ACTIVE)->first();
    //             if ($new_sim) {
    //                 $sim = $new_sim;
    //             }
    //         }
    //         return $sim;
    //     } catch (Exception $th) {}
    // }

    /**
     * checkPlanExpiration
     *
     * @return void
     */
    protected function checkPlanExpiration(): void
    {
        try {
            $subscriptions = Subscription::whereIn('status', [
                SubscriptionStatus::RUNNING->value,
                SubscriptionStatus::RENEWED->value
            ])->get();

            $now = Carbon::now();
            foreach ($subscriptions as $subscription) {
                if ($now->greaterThan(Carbon::parse($subscription->expired_date))) {
                    $subscription->status = SubscriptionStatus::EXPIRED->value;
                    $subscription->save();
                }
            }
        } catch (Throwable $throwable) {}
    }
}