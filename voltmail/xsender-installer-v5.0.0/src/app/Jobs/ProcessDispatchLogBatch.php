<?php

namespace App\Jobs;

use App\Http\Utility\SendWhatsapp;
use Carbon\Carbon;
use App\Models\Gateway;
use App\Models\DispatchLog;
use App\Models\DispatchUnit;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use App\Http\Utility\SendSMS;
use App\Http\Utility\SendMail;
use Illuminate\Support\Facades\DB;
use App\Enums\System\ChannelTypeEnum;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Database\Eloquent\Collection;
use App\Enums\System\CommunicationStatusEnum;
use App\Enums\System\Gateway\WhatsAppGatewayTypeEnum;
use App\Models\User;
use App\Service\Admin\Core\CustomerService;
use App\Services\System\Communication\DispatchService;
use App\Http\Controllers\TrackingController;
use App\Models\EmailSuppression;
use App\Enums\StatusEnum;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class ProcessDispatchLogBatch implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $ids;
    protected $pipe;
    protected $isBulk;
    protected $channel;

    /**
     * __construct
     *
     * @param array $ids
     * @param ChannelTypeEnum $channel
     * @param string $pipe
     * @param bool $isBulk
     */
    public function __construct(array $ids, ChannelTypeEnum $channel, string $pipe, bool $isBulk)
    {
        $this->ids     = $ids;
        $this->pipe    = $pipe;
        $this->isBulk  = $isBulk;
        $this->channel = $channel;
        $this->onQueue(config("queue.pipes.{$pipe}.{$channel->value}"));
    }

    /**
     * handle
     *
     * @return void
     */
    public function handle(): void
    {
        $sendSMS            = new SendSMS();
        $sendMail           = new SendMail();
        $sendWhatsapp       = new SendWhatsapp();
        $customerService    = new CustomerService();
        $dispatchService    = new DispatchService();

        if ($this->isBulk) {
            DispatchUnit::whereIn('id', $this->ids)
                ->with([
                    'message',
                    'gateway',
                    'dispatchLogs' => function ($query) {
                        $query->where('status', CommunicationStatusEnum::PENDING)
                            ->with(['message', 'contact', 'gatewayable']);
                    }
                ])
                ->lazyById()
                ->each(function (DispatchUnit $unit) use ($customerService, $dispatchService) {
                    $logs = $unit->dispatchLogs;
                    if ($logs->isEmpty()) return;
                    $gateway = $unit->gateway;
                    if (!$gateway) {
                        $this->failUnit($unit, $logs, translate("Gateway could not be used"), $customerService, $dispatchService);
                        return;
                    }
                    try {
                        $this->processBulkUnit($unit, $logs, $gateway);
                    } catch (Exception $e) {
                        $this->failUnit($unit, $logs, $e->getMessage(), $customerService, $dispatchService);
                    }
                });
        } else {
            DispatchLog::whereIn('id', $this->ids)
                ->where('status', CommunicationStatusEnum::PENDING)
                ->with(['contact', 'message', 'gatewayable'])
                ->lazyById()
                ->each(function (DispatchLog $log) use ($sendSMS, $sendMail, $sendWhatsapp, $customerService, $dispatchService) {
                    try {
                        $this->processSingleLog($log, $sendSMS, $sendMail, $sendWhatsapp, $dispatchService, $customerService);
                    } catch (Exception $e) {
                        $this->failLog($log, $e->getMessage(), $customerService, $dispatchService);
                    }
                });
        }
    }

    /**
     * processBulkUnit
     *
     * @param DispatchUnit $unit
     * @param Collection $logs
     * @param Gateway $gateway
     * 
     * @return void
     */
    protected function processBulkUnit(DispatchUnit $unit, Collection $logs, Gateway $gateway): void
    {
        $message = $unit->message;
        $to = $logs->pluck("contact.{$this->channel->value}_contact", "id")->all();

        $this->updateAndApplyGatewayDelays($gateway, count($to));
        
        $sendSMS = new SendSMS();
        $sendMail = new SendMail();
        $sendWhatsapp = new SendWhatsapp();

        if ($this->channel === ChannelTypeEnum::SMS) {
            $sendSMS->send(
                strtolower($gateway->type),
                $to,
                $gateway,
                $logs,
                $message->message
            );
        } elseif ($this->channel === ChannelTypeEnum::EMAIL) {
            $emailAttachments = Arr::get($message->file_info ?? [], 'attachments');
            $sendMail->send(
                $gateway,
                $to,
                $message->subject,
                $message->main_body,
                $logs,
                $emailAttachments
            );
        } elseif ($this->channel === ChannelTypeEnum::WHATSAPP) {
            $sendWhatsapp->send(
                $gateway,
                $to,
                $logs,
                $message,
                $message->message
            );
        } else {
            throw new \Exception("Channel {$this->channel->value} not yet implemented for bulk dispatch.");
        }
        $unit->update([
            'status' => CommunicationStatusEnum::DELIVERED,
            'response_message' => translate('Bulk dispatch successful'),
        ]);
    }

    /**
     * processSingleLog
     *
     * @param DispatchLog $log
     * @param SendSMS $sendSMS
     * @param SendMail $sendMail
     * @param SendWhatsapp $sendWhatsapp
     * @param DispatchService $dispatchService
     * 
     * @return void
     */
    protected function processSingleLog(DispatchLog $log, SendSMS $sendSMS, SendMail $sendMail, SendWhatsapp $sendWhatsapp, DispatchService $dispatchService, CustomerService $customerService): void
    {
        $now        = Carbon::now();
        $message    = $log->message;
        $contact    = $log->contact;
        $gateway    = $log->gatewayable;
        $to         = $contact->{"{$this->channel->value}_contact"};
        
        if (!$message || !$contact || !$gateway || !$to) {
            $this->failLog($log, translate("Something went wrong during dispatch, please contact support"), $customerService, $dispatchService);
            return;
        }
        $this->updateAndApplyGatewayDelays($gateway, 1);

        $log->sent_at   = $now;
        $log->status    = CommunicationStatusEnum::PROCESSING;
        $log->save();
        
        if ($this->channel === ChannelTypeEnum::SMS) {
            $messageText = replaceContactVariables($contact, $message->message);
            $sendSMS->send(
                strtolower($gateway->type),
                $to,
                $gateway,
                $log,
                $messageText
            );
        } elseif ($this->channel === ChannelTypeEnum::EMAIL) {
            // Check suppression list before sending
            if (EmailSuppression::isSuppressed($to, $log->user_id)) {
                $this->failLog($log, translate('Email address is suppressed (bounced or complained)'), $customerService, $dispatchService);
                return;
            }

            $subject    = replaceContactVariables($contact, $message->subject);
            $mainBody   = replaceContactVariables($contact, $message->main_body);
            $emailAttachments = Arr::get($message->file_info ?? [], 'attachments');

            // Inject email tracking (open pixel + click wrapping)
            if (site_settings('email_tracking_enabled') == StatusEnum::TRUE->status()) {
                $mainBody = TrackingController::injectTracking($mainBody, $log->id, $log->user_id);
            }

            $sendMail->send(
                $gateway,
                $to,
                $subject,
                $mainBody,
                $log,
                $emailAttachments
            );
        } elseif ($this->channel === ChannelTypeEnum::WHATSAPP) {
            $messageText = replaceContactVariables($contact, $message->message);
            $sendWhatsapp->send(
                $gateway,
                $to,
                $log,
                $message,
                $messageText
            );
        } else {
            throw new \Exception("Channel {$this->channel->value} not yet implemented for Gateway dispatch.");
        }
    }

    /**
     * updateAndApplyGatewayDelays
     *
     * @param Gateway $gateway
     * @param int $messagesSent
     *
     * @return void
     */
    protected function updateAndApplyGatewayDelays(Gateway $gateway, int $messagesSent): void
    {
        // Use atomic increment to prevent lock timeout when multiple workers
        // update the same gateway simultaneously (MySQL error 1205)
        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($gateway, $messagesSent) {
                // Atomic increment — no read-modify-save race condition
                Gateway::where('id', $gateway->id)->increment('sent_message_count', $messagesSent);

                // Handle reset_after_count threshold
                if ($gateway->reset_after_count > 0) {
                    Gateway::where('id', $gateway->id)
                        ->where('sent_message_count', '>=', $gateway->reset_after_count)
                        ->update([
                            'sent_message_count' => \Illuminate\Support\Facades\DB::raw(
                                "sent_message_count % {$gateway->reset_after_count}"
                            )
                        ]);
                }
            }, 3); // Retry up to 3 times on deadlock
        } catch (\Throwable $e) {
            // Non-fatal — log but don't fail the message delivery
            \Illuminate\Support\Facades\Log::warning('Gateway counter update failed (non-blocking)', [
                'gateway_id' => $gateway->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * failUnit
     *
     * @param DispatchUnit $unit
     * @param mixed $logs
     * @param string $message
     * @param CustomerService $customerService
     * @param DispatchService $dispatchService
     * 
     * @return void
     */
    protected function failUnit(DispatchUnit $unit, $logs, string $message, CustomerService $customerService, DispatchService $dispatchService): void
    {
        $unit->update([
            'status' => CommunicationStatusEnum::FAIL,
            'response_message' => $message,
        ]);
        DispatchLog::where('dispatch_unit_id', $unit->id)->update([
            'status' => CommunicationStatusEnum::FAIL,
            'response_message' => $message,
            'retry_count' => DB::raw('retry_count + 1'),
        ]);
        if ($logs->isNotEmpty() && $logs->first()->user_id) {
            $user = User::find($logs->first()->user_id);
            if ($user) {
                $creditCount = $logs->count();
                $serviceType = $dispatchService->getServiceType($this->channel);
                $customerService->addedCreditLog(
                    $user,
                    $creditCount,
                    $serviceType->value,
                    false,
                    translate("Re-added {$creditCount} credits due to failed {$this->channel->name} bulk dispatch: {$message}")
                );
            }
        }
    }

    /**
     * failLog
     *
     * @param DispatchLog $log
     * @param string $message
     * @param CustomerService $customerService
     * @param DispatchService $dispatchService
     * 
     * @return void
     */
    protected function failLog(DispatchLog $log, string $message, CustomerService $customerService, DispatchService $dispatchService): void
    {
        $log->update([
            'status' => CommunicationStatusEnum::FAIL,
            'response_message' => $message,
            'retry_count' => $log->retry_count + 1,
        ]);

        if ($log->user_id) {
            $user = User::find($log->user_id);
            if ($user) {
                $serviceType = $dispatchService->getServiceType($this->channel);
                $creditCount = $this->channel === ChannelTypeEnum::WHATSAPP && !$log->whatsapp_template_id
                    ? count(str_split($log->message->message ?? '', site_settings('whatsapp_word_count')))
                    : 1;
                $customerService->addedCreditLog(
                    $user,
                    $creditCount,
                    $serviceType->value,
                    false,
                    translate("Re-added {$creditCount} credit due to failed {$this->channel->name} dispatch: {$message}")
                );
            }
        }
    }
}