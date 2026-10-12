<?php

namespace App\Jobs;

use App\Enums\CommunicationStatusEnum;
use App\Enums\StatusEnum;
use App\Enums\System\Gateway\WhatsAppGatewayTypeEnum;
use App\Http\Utility\SendWhatsapp;
use App\Models\CommunicationLog;
use App\Models\DispatchLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\WhatsappLog;
use Illuminate\Support\Facades\Log;
use Exception;

class ProcessWhatsapp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 30;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 3;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(protected CommunicationLog $whatsappLog){}

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        try {
            $whatsappLog = $this->whatsappLog;

            // Skip if already processed or failed
            if ($whatsappLog->status == CommunicationStatusEnum::SUCCESS->value ||
                $whatsappLog->status == CommunicationStatusEnum::FAIL->value) {
                Log::debug("WhatsApp job skipped - already processed", [
                    'log_id' => $whatsappLog->id,
                    'status' => $whatsappLog->status,
                ]);
                return;
            }

            // Get the gateway
            $gateway = $whatsappLog->whatsappGateway;

            if (!$gateway) {
                throw new Exception("WhatsApp gateway not found for log: {$whatsappLog->id}");
            }

            // Check gateway type and route to appropriate handler
            if ($gateway->type == WhatsAppGatewayTypeEnum::NODE->value) {
                // Node/QR Code based WhatsApp
                $this->processNodeMessage($whatsappLog, $gateway);
            } elseif ($gateway->type == WhatsAppGatewayTypeEnum::CLOUD->value) {
                // Cloud API (Meta Business) WhatsApp
                $this->processCloudMessage($whatsappLog, $gateway);
            } else {
                throw new Exception("Unknown gateway type: {$gateway->type}");
            }

        } catch (Exception $exception) {
            Log::error("ProcessWhatsapp job failed", [
                'log_id' => $this->whatsappLog->id ?? 'unknown',
                'error' => $exception->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            // Update status to failed if max attempts reached
            if ($this->attempts() >= $this->tries) {
                $this->markAsFailed($exception->getMessage());
            } else {
                // Re-throw to trigger retry
                throw $exception;
            }
        }
    }

    /**
     * Process message via Node/QR Code WhatsApp
     */
    private function processNodeMessage($whatsappLog, $gateway): void
    {
        $sendWhatsapp = new SendWhatsapp();

        // Get dispatch log if available
        $dispatchLog = DispatchLog::where('communication_log_id', $whatsappLog->id)->first();

        if ($dispatchLog) {
            // Use the dispatch-based sending with full features
            $message = $dispatchLog->message;
            $contact = $dispatchLog->contact;
            $to = $contact ? $contact->whatsapp : ($whatsappLog->message['to'] ?? '');
            $body = $whatsappLog->message['message_body'] ?? '';

            $success = $sendWhatsapp->send($gateway, $to, $dispatchLog, $message, $body);

            if (!$success) {
                throw new Exception("Failed to send WhatsApp message via Node");
            }
        } else {
            // Direct sending without dispatch log
            Log::warning("No dispatch log found for WhatsApp communication log", [
                'log_id' => $whatsappLog->id,
            ]);
        }

        // Mark as success
        $this->markAsSuccess();
    }

    /**
     * Process message via Cloud API (Meta Business)
     */
    private function processCloudMessage($whatsappLog, $gateway): void
    {
        // Get dispatch log
        $dispatchLog = DispatchLog::where('communication_log_id', $whatsappLog->id)->first();

        if (!$dispatchLog) {
            throw new Exception("Dispatch log not found for Cloud API message");
        }

        $message = $dispatchLog->message;
        $contact = $dispatchLog->contact;
        $to = $contact ? $contact->whatsapp : ($whatsappLog->message['to'] ?? '');
        $body = $whatsappLog->message['message_body'] ?? '';

        $success = SendWhatsapp::sendCloudApiMessages($dispatchLog, $gateway, $message, $body, $to);

        if (!$success) {
            throw new Exception("Failed to send WhatsApp message via Cloud API");
        }

        // Mark as success
        $this->markAsSuccess();
    }

    /**
     * Mark the log as successfully sent
     */
    private function markAsSuccess(): void
    {
        $this->whatsappLog->status = CommunicationStatusEnum::SUCCESS->value;
        $this->whatsappLog->save();

        Log::info("WhatsApp message sent successfully", [
            'log_id' => $this->whatsappLog->id,
        ]);
    }

    /**
     * Mark the log as failed
     */
    private function markAsFailed(string $reason): void
    {
        $this->whatsappLog->status = CommunicationStatusEnum::FAIL->value;
        $this->whatsappLog->response_message = $reason;
        $this->whatsappLog->save();

        Log::error("WhatsApp message failed permanently", [
            'log_id' => $this->whatsappLog->id,
            'reason' => $reason,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $this->markAsFailed($exception->getMessage());

        Log::error("ProcessWhatsapp job permanently failed", [
            'log_id' => $this->whatsappLog->id ?? 'unknown',
            'error' => $exception->getMessage(),
        ]);
    }
}
