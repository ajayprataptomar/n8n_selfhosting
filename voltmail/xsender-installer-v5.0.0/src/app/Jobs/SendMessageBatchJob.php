<?php

namespace App\Jobs;

use App\Enums\System\ChannelTypeEnum;
use App\Enums\System\CommunicationStatusEnum;
use App\Models\AndroidSim;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\DispatchLog;
use App\Models\Gateway;
use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendMessageBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $campaign;
    protected $message;
    protected $contact;
    protected $dispatchLog;

    public $tries = 3; // Retry up to 3 times
    public $timeout = 120; // Timeout after 120 seconds

    public function __construct(Campaign $campaign, Message $message, Contact $contact, DispatchLog $dispatchLog)
    {
        $this->campaign = $campaign;
        $this->message = $message;
        $this->contact = $contact;
        $this->dispatchLog = $dispatchLog;
    }

    public function handle()
    {
        try {

            $this->dispatchLog->update([
                'status'        => CommunicationStatusEnum::PROCESSING,
                'retry_count'   => $this->dispatchLog->retry_count + 1,
            ]);

            $gateway = $this->selectGateway();
            if ($gateway) {
                $result = $this->sendViaGateway($gateway);

                if ($result['success']) {
                    // Success: Update dispatch log
                    $this->dispatchLog->update([
                        'status' => CommunicationStatusEnum::DELIVERED,
                        'response_message' => $result['message'],
                        'sent_at' => now(),
                        'gatewayable_id' => $gateway->id,
                        'gatewayable_type' => Gateway::class,
                    ]);
                    return;
                }

                Log::warning("Gateway failed for DispatchLog ID {$this->dispatchLog->id}: {$result['message']}");
            }

            // Step 2: Failover to Android SIM if gateway fails or no gateway is available
            $androidSim = $this->selectAndroidSim();
            if ($androidSim) {
                // Mark the dispatch log for Android app processing
                $this->dispatchLog->update([
                    'status' => CommunicationStatusEnum::PENDING,
                    'gatewayable_id' => $androidSim->id,
                    'gatewayable_type' => AndroidSim::class,
                    'response_message' => 'Awaiting Android SIM processing',
                    'meta_data' => array_merge(
                        $this->dispatchLog->meta_data ?? [],
                        ['failover_reason' => 'Gateway failed or unavailable']
                    ),
                ]);

                // Apply delay based on Android SIM settings
                $this->applyDelay($androidSim);

                // The Android app will pick up this dispatch log later via API
                return;
            }

            // Step 3: No gateway or Android SIM available, mark as failed
            $this->dispatchLog->update([
                'status' => CommunicationStatusEnum::FAIL,
                'response_message' => 'No gateway or Android SIM available for failover',
            ]);

        } catch (\Exception $e) {
            // Log the error and fail the job
            Log::error("Error in SendMessageBatchJob for DispatchLog ID {$this->dispatchLog->id}: {$e->getMessage()}");

            $this->dispatchLog->update([
                'status' => CommunicationStatusEnum::FAIL,
                'response_message' => $e->getMessage(),
            ]);

            // Re-queue if retries are available
            if ($this->dispatchLog->retry_count < $this->tries) {
                $this->release(30); // Re-queue after 30 seconds
            }
        }
    }

    protected function selectGateway(): ?Gateway
    {
        return Gateway::query()
            ->where('type', $this->message->type)
            ->where('status', 'active')
            ->when($this->campaign->user_id, function ($query) {
                $query->where('user_id', $this->campaign->user_id)
                      ->orWhereNull('user_id'); // Include admin gateways
            })
            ->orderBy('is_default', 'desc') // Prioritize default gateways
            ->orderBy('id') // Consistent ordering
            ->first();
    }

    protected function sendViaGateway(Gateway $gateway): array
    {
        // Simulate sending via gateway (replace with actual gateway integration)
        // For now, we'll assume it fails 50% of the time for testing failover
        $success = rand(0, 1) === 1;

        if ($success) {
            return ['success' => true, 'message' => 'Message sent via gateway'];
        }

        return ['success' => false, 'message' => 'Gateway failed to send message'];
    }

    protected function selectAndroidSim(): ?AndroidSim
    {
        return AndroidSim::query()
            ->whereHas('androidSession', function ($query) {
                $query->where('status', 'active')
                      ->where(function ($q) {
                          $q->whereNull('expires_at')
                            ->orWhere('expires_at', '>', now());
                      })
                      ->when($this->campaign->user_id, function ($q) {
                          $q->where('user_id', $this->campaign->user_id)
                            ->orWhereNull('user_id'); // Include admin sessions
                      });
            })
            ->where('status', 'active')
            ->where('send_sms', true)
            ->orderBy('id') // Consistent ordering
            ->first();
    }

    protected function applyDelay($gatewayable)
    {
        $delay = $gatewayable->per_message_delay ?? 0;

        if ($gatewayable->delay_after_count > 0 && $gatewayable->delay_after_duration > 0) {
            // Simulate tracking message count (in a real app, use a counter or cache)
            $messageCount = $this->dispatchLog->id % $gatewayable->delay_after_count;
            if ($messageCount === 0) {
                $delay += $gatewayable->delay_after_duration;
            }
        }

        if ($delay > 0) {
            sleep($delay); // In a real app, consider using queue delays instead
            $this->dispatchLog->update([
                'applied_delay' => $delay,
            ]);
        }
    }
}