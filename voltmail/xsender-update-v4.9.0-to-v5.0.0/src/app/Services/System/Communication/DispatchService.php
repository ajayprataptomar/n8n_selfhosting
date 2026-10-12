<?php

namespace App\Services\System\Communication;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Gateway;
use App\Models\Message;
use App\Models\Campaign;
use App\Models\Template;
use App\Enums\StatusEnum;
use Illuminate\View\View;
use App\Enums\SettingKey;
use App\Enums\ServiceType;
use App\Models\AndroidSim;
use App\Models\DispatchLog;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use App\Enums\Common\Status;
use App\Models\Contact;
use App\Models\ContactGroup;
use Illuminate\Http\Response;
use App\Models\AndroidSession;
use App\Managers\GatewayManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use App\Enums\System\PriorityEnum;
use App\Services\Core\MailService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Http\RedirectResponse;
use App\Enums\System\ChannelTypeEnum;
use App\Jobs\ProcessDispatchLogBatch;
use Illuminate\Support\LazyCollection;
use App\Managers\CommunicationManager;
use App\Exceptions\ApplicationException;
use App\Enums\System\CampaignStatusEnum;
use App\Services\System\TemplateService;
use App\Http\Requests\SmsCampaignRequest;
use App\Http\Utility\Api\ApiJsonResponse;
use Illuminate\Database\Eloquent\Builder;
use App\Service\Admin\Core\CustomerService;
use App\Http\Requests\EmailCampaignRequest;
use App\Enums\System\CommunicationStatusEnum;
use App\Http\Requests\WhatsappCampaignRequest;
use App\Services\System\Contact\ContactService;
use App\Enums\System\EmailVerificationStatusEnum;
use App\Jobs\InsertDispatchLogs;
use App\Models\DispatchDelay;
use App\Enums\System\DispatchTypeEnum;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class DispatchService
{ 
     protected $mailService;
     protected $gatewayService;
     protected $gatewayManager;
     protected $contactService;
     protected $dispatchManager;
     protected $templateService;
     protected $customerService;
     

     public function __construct()
     {
          $this->mailService       = new MailService();
          $this->gatewayService    = new GatewayService();
          $this->gatewayManager    = new GatewayManager();
          $this->contactService    = new ContactService();
          $this->templateService   = new TemplateService();
          $this->customerService   = new CustomerService();
          $this->dispatchManager   = new CommunicationManager();
     }

     ##------------------##
     ## Regular Dispatch ##
     ##------------------##

     /**
      * loadLogs
      *
      * @param ChannelTypeEnum $channel
      * @param int|string|null|null $campaign_id
      * @param User|null $user
      * 
      * @return View
      */
     public function loadLogs(ChannelTypeEnum $channel, int|string|null $campaign_id = null, ?User $user = null): View
     {
          $title              = translate("{$channel->value} Dispatch Logs");
          $logs               = $this->dispatchManager->getDispatchLogs(channel: $channel, campaign_id: $campaign_id, user: $user);
          $gateways           = $this->gatewayManager->getGateways(channel: $channel, user: $user);
          $androidSessions    = $channel == ChannelTypeEnum::SMS 
                                   ? $this->gatewayManager->getAndroidSessions(user: $user)
                                   : null;
          
          $panelType = $user ? "user" : "admin";
          return view("{$panelType}.communication.{$channel->value}.index", 
                    compact('title', 'logs', 'gateways', 'androidSessions'));
     }

     public function loadChats(User|null $user = null): View
     {
          $title              = translate("Chats");
          $templates          = $this->templateService->getChannelSpecificTemplates(channel: ChannelTypeEnum::WHATSAPP, user: $user);
          $panelType = $user ? "user" : "admin";
          return view("{$panelType}.communication.whatsapp.chats",
                    compact('title', 'templates'));
     }

     /**
      * showDispatchLog
      *
      * @param ChannelTypeEnum $channel
      * @param string|int $id
      * @param User|null $user
      * 
      * @return View
      */
     public function showDispatchLog(ChannelTypeEnum $channel, string|int $id, ?User $user = null, bool $raw = false): View {

          $title    = translate("Details View");
          $log      = $this->dispatchManager->getSpecificDispatchLog(id: $id, user: $user);

          if ($raw) {
               return view('partials.email_view', compact('title', 'log'));
          }

          $log->load(['gatewayable', 'campaign', 'user']);
          $title = translate("Email Details");
          $gateways = $this->gatewayManager->getGateways(channel: $channel, user: $user);

          $panelType = $user ? "user" : "admin";
          return view("{$panelType}.communication.{$channel->value}.show", compact('title', 'log', 'gateways'));
     }

     /**
      * Resend a dispatch log by creating a new PENDING copy and dispatching it.
      */
     public function resendDispatchLog(ChannelTypeEnum $channel, string|int $id, ?User $user = null): RedirectResponse
     {
          $log = $this->dispatchManager->getSpecificDispatchLog(id: $id, user: $user);
          if (!$log) throw new ApplicationException(translate('Dispatch log not found'));
          if (!$log->message) throw new ApplicationException(translate('No message data found for this log'));

          // Credit check & deduction for users — resend still costs by SMS segment.
          if ($user) {
               $creditColumn = $channel->value . "_credit";
               $body = (string) ($log->message->message ?? '');
               $segments = ($channel === ChannelTypeEnum::SMS || $channel === ChannelTypeEnum::WHATSAPP)
                    ? sms_segment_count($body)
                    : 1;

               if ($user->$creditColumn != -1 && $user->$creditColumn < $segments) {
                    throw new ApplicationException(translate('Insufficient credits for resend (needs :n segments)', ['n' => $segments]));
               }

               $serviceType = $this->getServiceType($channel);
               $this->customerService->deductCreditLog(
                    $user,
                    $segments,
                    $serviceType->value,
                    false,
                    translate("Deducted {$segments} credit(s) for {$channel->name} resend")
               );
          }

          // Create a new dispatch log copying the original
          $newLog = DispatchLog::create([
               'user_id'          => $log->user_id,
               'message_id'       => $log->message_id,
               'contact_id'       => $log->contact_id,
               'campaign_id'      => $log->campaign_id,
               'type'             => $log->type,
               'gatewayable_id'   => $log->gatewayable_id,
               'gatewayable_type' => $log->gatewayable_type,
               'status'           => CommunicationStatusEnum::PENDING,
               'priority'         => $log->priority,
               'meta_data'        => $log->meta_data,
               'retry_count'      => 0,
          ]);

          // Dispatch synchronously for instant feedback
          try {
               ProcessDispatchLogBatch::dispatchSync([$newLog->id], $channel, 'regular', false);
          } catch (\Exception $e) {
               \Log::error('Resend dispatch failed', [
                    'channel' => $channel->value,
                    'log_id'  => $newLog->id,
                    'error'   => $e->getMessage(),
               ]);
          }

          $panelPrefix = $user ? 'user' : 'admin';
          $newLog->refresh();
          $statusMsg = $newLog->status == CommunicationStatusEnum::DELIVERED
               ? translate('Email resent successfully')
               : translate('Email queued for resending');

          $notify[] = ['success', $statusMsg];
          return redirect()->route("{$panelPrefix}.communication.email.show", $newLog->id)->withNotify($notify);
     }

     /**
      * createDispatchLog
      *
      * @param ChannelTypeEnum $channel
      * @param User|null $user
      *
      * @return View
      */
     public function createDispatchLog(ChannelTypeEnum $channel, ?User $user = null): View {

          $credentials        = config('setting.gateway_credentials.email');
          $type               = $channel->value;
          $title              = ucfirst($type);
          $groups             = $this->contactService->getChannelSpecificGroup(channel: $channel, user: $user);
          $templates          = $this->templateService->getChannelSpecificTemplates(channel: $channel, user: $user);
          $gateways           = $this->gatewayManager->getGateways($channel, loadPaginated: false, user: $user);
          $gateways           = $gateways->groupBy('type')
                                             ->map(function ($group, $type) {
                                                  return $group->mapWithKeys(function ($gateway) {
                                                  return [$gateway->id => $gateway->name];
                                                  })->toArray();
                                             })->toArray();
                                             
          $androidSessions    = $channel == ChannelTypeEnum::SMS 
                                   ? $this->gatewayManager->getAndroidSessions(user: $user)
                                   : null;
          $planAccess = null;
          if($user) $planAccess = (object)planAccess($user);
          
          $panelType = $user ? "user" : "admin";
          
          return view("{$panelType}.communication.{$channel->value}.create", 
               compact('title', 'templates', 'gateways', 'groups', 'type', 'credentials', 'androidSessions', 'planAccess'));
     }

      /**
     * Store dispatch logs for a communication channel.
     *
     * @param ChannelTypeEnum $type
     * @param Request $request
     * @param bool $isCampaign
     * @param int|string|null $campaignId
     * @param User|null $user
     * @param bool $isApi
     * @param null|int $apiLogCount 
     * @return RedirectResponse|array
     */
    public function storeDispatchLogs(
     ChannelTypeEnum $type,
     Request $request,
     bool $isCampaign = false,
     int|string|null $campaignId = null,
     ?User $user = null,
     bool $isApi = false,
     null|int $apiLogCount = null
     ): RedirectResponse|array {
     
          $metaData      = [];
          $messageData   = $request->input('message');
          $contactsInput = $request->input('contacts');
          $scheduleAt    = $request->input('schedule_at');
     
          if ($type == ChannelTypeEnum::EMAIL) {
               $metaData = Arr::set($metaData, "email_from_name", $request->input('email_from_name'));
               $metaData = Arr::set($metaData, "reply_to_address", $request->input('reply_to_address'));
          }
     
          $dispatchLogs = [];
          $insertedLogs = DB::transaction(function () use ($contactsInput, $messageData, $scheduleAt, $type, $user, $request, $isCampaign, $campaignId, &$dispatchLogs, $metaData, $apiLogCount) {
                              $campaign = null;
                              $groups   = $this->contactService->handleContacts(type: $type, contactsInput: $contactsInput, user: $user);
                              $message  = $this->createMessage(request: $request, messageData: $messageData, type: $type, isCampaign: $isCampaign, user: $user);
     
                              if ($isCampaign) {
                                   $campaign = $this->createCampaign(request: $request, type: $type, message: $message, campaignId: $campaignId, user: $user);
                              }

                              $dispatchLogs = $this->prepareDispatchLogs(request: $request, groups: $groups, message: $message, type: $type, scheduleAt: $scheduleAt, campaign: $campaign, metaData: $metaData, user: $user);
                              $dispatchLogs = $this->gatewayService->assignGateway(type: $type, dispatchLogs: $dispatchLogs, request: $request, user: $user);

                              $totalLogCount = count($dispatchLogs);
                              $__body = (string) ($messageData['message_body'] ?? $messageData['message'] ?? '');
                              $contactCount = $this->checkUserCredits(type: $type, contactCount: $totalLogCount, user: $user, messageBody: $__body);
                              $insertedLogs = [];

                              $insertedLogs = LazyCollection::make($dispatchLogs)
                                                                 ->groupBy('gatewayable_id')
                                                                 ->flatMap(function ($gatewayLogs, $gatewayId) use ($type, $message, $totalLogCount, $isCampaign, $user, $request, $apiLogCount) {
                                                                      
                                                                      $gateway = null;
                                                                      if($request->input("method") == "android") {

                                                                           $gateway = AndroidSim::active()
                                                                                               ->where("id", $gatewayId)
                                                                                               ->first();
                                                                      } else {

                                                                           $gateway = Gateway::active()
                                                                                                    ->where("id", $gatewayId)
                                                                                                    ->select(["bulk_contact_limit", "type"])
                                                                                                    ->first();
                                                                      }
                                                                      
                                                                      if (!$gateway) throw new ApplicationException(translate("Gateway not found"));
                                                                      
                                                                      $bulkLimit     = $gateway->bulk_contact_limit ?? 1;
                                                                      $logCount      = $gatewayLogs->count();
                                                                      
                                                                      if ($bulkLimit > 1 && ($apiLogCount > 1 || $logCount > 1)) {
                                                                           
                                                                           return $gatewayLogs
                                                                                     ->chunk($bulkLimit)
                                                                                     ->flatMap(function ($chunk) use ($gatewayId, $type, $message, $totalLogCount, $isCampaign, $user, $apiLogCount) {
                                                                                          $unit = $this->dispatchManager->createDispatchUnit($gatewayId, $message, $type, $chunk->count());
                                                                                          return $chunk->chunk(1000)->flatMap(function ($subChunk) use ($unit, $totalLogCount, $type, $isCampaign, $user, $apiLogCount) {

                                                                                               $chunkArray = $subChunk->map(function ($log) use ($unit) {
                                                                                                    Arr::set($log, "dispatch_unit_id", $unit->id);
                                                                                                    return $log;
                                                                                               })->toArray();
                                                                                               
                                                                                               if ($totalLogCount <= 1000) { 
                                                                                                    DB::table('dispatch_logs')->insert($chunkArray);
                                                                                                    return $this->retrieveInsertedLogs($chunkArray);
                                                                                               } else {
                                                                                                    InsertDispatchLogs::dispatch($chunkArray, $type, $isCampaign ? 'campaign' : 'regular', $user, $apiLogCount);
                                                                                                    return $chunkArray; 
                                                                                               }
                                                                                          });
                                                                                     });
                                                                      } else {
                                                                           return $gatewayLogs->chunk(1000)->flatMap(function ($chunk) use($totalLogCount, $type, $isCampaign, $user, $apiLogCount) {
                                                                                $chunkArray = $chunk->toArray();
                                                                                if ($totalLogCount <= 1000) { 

                                                                                DB::table('dispatch_logs')->insert($chunkArray);
                                                                                     return $this->retrieveInsertedLogs($chunkArray);
                                                                                } else {
                                                                                     InsertDispatchLogs::dispatch($chunkArray, $type, $isCampaign ? 'campaign' : 'regular', $user, $apiLogCount);
                                                                                     return $chunkArray;
                                                                                }
                                                                           });
                                                                      }
                                                                 })->all();

                              // Determine if this is a single message (1 contact, not scheduled, not campaign)
                              $isSingleMessage = $totalLogCount === 1 && !$isCampaign && !$scheduleAt;
                              $dispatchResult = null;

                              if ($totalLogCount <= 1000 && !$isCampaign && !$scheduleAt) {
                                   // Pass forceSync=true for single messages to dispatch instantly
                                   $dispatchResult = $this->queueGatewayLogs(
                                        $insertedLogs,
                                        $type,
                                        $isCampaign ? 'campaign' : 'regular',
                                        $user,
                                        $apiLogCount,
                                        $isSingleMessage // forceSync for single messages
                                   );
                              }

                              if ($user) {

                                   if ($user && $contactCount > 0) {
                                        $serviceType = $this->getServiceType($type);
                                        // Credits are per-SEGMENT for SMS/WhatsApp, not per-contact.
                                        // A 400-char SMS costs 3 segments per recipient (GSM 03.38 standard).
                                        // Previously we deducted only $contactCount, which under-billed
                                        // multi-segment sends and let 160-char and 400-char messages
                                        // both cost 1 credit — the customer's original complaint.
                                        $body = (string) ($messageData['message_body'] ?? $messageData['message'] ?? '');
                                        $totalCredits = sms_credit_units($body, $contactCount, $serviceType->value);
                                        $segments = ($type === ChannelTypeEnum::SMS || $type === ChannelTypeEnum::WHATSAPP)
                                             ? sms_segment_count($body)
                                             : 1;
                                        $this->customerService->deductCreditLog(
                                             $user,
                                             $totalCredits,
                                             $serviceType->value,
                                             false,
                                             translate("Deducted {$totalCredits} credits for {$type->name} dispatch ({$contactCount} contacts × {$segments} segments)")
                                        );
                                   }
                              }

                              return [
                                   'logs' => $insertedLogs,
                                   'dispatchResult' => $dispatchResult,
                                   'isSingleMessage' => $isSingleMessage,
                                   'totalLogCount' => $totalLogCount
                              ];
                         });

          // Handle multi-media sequence for WhatsApp (additional files sent as separate messages)
          if ($type === ChannelTypeEnum::WHATSAPP && $request->hasFile('media_files')) {
               $additionalFiles = $this->findAndUploadMultipleFiles($request);
               if (!empty($additionalFiles) && !empty($dispatchLogs)) {
                    $originalMessage = Message::find($dispatchLogs[0]['message_id'] ?? null);
                    $campaign = $isCampaign && $campaignId ? \App\Models\Campaign::find($campaignId) : null;
                    if ($originalMessage) {
                         $this->createMultiMediaSequence($additionalFiles, $dispatchLogs, $originalMessage, $campaign, $user);
                    }
               }
          }

          if ($isApi) return $this->dispatchManager->getMappedDispatchLogs($insertedLogs['logs'] ?? $insertedLogs, $type);

          $panelType = $user
                         ? "user"
                         : "admin";

          // Build appropriate response based on dispatch mode
          $dispatchResult = $insertedLogs['dispatchResult'] ?? null;
          $isSingleMessage = $insertedLogs['isSingleMessage'] ?? false;
          $totalLogCount = $insertedLogs['totalLogCount'] ?? 0;

          if ($isSingleMessage && $dispatchResult && Arr::get($dispatchResult, 'mode') === 'sync') {
               // Single message was sent synchronously - provide instant feedback
               $success = Arr::get($dispatchResult, 'success', false);
               $status = Arr::get($dispatchResult, 'status', 'unknown');

               // Cleanup temporary contact if auto-save is disabled
               if (site_settings(SettingKey::AUTO_SAVE_QUICK_SEND_CONTACTS->value) == StatusEnum::FALSE->status()) {
                    $this->cleanupTemporaryContact(Arr::get($dispatchResult, 'log_id'), $user);
               }

               if ($success) {
                    $notify[] = ['success', translate("{$type->name} message sent successfully")];
               } else {
                    $errorMsg = Arr::get($dispatchResult, 'error', translate("Failed to send message"));
                    $notify[] = ['error', translate("{$type->name} message failed: ") . $errorMsg];
               }
          } else {
               // Bulk messages queued for processing
               $notify[] = ['success',
                         translate("{$type->name} "
                              . ($isCampaign
                                   ? 'campaign'
                                   : 'dispatch') . " logs are being processed")];
          }

          return redirect()
                    ->route("{$panelType}.communication.{$type->value}"
                         . ($isCampaign
                              ? '.campaign'
                              : '') . ".index")
                    ->withNotify($notify);
     }

     
     /**
      * checkUserCredits
      *
      * @param ChannelTypeEnum $type
      * @param User|null $user
      * @param int $contactCount
      * 
      * @return int
      */
     private function checkUserCredits(ChannelTypeEnum $type, int $contactCount, ?User $user = null, string $messageBody = ''): int
     {
          if (!$user) {
               return 0;
          }

          // A single SMS/WhatsApp message can span multiple segments — a 400-char
          // SMS is 3 segments in the GSM 03.38 standard. Balance and daily-limit
          // checks must both account for that or a customer with 1 credit could
          // send a message billed at 3.
          $segments = ($type === ChannelTypeEnum::SMS || $type === ChannelTypeEnum::WHATSAPP)
               ? sms_segment_count($messageBody)
               : 1;
          $requiredCredits = $contactCount * $segments;

          $planAccess = (object) planAccess($user);
          $creditColumn = $type->value."_credit";
          // -1 == unlimited (per historic convention); any other value must cover the request.
          if ($user->$creditColumn != -1 && $user->$creditColumn < $requiredCredits) {
               throw new ApplicationException(
                    translate("Insufficient credits — this message needs :need credits ({:contacts} contacts × {:segments} segments) but you have :have.", [
                         'need'     => $requiredCredits,
                         'contacts' => $contactCount,
                         'segments' => $segments,
                         'have'     => $user->$creditColumn,
                    ])
               );
          }

          $creditsPerDay = Arr::get($planAccess->{$type->value}, 'credits_per_day', 0);

          if ($creditsPerDay <= 0) {
               return $contactCount;
          }

          $todayStart    = Carbon::today()->startOfDay();
          $todayEnd      = Carbon::today()->endOfDay();

          $usedCredits = DB::table('dispatch_logs')
                              ->where('user_id', $user->id)
                              ->where('type', $type->value)
                              ->whereBetween('created_at', [$todayStart, $todayEnd])
                              ->whereIn('status', [
                                   CommunicationStatusEnum::PENDING->value,
                                   CommunicationStatusEnum::SCHEDULE->value,
                                   CommunicationStatusEnum::PROCESSING->value,
                                   CommunicationStatusEnum::DELIVERED->value
                              ])
                              ->count();

          if ($usedCredits + $requiredCredits > $creditsPerDay) {
               throw new ApplicationException(
                    translate("You have exceeded your daily {$type->name} credit limit of {$creditsPerDay}.")
               );
          }

          return $contactCount;
     }

     public function getServiceType(ChannelTypeEnum $type): ServiceType
     {
          return match ($type) {
               ChannelTypeEnum::EMAIL => ServiceType::EMAIL,
               ChannelTypeEnum::SMS => ServiceType::SMS,
               ChannelTypeEnum::WHATSAPP => ServiceType::WHATSAPP,
          };
     }

     /**
      * retrieveInsertedLogs
      *
      * @param array $logs
      * 
      * @return array
      */
     protected function retrieveInsertedLogs(array $logs): array
     {
          return collect($logs)->map(function ($log) {
               $log['id'] = DB::table('dispatch_logs')
                    ->where('message_id', Arr::get($log, "message_id"))
                    ->where('contact_id', Arr::get($log, "contact_id"))
                    ->where('created_at', Arr::get($log, "created_at"))
                    ->value('id');
               return $log;
          })->all();
     }

     /**
      * queueGatewayLogs
      *
      * @param array $dispatchLogs
      * @param ChannelTypeEnum $channel
      * @param string $pipe
      * @param User|null $user
      * @param int|null $apiLogCount
      * @param bool $forceSync - Force synchronous dispatch for single messages
      *
      * @return array - Returns dispatch result for sync dispatches
      */
     public function queueGatewayLogs(array $dispatchLogs, ChannelTypeEnum $channel, string $pipe, ?User $user = null, ?int $apiLogCount = null, bool $forceSync = false): array
     {
          $gatewayLogs = array_filter($dispatchLogs, fn($log) => Arr::get($log, 'gatewayable_type') === Gateway::class);
          if (empty($gatewayLogs)) return ['mode' => 'empty', 'success' => false];

          $totalLogs = count($gatewayLogs);

          // Single message detection: 1 log, not from API batch, force sync enabled
          // For single messages: skip anti-ban delay (only needed for bulk) → fast sync dispatch
          $isSingleMessage = $totalLogs === 1 && ($apiLogCount === null || $apiLogCount <= 1) && $forceSync;

          if ($isSingleMessage) {
               return $this->dispatchSingleMessageSync($gatewayLogs, $channel, $pipe);
          }

          // Bulk messages - use queue with delay
          return $this->dispatchBulkMessagesQueued($gatewayLogs, $channel, $pipe, $apiLogCount);
     }

     /**
      * dispatchSingleMessageSync - Send single message synchronously for instant feedback
      *
      * @param array $gatewayLogs
      * @param ChannelTypeEnum $channel
      * @param string $pipe
      *
      * @return array
      */
     protected function dispatchSingleMessageSync(array $gatewayLogs, ChannelTypeEnum $channel, string $pipe): array
     {
          $log = reset($gatewayLogs);
          $logId = Arr::get($log, 'id');

          if (!$logId) {
               return ['mode' => 'sync', 'success' => false, 'error' => 'No log ID found'];
          }

          try {
               // Dispatch synchronously - no queue delay
               ProcessDispatchLogBatch::dispatchSync([$logId], $channel, $pipe, false);

               // Fetch updated status
               $dispatchLog = DispatchLog::find($logId);
               $status = $dispatchLog ? $dispatchLog->status->value : 'unknown';

               return [
                    'mode' => 'sync',
                    'success' => $status === CommunicationStatusEnum::DELIVERED->value || $status === CommunicationStatusEnum::PROCESSING->value,
                    'status' => $status,
                    'log_id' => $logId
               ];
          } catch (\Exception $e) {
               Log::error('Sync dispatch failed', [
                    'channel' => $channel->value,
                    'log_id' => $logId,
                    'error' => $e->getMessage()
               ]);

               return [
                    'mode' => 'sync',
                    'success' => false,
                    'error' => $e->getMessage(),
                    'log_id' => $logId
               ];
          }
     }

     /**
      * dispatchBulkMessagesQueued - Queue bulk messages with delay for rate limiting
      *
      * @param array $gatewayLogs
      * @param ChannelTypeEnum $channel
      * @param string $pipe
      * @param int|null $apiLogCount
      *
      * @return array
      */
     protected function dispatchBulkMessagesQueued(array $gatewayLogs, ChannelTypeEnum $channel, string $pipe, ?int $apiLogCount = null): array
     {
          $batches       = [];
          $batchSizes    = config("queue.batch_sizes.{$pipe}.{$channel->value}");
          $queue         = config("queue.pipes.{$pipe}.{$channel->value}");
          $minBatchSize  = Arr::get($batchSizes, "min");
          $maxBatchSize  = Arr::get($batchSizes, "max");
          $gatewayConfig = config("setting.gateway_credentials.{$channel->value}");
          $logCounter    = 0;

          collect($gatewayLogs)
               ->groupBy(function ($log) use ($channel, $logCounter) {

                    return implode('|', [
                         Arr::get($log, 'gatewayable_id'),
                         Arr::get($log, 'user_id'),
                         $channel->value,
                         Arr::get($log, 'campaign_id', 'none'),
                         Arr::get($log, 'id', 'none'),
                         Arr::get($log, 'scheduled_at', 'none'),
                    ]);
               })
               ->map(function ($logs, $groupKey) use ($channel, $pipe, &$batches, $maxBatchSize, $minBatchSize, $gatewayConfig, &$logCounter, $apiLogCount) {

                    [$gatewayId, $userId, $channelValue, $campaignId, $dispatchId, $scheduledAt] = explode('|', $groupKey);


                    $gatewayId          = $gatewayId ?: null;
                    $userId             = $userId ?: null;
                    $dispatchId         = $dispatchId !== 'none' ? $dispatchId : null;
                    $messagesToSend     = $apiLogCount ?? $logs->count();

                    $dispatchType       = $scheduledAt ? DispatchTypeEnum::CAMPAIGN : DispatchTypeEnum::REGULAR;
                    $delay              = $this->gatewayService->calculateDispatchDelay($gatewayId, $channel, $messagesToSend, $campaignId ? "campaign" : "regular", $userId);

                    $gateway            = Gateway::where('id', $gatewayId)->select(['bulk_contact_limit', 'type'])->first();
                    $typeConfig         = $gateway ? Arr::get($gatewayConfig, $gateway->type, []) : [];
                    $nativeBulkSupport  = Arr::get($typeConfig, 'meta_data', false);
                    $bulkLimit          = $gateway ? ($gateway->bulk_contact_limit ?? 1) : 1;
                    $logCount           = $logs->count();

                    if ($nativeBulkSupport && $bulkLimit > 1 && ($apiLogCount > 1 || $logCount > 1)) {

                         collect($logs)
                              ->groupBy('dispatch_unit_id')
                              ->map(function ($unitLogs) use ($gatewayId, $dispatchId, $dispatchType, $userId, $channel, $pipe, &$batches, $maxBatchSize, $delay, &$logCounter) {

                                   return $unitLogs->chunk($maxBatchSize)
                                                       ->filter(fn($chunk) => count($chunk) >= 1)
                                                       ->map(function ($chunk) use ($gatewayId, $dispatchId, $dispatchType, $userId, $channel, $pipe, &$batches, $delay, &$logCounter) {

                                                            $logCounter++;
                                                            $unitIds = $chunk->keys()->all();
                                                            $delay = $delay * $logCounter;
                                                            $this->storeDispatchDelay($gatewayId, $channel->value, $dispatchId, $dispatchType, $delay, $userId);
                                                            $job = ProcessDispatchLogBatch::dispatch($unitIds, $channel, $pipe, true)
                                                                                               ->delay(now()->addSeconds($delay));
                                                            $batches[] = $job;
                                                       });
                              })->all();
                    } else {

                         $logs->chunk($maxBatchSize)
                                   ->filter(fn($chunk) => count($chunk) >= $minBatchSize)
                                   ->map(function ($chunk) use ($gatewayId, $dispatchId, $dispatchType, $userId, $channel, $pipe, &$batches, $delay, &$logCounter) {

                                        $logCounter++;
                                        $ids      = collect($chunk)->pluck('id')->toArray();
                                        $delay    = $delay * $logCounter;

                                        $this->storeDispatchDelay($gatewayId, $channel->value, $dispatchId, $dispatchType, $delay, $userId);
                                        $job = ProcessDispatchLogBatch::dispatch($ids, $channel, $pipe, false)
                                                                           ->delay(now()->addSeconds($delay));
                                        $batches[] = $job;
                                   })->all();
                    }
               });

          return [
               'mode' => 'queued',
               'success' => true,
               'batch_count' => count($batches),
               'total_logs' => count($gatewayLogs)
          ];
     }

     /**
      * Cleanup temporary contact after single message dispatch
      * Only deletes the contact if auto-save is disabled and it's from the Quick Send group
      *
      * @param int|null $dispatchLogId
      * @param User|null $user
      * @return void
      */
     protected function cleanupTemporaryContact(?int $dispatchLogId, ?User $user = null): void
     {
          if (!$dispatchLogId) return;

          try {
               $dispatchLog = DispatchLog::find($dispatchLogId);
               if (!$dispatchLog || !$dispatchLog->contact_id) return;

               $contact = Contact::find($dispatchLog->contact_id);
               if (!$contact) return;

               // Only delete if contact is in the "Single Contact" (Quick Send) group
               $group = ContactGroup::find($contact->group_id);
               if (!$group || $group->name !== SettingKey::SINGLE_CONTACT_GROUP_NAME->value) return;

               // Check if this contact has other dispatch logs (don't delete if reused)
               $otherLogsCount = DispatchLog::where('contact_id', $contact->id)
                    ->where('id', '!=', $dispatchLogId)
                    ->count();

               if ($otherLogsCount === 0) {
                    // Safe to delete - this contact was only used for this single message
                    // Set contact_id to null in dispatch log first (for history)
                    $dispatchLog->update(['contact_id' => null]);
                    $contact->delete();
               }
          } catch (\Exception $e) {
               Log::warning('Failed to cleanup temporary contact: ' . $e->getMessage());
          }
     }

     /**
      * createMessage
      *
      * @param Request $request
      * @param array $messageData
      * @param ChannelTypeEnum $type
      * @param bool $isCampaign
      * @param User|null $user
      * 
      * @return Message
      */
     protected function createMessage(Request $request, array $messageData, ChannelTypeEnum $type, bool $isCampaign = false, ?User $user = null): Message
     {
          $template = Template::find(request()->input("whatsapp_template_id"));
          $fileInfo = $this->findAndUploadFile($request);

          if ($type == ChannelTypeEnum::EMAIL) {
               if ($request->hasFile('email_attachments')) {
                    $fileInfo = $fileInfo ?? [];
                    $fileInfo['attachments'] = $this->uploadEmailAttachments($request);
               } elseif ($request->has('email_attachment_info')) {
                    $fileInfo = $fileInfo ?? [];
                    $fileInfo['attachments'] = $request->input('email_attachment_info');
               }
          }

          return Message::create([
               'user_id'      => $user?->id,
               'type'         => $type->value,
               'message'      => $type == ChannelTypeEnum::WHATSAPP && $template
                                   ? json_encode($this->getTemplateData(request(), $template))
                                   : Arr::get($messageData, 'message_body'),

               'subject'      => Arr::get($messageData, 'subject'),
               'main_body'    => Arr::has($messageData, 'main_body')
                                   ? buildDomDocument(Arr::get($messageData, 'main_body'))
                                   : null,
               'file_info'    => $fileInfo,
               'template_id'  => request()->input("whatsapp_template_id"),
               'is_campaign'  => $isCampaign,
          ]);
     }

     /**
      * Upload email attachments and return metadata array
      *
      * @param Request $request
      * @return array
      */
     public function uploadEmailAttachments(Request $request, string $fieldName = 'email_attachments'): array
     {
          $attachments = [];
          $disk = Storage::disk('email_attachments');

          foreach ($request->file($fieldName) as $file) {
               $originalName = $file->getClientOriginalName();
               $extension = $file->getClientOriginalExtension();
               $storedFileName = uniqid() . time() . '.' . $extension;
               $mimeType = $file->getMimeType();
               $size = $file->getSize();

               try {
                    $disk->putFileAs('', $file, $storedFileName);
                    $attachments[] = [
                         'name'        => $originalName,
                         'stored_name' => $storedFileName,
                         'url_file'    => 'storage:email_attachments/' . $storedFileName,
                         'size'        => $size,
                         'mime_type'   => $mimeType,
                    ];
               } catch (\Exception $e) {
                    Log::warning('Failed to upload email attachment: ' . $e->getMessage());
               }
          }

          return $attachments;
     }

     /**
      * createCampaign
      *
      * @param Request $request
      * @param ChannelTypeEnum $type
      * @param Message $message
      * @param int|string|null $campaignId
      * @param User|null $user
      *
      * @return Campaign
      */
     protected function createCampaign(Request $request, ChannelTypeEnum $type, Message $message, int|string|null $campaignId = null, ?User $user = null): Campaign
     {
          $scheduleAt = $request->has('schedule_at') 
                              ? Carbon::parse($request->input('schedule_at'))->setTimezone(config('app.timezone'))
                              : Carbon::now();
          
          return Campaign::updateOrCreate([
               "id" => $campaignId
          ],[
               'user_id'           => $user?->id,
               'message_id'        => $message->id,
               'group_id'          => null,
               'type'              => $type->value,
               'name'              => $request->input('name'),
               'priority'          => PriorityEnum::LOW->value, 
               'repeat_format'     => $request->input('repeat_format', 'none'),
               'repeat_time'       => $request->has('repeat_time') 
                                        ? (int)$request->input('repeat_time') 
                                        : 0,
               'status'            => CampaignStatusEnum::ACTIVE->value,
               'schedule_at'       => $scheduleAt,
               'meta_data'         => [
                    'repeat_format'     => $request->input('repeat_format'),
                    'sms_type'          => $request->input('sms_type'),
                    'email_from_name'   => $request->input('email_from_name'),
                    'reply_to_address'   => $request->input('reply_to_address'),
               ],
          ]);
     }

     /**
      * prepareDispatchLogs
      *
      * @param Request $request
      * @param mixed $groups
      * @param Message $message
      * @param ChannelTypeEnum $type
      * @param string|null $scheduleAt
      * @param Campaign|null $campaign
      * @param array|null $metaData
      * @param User|null $user
      * 
      * @return array
      */
     protected function prepareDispatchLogs(Request $request, $groups, Message $message, ChannelTypeEnum $type, ?string $scheduleAt, ?Campaign $campaign = null, ?array $metaData = null, ?User $user = null): array
     {
          $dispatchLogs = [];
          $now = Carbon::now();
          $contactColumn = $type->value . "_contact";
          
          LazyCollection::make($groups)
               ->map(function ($group) use ($contactColumn, $request) {
                    $group = $group->load(["contacts"]);
                    if ($group->name == SettingKey::SINGLE_CONTACT_GROUP_NAME->value) {
                         
                         return $group->contacts
                                             ->whereNotNull($contactColumn)
                                             ->when(!is_array($request->input("contacts")), fn($q) => 
                                                  $q->where($contactColumn, $request->input("contacts"))
                                                       ->first());
                    } else {
                         return $group->contacts->whereNotNull($contactColumn);
                    }
               })
               ->flatten()
               ->chunk(1000)
               ->each(function ($chunk) use ($message, $type, $scheduleAt, $campaign, $user, $now, &$dispatchLogs, $metaData) {
                    $chunk->each(function ($contact) use ($message, $type, $scheduleAt, $campaign, $user, $now, &$dispatchLogs, $metaData) {
                         
                         if ($type == ChannelTypeEnum::EMAIL 
                         && (site_settings('email_contact_verification') == StatusEnum::TRUE->status() 
                              || site_settings('email_contact_verification') == Status::ACTIVE->value)
                         && $contact->email_verification != EmailVerificationStatusEnum::VERIFIED) {
                              return;
                         }
                         // Always inject an unsubscribe link for email sends, whether the message
                         // is part of a campaign or an ad-hoc one-off send. Anti-spam compliance
                         // requires List-Unsubscribe / CAN-SPAM on every commercial email.
                         if ($type == ChannelTypeEnum::EMAIL) {
                              $metaData = Arr::set(
                                  $metaData,
                                  "unsubscribe_link",
                                  $this->generateUnsubscribeLink($campaign?->id, $contact->uid, $type, $user?->id)
                              );
                         }

                         $log = [];
                         Arr::set($log, 'user_id', $user?->id);
                         Arr::set($log, 'message_id', $message->id);
                         Arr::set($log, 'contact_id', $contact->id);
                         Arr::set($log, 'campaign_id', $campaign?->id);
                         Arr::set($log, 'type', $type->value);
                         Arr::set($log, 'gatewayable_id', null); // Set by assignGateway later
                         Arr::set($log, 'gatewayable_type', null); // Set by assignGateway later
                         Arr::set($log, 'priority', PriorityEnum::LOW->value);
                         Arr::set($log, 'status', $scheduleAt 
                                                                           ? CommunicationStatusEnum::SCHEDULE->value
                                                                           : CommunicationStatusEnum::PENDING->value);
                         
                         Arr::set($log, 'scheduled_at', $scheduleAt 
                              ? Carbon::parse($scheduleAt)->setTimezone(config('app.timezone')) 
                              : null);
                         Arr::set($log, 'meta_data', json_encode($metaData));
                         Arr::set($log, 'created_at', $now);
                         Arr::set($log, 'updated_at', $now);
                         $dispatchLogs[] = $log;
                    });
               });
          return $dispatchLogs;
     }

     /**
      * generateUnsubscribeLink
      *
      * @param int $campaign_id
      * @param string $contact_uid
      * @param ChannelTypeEnum $channel
      * 
      * @return string
      */
     public function generateUnsubscribeLink(?int $campaign_id, string $contact_uid, ChannelTypeEnum $channel, ?int $user_id = null): string
     {
         $params = [
             'contact_id' => encrypt($contact_uid),
             'channel'    => $channel->value,
         ];

         if ($campaign_id !== null) {
             $params['campaign_id'] = encrypt($campaign_id);
         } elseif ($user_id !== null) {
             // Ad-hoc message (no campaign): tag the link with the sender so the controller
             // knows who owns the unsubscribe record.
             $params['user_id'] = encrypt($user_id);
         }

         return route('unsubscribe', $params);
     }
 

     /**
      * updateStatusesForAndroid
      *
      * @param AndroidSession $androidSession
      * @param array $logs
      * 
      * @return JsonResponse
      */
     public function updateStatusesForAndroid(AndroidSession $androidSession, array $logs): JsonResponse
     {
          $logIds = array_column($logs, 'id');
          
          $invalidLogs = DispatchLog::select('id', 'status')
                                        ->whereIn('id', $logIds)
                                        ->where('gatewayable_type', AndroidSim::class)
                                        ->where(function ($query) use ($androidSession) {
                                             $query->where('status', '!=', CommunicationStatusEnum::PROCESSING->value)
                                                  ->when("gatewayable_type" instanceof AndroidSim, fn(Builder $q): Builder =>
                                                  $q->orWhereHas('gatewayable', function ($subQuery) use ($androidSession) {
                                                       $subQuery->where('android_session_id', '!=', $androidSession->id);
                                                  }));
                                        })->get()
                                             ->mapWithKeys(function ($log) {
                                                  return [$log->id => $log->status->value];
                                             })->toArray();
          
          if (!empty($invalidLogs)) {

               $invalidId          = array_key_first($invalidLogs);
               $invalidStatus      = Arr::get($invalidLogs, $invalidId);
               $hasMatchingSession = DispatchLog::where('id', $invalidId)
                                                       ->where('gatewayable_type', AndroidSim::class)
                                                       ->when("gatewayable_type" instanceof AndroidSim, fn(Builder $q): Builder =>
                                                            $q->whereHas('gatewayable', function ($query) use ($androidSession) {
                                                                 $query->where('android_session_id', $androidSession->id);
                                                            }))->exists();
     
               if ($invalidStatus != CommunicationStatusEnum::PROCESSING->value) 
                    return ApiJsonResponse::error(
                         translate("DispatchLog with ID "). $invalidId. translate(" cannot be updated because its status is "). $invalidStatus. translate(", only logs with status 'processing' can be updated."),
                         null,
                         403
                    );
               
     
               if (!$hasMatchingSession) 
                    return ApiJsonResponse::error(
                         translate("DispatchLog with ID"). $invalidId .translate("does not belong to this Android session."),
                         null,
                         403
                    );
          }
     
          $updatedCount = $this->dispatchManager->updateDispatchLogStatuses($androidSession, $logs);
     
          if ($updatedCount === 0) 
               return ApiJsonResponse::success(
                    translate('No dispatch log statuses were updated'),
                    null,
                    200
               );
          
     
          return ApiJsonResponse::success(
               translate("Successfully updated"). $updatedCount. translate("dispatch log statuses"),
               ['updated_count' => $updatedCount]
          );
     }

     /**
      * destroyDispatchLog
      *
      * @param string|int|null $id
      * @param User|null $user
      * 
      * @return RedirectResponse
      */
     public function destroyDispatchLog(string|int|null $id, ?User $user = null): RedirectResponse {
          
          $dispatchLog = $this->dispatchManager->getSpecificDispatchLog($id);
          if(!$dispatchLog) throw new ApplicationException('Invalid dispatch log', 401);
          $message = $dispatchLog->message;
          
          $dispatchLog->delete();
          if($message) {

               $messageId = $message->id;
               $remainingDispatchLogs = $this->dispatchManager->dispatchLogsExists(column: "message_id", value: $messageId);
               if (!$remainingDispatchLogs) $message->delete();
          }

          return returnBackWithResponse(status: "succ:ess", message:"Successfully deleted dispatch log");  
     }

     /**
      * updateDispatchLogStatus
      *
      * @param ChannelTypeEnum $channel
      * @param Request $request
      * @param User|null $user
      * 
      * @return RedirectResponse
      */
     public function updateDispatchLogStatus(ChannelTypeEnum $channel, Request $request, ?User $user = null): RedirectResponse{
          
          if(!$request->input("status")) throw new ApplicationException('Invalid Status', 401);
          $dispatchLog = $this->dispatchManager->getSpecificDispatchLog($request->input("id"));
          if(!$dispatchLog) throw new ApplicationException('Invalid dispatch log', 401);

          $status   = CommunicationStatusEnum::from($request->input("status"));
          if($user && $status == CommunicationStatusEnum::CANCEL && $dispatchLog->status != CommunicationStatusEnum::PENDING) throw new ApplicationException('Only pending logs status can be updated', Response::HTTP_FORBIDDEN);
          $response = false;
          $response = DB::transaction(function() use($channel, $request, $dispatchLog, $user, $status): bool {

               $dispatchLog->status = $status;
               $dispatchLog->response_message = $request->input("response_message");
               if(@$dispatchLog?->user) {

                    $column = $channel->value."_credit";
                    $currentCredit = $dispatchLog->user->$column;
                    if($currentCredit >= 0 ) {

                         $dispatchLog->user->$column = 
                              $status == CommunicationStatusEnum::DELIVERED
                                             ? --$dispatchLog->user->$column
                                             : (CommunicationStatusEnum::FAIL
                                                  ? ++$dispatchLog->user->$column
                                                  : $dispatchLog->user->$column);
                         $dispatchLog->user->save();
                    }
               }
               $dispatchLog->save();
               return true;
          });

          return returnBackWithResponse(
               status: $response 
                         ? "success"
                         : "error", 
               message: $response 
                    ? "Successfully deleted dispatch log"
                    : "Could not update log status");  
     }

     ##-------------------##
     ## Campaign Dispatch ##
     ##-------------------##

     /**
      * loadCampaignLogs
      *
      * @param ChannelTypeEnum $channel
      * @param User|null $user
      * 
      * @return View
      */
     public function loadCampaignLogs(ChannelTypeEnum $channel, ?User $user = null): View
     {
          $title              = translate("{$channel->value} Campaign Logs");
          $logs               = $this->dispatchManager->getCampaignLogs(channel: $channel, user: $user);

          $panelType = $user ? "user" : "admin";
          return view("{$panelType}.communication.campaigns", 
                    compact('title', 'logs'));
     }

     /**
      * createCampaignLog
      *
      * @param ChannelTypeEnum $channel
      * @param User|null $user
      * 
      * @return View
      */
     public function createCampaignLog(ChannelTypeEnum $channel, ?User $user = null): View
     {
          $type               = $channel->value;
          $title              = translate("Create an {$channel->value} campaign");
          $groups             = $this->contactService->getChannelSpecificGroup(channel: $channel, user: $user);
          $templates          = $this->templateService->getChannelSpecificTemplates(channel: $channel, user: $user);
          $gateways           = $this->gatewayManager->getGateways(channel: $channel, loadPaginated: false, user: $user);
          $gateways           = $gateways->groupBy('type')
                                             ->map(function ($group, $type) {
                                                  return $group->mapWithKeys(function ($gateway) {
                                                       return [$gateway->id => $gateway->name];
                                                  })->toArray();
                                             })->toArray();
          $androidSessions    = $channel == ChannelTypeEnum::SMS 
                                   ? $this->gatewayManager->getAndroidSessions(loadPaginated: $loadPaginated = false, user: $user)
                                   : null;   

          $planAccess = null;
          if($user) $planAccess = (object)planAccess($user);

          $panelType = $user ? "user" : "admin";
          
          return view("{$panelType}.communication.{$channel->value}.campaign.create", compact('title', 'groups', 'type', 'templates', 'gateways', 'androidSessions', 'planAccess'));
     }

     /**
      * showCampaignLog
      *
      * @param ChannelTypeEnum $channel
      * @param int|string $id
      * @param User|null $user
      * 
      * @return View
      */
     public function showCampaignLog(ChannelTypeEnum $channel, int|string $id, ?User $user = null): View {

          $type               = $channel->value;
          $title              = translate("Create an {$channel->value} campaign");
          $campaign           = $this->dispatchManager->getSpecificCampaignLog(id: $id, user: $user);
          $groups             = $this->contactService->getChannelSpecificGroup(channel: $channel, user: $user);
          $templates          = $this->templateService->getChannelSpecificTemplates(channel: $channel, user: $user);
          $gateways           = $this->gatewayManager->getGateways(channel: $channel, user: $user); 
          $gateways           = $gateways->groupBy('type')
                                             ->map(function ($group, $type) {
                                                  return $group->mapWithKeys(function ($gateway) {
                                                  return [$gateway->id => $gateway->name];
                                                  })->toArray();
                                             })->toArray();    
          $androidSessions    = $channel == ChannelTypeEnum::SMS 
                                   ? $this->gatewayManager->getAndroidSessions(loadPaginated: $loadPaginated = false, user: $user)
                                   : null;   

          $planAccess = null;
          if($user) $planAccess = (object)planAccess($user);

          $panelType = $user ? "user" : "admin";
          return view("{$panelType}.communication.{$channel->value}.campaign.edit", 
               compact('title', 'groups', 'type', 'templates', 'gateways', 'androidSessions', 'campaign', 'planAccess'));
     }

     /**
      * destroyCampaignLog
      *
      * @param string|int|null $id
      * @param User|null $user
      * 
      * @return RedirectResponse
      */
     public function destroyCampaignLog(string|int|null $id, ?User $user = null): RedirectResponse {
          
          $campaignLog = $this->dispatchManager->getSpecificCampaignLog(id: $id, user: $user);
          if(!$campaignLog) throw new ApplicationException('Invalid dispatch log', 401);
          
          $campaignLog?->dispatchLogs();
          $campaignLog?->message();
          $campaignLog->delete();


          return returnBackWithResponse(status: "success", message:"Successfully deleted campaign");  
     }


     ## Old Functions

     public function getTemplateData($request, $template) {

        
          $template_message = $template["template_data"]["components"];
          $request_data     = $request->all();
          $matches = []; $i = 0; $message = []; $data = [];
          
          foreach ($request_data as $request_key => $request_value) {
              
              if (str_contains($request_key, "_placeholder_")) {
  
                  preg_match('/([a-z]+)_placeholder_(\d+)/', $request_key, $match);
                  $matches[]          = $match;
                  $data[$request_key] = $request_value;
              }
              if (str_contains($request_key, "_header_media")) {
  
                  $fileType = explode('_', $request_key)[0];
                  $fileLink = "";
                  
                  if ($fileType == "image") { $fileLink = storeCloudMediaAndGetLink('image_header_media', $request->file('image_header_media')); } 
                  elseif ($fileType == "video") { $fileLink = storeCloudMediaAndGetLink('video_header_media', $request->file('video_header_media')); } 
                  elseif ($fileType == "document") { $fileLink = storeCloudMediaAndGetLink('document_header_media', $request->file('document_header_media')); }
  
                  preg_match('/([a-z]+)_header_media/', $request_key, $match);
                  $match[]            = "header_media"; 
                  $match[]            = $fileLink; 
                  $matches[]          = $match;
                  $data[$request_key] = $request_value;
              }
              if (str_contains($request_key, "_button_")) {
  
                  preg_match('/([a-z]+)_button_(\d+)/', $request_key, $match);
              
                  $match[]   = $request_value; 
                  $matches[] = $match;
                  $data[]    = $match; 
              }
              if (str_contains($request_key, "flow_")) {
                  preg_match('/(flow)_([a-z]+)/', $request_key, $match); 
                  $match[]   = $request_value; 
                  $match[1] = "flow"; 
                  $matches[] = $match;
                  $data[]    = $match; 
              }
          }
          array_column($matches, 1);
          $k = 0;
          $t = 0;
          foreach ($matches as $value) {
              
              $type                 = strtoupper($value[1]); 
              $number               = $value[2];
              
              $template_message_key = array_search($type, array_column($template_message, 'type'));
              
              if ($template_message_key !== false || preg_match('/button/', $value[0]) || preg_match('/_header_media/', $value[0]) ||  preg_match('/flow_cloud/', $value[0])) {
                  
                  if ($value[1] == "header") {
                      
                      foreach($template_message[$template_message_key]['example']["$value[1]_text"] as $template_key => $template_value) {
                          
                          $message[$template_message_key]["type"]         = strtolower($template_message[$template_message_key]["type"]);
                          $message[$template_message_key]["parameters"][] = [
                              "type" => strtolower($template_message[$template_message_key]["format"]),
                              strtolower($template_message[$template_message_key]["format"]) => $request_data["$value[1]_placeholder_$template_key"]
                          ];
                      }
                  } elseif ($value[1] == "reply") {
  
                      $message[] = [
                          "type"       => "button",
                          "sub_type"   => "QUICK_REPLY",
                          "index"      => $value[2],
                          "parameters" => [
                              [
                                  "type" => "text",
                                  "text" => $value[3],
                              ]
                          ],
                      ];
  
                  } elseif ($value[1] == "code") {
                      
                      $message[3] = [
                          "type"       => "button",
                          "sub_type"   => "COPY_CODE",
                          "index"      => $value[2],
                          "parameters" => [
                              [
                                  "type" => "coupon_code",
                                  "coupon_code" => $value[3],
                              ]
                          ],
                      ];
                      
                  } elseif ($value[1] == "url") {
                      
                      $message[4] = [
                          "type"       => "button",
                          "sub_type"   => "URL",
                          "index"      => $value[2],
                          "parameters" => [
                              [
                                  "type" => "text",
                                  "text" => substr($value[3], strlen($template_message[$t]['buttons'][0]['example'][0] ?? '')),
                              ]
                          ],
                      ];
                  } elseif ($value[1] == "flow") {
                      
                      $flow_key = null;
                      foreach ($template_message as $index => $item) {
                          if (isset($item['buttons'])) {
                              foreach ($item['buttons'] as $button) {
                                  if ($button['type'] === 'FLOW') {
                                      $flow_key = $index; 
                                      break 2; 
                                  }
                              }
                          }
                      }
                      $message[3] = [
                          "type"       => "button",
                          "sub_type"   => "FLOW",
                          "index"      => 0,
                          "parameters" => [
                              [
                                  "type" => "action",
                                  "action" => [
                                      "flow_token" => "unused",
                                  ]
                              ]
                          ],
                      ];
                      
                  } elseif ($value[2] === 'header_media') {
  
                      $message[] = [
                          "type"       => "header",
                          "parameters" => [
                              [
                                  "type"  => strtolower($value[1]),
                                  strtolower($value[1]) => [
                                      "link" => $value[3],
                                  ],
                              ]
                          ],
                      ];
                  } else {
                     
                      foreach($template_message[$template_message_key]['example']["$value[1]_text"] as $template_key => $template_value) {
                          
                          $message[$template_message_key]["type"]         = strtolower($template_message[$template_message_key]["type"]);
                          $message[$template_message_key]["parameters"][] = [
                              "type" => "text",
                              "text" => $data["body_placeholder_$k"]
                          ];
                          $k++;
                      }
                    
                  } 
              }
              $t++;
          }
          return $message;
     }

     public function findAndUploadFile($request): ?array {

          $fileTypes = ['image', 'document', 'audio', 'video', 'others'];

          foreach ($fileTypes as $fileType) {

               if ($request->hasFile($fileType)) {

                    $file = $request->file($fileType);
                    $originalName = $file->getClientOriginalName(); // Get original filename from API
                    $extension = $file->getClientOriginalExtension() ?: pathinfo($originalName, PATHINFO_EXTENSION);
                    $storedFileName = uniqid() . time() . '.' . $extension;
                    $path = filePath()['whatsapp']['path_' . $fileType];

                    if (!file_exists($path)) {
                         mkdir($path, 0777, true);
                    }

                    try {
                         $file->move($path, $storedFileName);

                         // Clean the original filename for display in WhatsApp
                         $displayName = $this->cleanDisplayFilename($originalName, $extension);

                         return [
                              'type'        => $fileType,
                              'url_file'    => $path . '/' . $storedFileName,
                              'name'        => $displayName, // User-friendly display name
                              'stored_name' => $storedFileName, // Actual stored filename
                         ];
                    } catch (\Exception $e) {
                         return null;
                    }
               }
          }

          return null;
     }

     /**
      * Upload multiple WhatsApp media files
      * Returns array of file info objects for multi-media sequence sending
      *
      * @param Request $request
      * @return array Array of file info arrays
      */
     public function findAndUploadMultipleFiles(Request $request): array
     {
          $uploadedFiles = [];

          if (!$request->hasFile('media_files')) {
               return $uploadedFiles;
          }

          $files = $request->file('media_files');
          if (!is_array($files)) {
               $files = [$files];
          }

          foreach ($files as $file) {
               $originalName = $file->getClientOriginalName();
               $extension = $file->getClientOriginalExtension() ?: pathinfo($originalName, PATHINFO_EXTENSION);
               $mimeType = $file->getMimeType() ?? '';
               $storedFileName = uniqid() . time() . '.' . $extension;

               // Determine file type from MIME
               $fileType = 'document';
               if (str_starts_with($mimeType, 'image/')) {
                    $fileType = 'image';
               } elseif (str_starts_with($mimeType, 'audio/')) {
                    $fileType = 'audio';
               } elseif (str_starts_with($mimeType, 'video/')) {
                    $fileType = 'video';
               }

               $path = filePath()['whatsapp']['path_' . $fileType];
               if (!file_exists($path)) {
                    mkdir($path, 0777, true);
               }

               try {
                    $file->move($path, $storedFileName);
                    $displayName = $this->cleanDisplayFilename($originalName, $extension);

                    $uploadedFiles[] = [
                         'type'        => $fileType,
                         'url_file'    => $path . '/' . $storedFileName,
                         'name'        => $displayName,
                         'stored_name' => $storedFileName,
                    ];
               } catch (\Exception $e) {
                    // Skip failed uploads, continue with others
                    continue;
               }
          }

          return $uploadedFiles;
     }

     /**
      * Create additional Message + DispatchLog entries for multi-media WhatsApp sequence
      * WhatsApp only supports 1 media per message, so we split into sequential messages
      *
      * @param array $additionalFiles Array of file info arrays (from findAndUploadMultipleFiles)
      * @param array $dispatchLogs The original dispatch logs (to duplicate contacts/gateways)
      * @param Message $originalMessage The original message (for user/type reference)
      * @param Campaign|null $campaign The campaign if applicable
      * @param User|null $user
      * @return array Additional dispatch logs created
      */
     public function createMultiMediaSequence(
          array $additionalFiles,
          array $dispatchLogs,
          Message $originalMessage,
          $campaign = null,
          ?User $user = null
     ): array {
          $additionalLogs = [];

          foreach ($additionalFiles as $index => $fileInfo) {
               // Create a new Message for each additional media (no text, just media)
               $mediaMessage = Message::create([
                    'user_id'      => $originalMessage->user_id,
                    'type'         => $originalMessage->type,
                    'message'      => '', // No text for additional media messages
                    'subject'      => null,
                    'main_body'    => null,
                    'file_info'    => $fileInfo,
                    'template_id'  => null,
                    'is_campaign'  => $originalMessage->is_campaign,
               ]);

               // Create a dispatch log for each contact, mirroring the original logs
               foreach ($dispatchLogs as $log) {
                    $additionalLogs[] = [
                         'user_id'          => $log['user_id'] ?? $user?->id,
                         'message_id'       => $mediaMessage->id,
                         'contact_id'       => $log['contact_id'],
                         'campaign_id'      => $campaign?->id ?? $log['campaign_id'] ?? null,
                         'type'             => $log['type'],
                         'gatewayable_id'   => $log['gatewayable_id'],
                         'gatewayable_type' => $log['gatewayable_type'],
                         'priority'         => $log['priority'] ?? 'medium',
                         'status'           => $log['status'],
                         'meta_data'        => array_merge($log['meta_data'] ?? [], [
                              'multi_media_sequence' => $index + 2, // 1-based, first media is #1
                              'parent_message_id'    => $originalMessage->id,
                         ]),
                         'scheduled_at'     => isset($log['scheduled_at'])
                              ? Carbon::parse($log['scheduled_at'])->addSeconds(($index + 1) * 3) // 3s delay between each media
                              : now()->addSeconds(($index + 1) * 3),
                         'sent_at'          => null,
                         'processed_at'     => null,
                         'created_at'       => now(),
                         'updated_at'       => now(),
                    ];
               }
          }

          // Bulk insert all additional logs
          if (!empty($additionalLogs)) {
               DispatchLog::insert($additionalLogs);
          }

          return $additionalLogs;
     }

     /**
      * Clean the display filename to be user-friendly
      * Handles URL-encoded names and ensures proper formatting
      *
      * @param string $filename
      * @param string $extension
      * @return string
      */
     protected function cleanDisplayFilename(string $filename, string $extension): string
     {
          // Decode URL-encoded characters
          $filename = urldecode($filename);

          // Remove path components
          $filename = basename($filename);

          // Check if this looks like a URL-converted filename and clean it up
          if (preg_match('/^https?_|_{3,}/', $filename)) {
               $filename = preg_replace('/^https?_+/', '', $filename);
               $filename = preg_replace('/_{2,}/', '_', $filename);
          }

          // Ensure it has the extension
          $currentExt = pathinfo($filename, PATHINFO_EXTENSION);
          if (!$currentExt && $extension) {
               $filename .= '.' . $extension;
          }

          // Remove problematic characters
          $filename = preg_replace('/[<>:"|?*]/', '', $filename);

          // If filename is empty or just extension, generate default
          $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
          if (empty($nameWithoutExt)) {
               $filename = 'document_' . time() . '.' . $extension;
          }

          return $filename;
     }
     
     /**
      * storeDispatchDelay
      *
      * @param string|int $gatewayId
      * @param string|ChannelTypeEnum $channel
      * @param string|int $dispatchId
      * @param string|DispatchTypeEnum $dispatchType
      * @param int|float|null|null $delayValue
      * @param int|null $userId
      * 
      * @return DispatchDelay
      */
     public function storeDispatchDelay(string|int $gatewayId, string|ChannelTypeEnum $channel, string|int $dispatchId, string|DispatchTypeEnum $dispatchType, int|float|null $delayValue = null, ?int $userId = null): DispatchDelay
     {
          return DispatchDelay::updateOrCreate([
               'user_id'           => $userId,
               'gateway_id'        => $gatewayId,
               'channel'           => $channel,
               'dispatch_id'       => $dispatchId,
               'dispatch_type'     => $dispatchType,
          ], [
               'delay_value'  => $delayValue,
               'applies_from' => Carbon::now(),
          ]);
     }
}