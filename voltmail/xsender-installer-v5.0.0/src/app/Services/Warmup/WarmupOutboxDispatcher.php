<?php

namespace App\Services\Warmup;

use App\Http\Utility\SendMail;
use App\Models\ContentGenerationHistory;
use App\Models\ContentResource;
use App\Models\Gateway;
use App\Models\WarmupDispatch;
use App\Models\WarmupSchedule;
use App\Services\Content\DeterministicContentEngine;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WarmupOutboxDispatcher
{
    protected DeterministicContentEngine $contentEngine;
    protected SendMail $sendMail;

    public function __construct(
        ?DeterministicContentEngine $contentEngine = null,
        ?SendMail $sendMail = null
    ) {
        $this->contentEngine = $contentEngine ?: new DeterministicContentEngine();
        $this->sendMail      = $sendMail ?: new SendMail();
    }

    /**
     * Recover stale leases from crashed workers.
     * Any dispatch in 'claimed' or 'sending' older than 5 minutes is restored to 'pending'.
     */
    public function recoverStaleLeases(int $staleMinutes = 5): int
    {
        $cutoff = Carbon::now()->subMinutes($staleMinutes);

        return WarmupDispatch::whereIn('status', [
                WarmupDispatch::STATUS_CLAIMED,
                WarmupDispatch::STATUS_SENDING,
            ])
            ->where('claimed_at', '<', $cutoff)
            ->update([
                'status'     => WarmupDispatch::STATUS_PENDING,
                'claimed_at' => null,
                'claimed_by' => null,
            ]);
    }

    /**
     * Atomically claim pending slots due for dispatch.
     * Prevents race conditions among concurrent workers.
     */
    public function claimDueSlots(int $batchSize = 20, ?string $workerId = null): Collection
    {
        $this->recoverStaleLeases();

        $workerId = $workerId ?: 'worker_' . Str::random(10);
        $now = Carbon::now();

        // 1. Identify due candidate IDs
        $candidateIds = WarmupDispatch::where('status', WarmupDispatch::STATUS_PENDING)
            ->where(function ($query) use ($now) {
                $query->whereNull('scheduled_at')
                      ->orWhere('scheduled_at', '<=', $now);
            })
            ->whereHas('schedule', function ($query) {
                $query->where('status', 'active');
            })
            ->orderBy('scheduled_at')
            ->limit($batchSize)
            ->pluck('id');

        if ($candidateIds->isEmpty()) {
            return new Collection();
        }

        // 2. Atomically claim each candidate ID
        $claimedIds = [];
        foreach ($candidateIds as $id) {
            $affected = WarmupDispatch::where('id', $id)
                ->where('status', WarmupDispatch::STATUS_PENDING)
                ->update([
                    'status'     => WarmupDispatch::STATUS_CLAIMED,
                    'claimed_at' => $now,
                    'claimed_by' => $workerId,
                ]);

            if ($affected > 0) {
                $claimedIds[] = $id;
            }
        }

        return WarmupDispatch::with(['schedule', 'gateway', 'user'])
            ->whereIn('id', $claimedIds)
            ->get();
    }

    /**
     * Execute outbox dispatch for a single claimed slot.
     */
    public function dispatchSlot(WarmupDispatch $dispatch): array
    {
        if ($dispatch->status !== WarmupDispatch::STATUS_CLAIMED) {
            return [
                'success' => false,
                'status'  => $dispatch->status,
                'error'   => 'Dispatch slot is not in claimed state',
            ];
        }

        $dispatch->update(['status' => WarmupDispatch::STATUS_SENDING]);

        // 1. Validate gateway usability
        $gateway = $dispatch->gateway;
        $gatewayStatus = $gateway?->status instanceof \BackedEnum ? $gateway->status->value : (string) $gateway?->status;
        if (!$gateway || $gatewayStatus !== 'active') {
            $dispatch->update([
                'status'        => WarmupDispatch::STATUS_FAILED,
                'error_message' => 'Associated email gateway is inactive or deleted',
            ]);

            return [
                'success' => false,
                'status'  => WarmupDispatch::STATUS_FAILED,
                'error'   => 'Gateway inactive or missing',
            ];
        }

        // 2. Generate content using the tenant's approved Content Resource
        $resource = $this->resolveContentResource($dispatch->user_id);
        $content = $this->contentEngine->generateContent($resource, [
            'intent'      => 'warmup',
            'dispatch_id' => $dispatch->id,
        ]);

        $subject = $content['subject'] ?? 'Professional inquiry';
        $body    = $content['body'] ?? '<p>Hello, following up on our recent conversation.</p>';

        // 3. Compute unique RFC 2822 Message-ID header
        $senderDomain = substr(strrchr($gateway->address, "@"), 1) ?: 'voltmail.io';
        $messageId    = sprintf('<warmup-%s@%s>', $dispatch->uid, $senderDomain);

        $dispatch->subject             = $subject;
        $dispatch->message_id          = $messageId;
        $dispatch->content_resource_id = $resource->id;

        // 4. Record usage in ContentGenerationHistory
        ContentGenerationHistory::create([
            'user_id'             => $dispatch->user_id,
            'content_resource_id' => $resource->id,
            'gateway_id'          => $gateway->id,
            'template_id'         => $content['template_id'] ?? 'standard_intro',
            'template_version'    => '1.0.0',
            'subject'             => $subject,
            'body'                => $body,
            'content_fingerprint' => $content['content_fingerprint'] ?? hash('sha256', $body),
            'variables_used'      => $content['variables_used'] ?? [],
            'dispatch_reference'  => $dispatch->uid,
            'last_used_at'        => now(),
        ]);

        // 5. Outbound transmission via SendMail reusing existing Gateway
        try {
            // Note: SendMail handles provider specific sending (SMTP, SendGrid, Mailgun, etc.)
            $sent = $this->sendMail->send(
                gateway: $gateway,
                to: $dispatch->recipient_email,
                subject: $subject,
                mailBody: $body
            );

            if ($sent) {
                $dispatch->status        = WarmupDispatch::STATUS_SENT;
                $dispatch->sent_at       = Carbon::now();
                $dispatch->response_code = '250';
                $dispatch->response_text = '250 OK - Delivered to mail transport';
                $dispatch->error_message = null;
                $dispatch->save();

                // Update schedule last_dispatched_at
                $dispatch->schedule?->update(['last_dispatched_at' => Carbon::now()]);

                return [
                    'success'    => true,
                    'status'     => WarmupDispatch::STATUS_SENT,
                    'message_id' => $messageId,
                ];
            } else {
                $errorMsg = method_exists($this->sendMail, 'getLastError') ? $this->sendMail->getLastError() : null;
                $errorMsg = $errorMsg ?: 'Unknown SMTP transport error';
                $isAmbiguous = $this->isAmbiguousNetworkError($errorMsg);

                $finalStatus = $isAmbiguous ? WarmupDispatch::STATUS_AMBIGUOUS : WarmupDispatch::STATUS_FAILED;
                $dispatch->status        = $finalStatus;
                $dispatch->error_message = $errorMsg;
                $dispatch->response_code = $isAmbiguous ? '421' : '550';
                $dispatch->save();

                return [
                    'success' => false,
                    'status'  => $finalStatus,
                    'error'   => $errorMsg,
                ];
            }
        } catch (Exception $e) {
            $isAmbiguous = $this->isAmbiguousNetworkError($e->getMessage());
            $finalStatus = $isAmbiguous ? WarmupDispatch::STATUS_AMBIGUOUS : WarmupDispatch::STATUS_FAILED;

            $dispatch->status        = $finalStatus;
            $dispatch->error_message = $e->getMessage();
            $dispatch->response_code = $isAmbiguous ? '421' : '500';
            $dispatch->save();

            return [
                'success' => false,
                'status'  => $finalStatus,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Resolve the active ContentResource for this tenant, or create an approved fallback.
     */
    protected function resolveContentResource(int $userId): ContentResource
    {
        $resource = ContentResource::where('user_id', $userId)
            ->where('status', 'active')
            ->orderByDesc('is_default')
            ->first();

        if ($resource) {
            return $resource;
        }

        // Return a baseline business facts resource
        return ContentResource::firstOrCreate(
            ['user_id' => $userId, 'name' => 'Default Business Profile'],
            [
                'company_name'        => 'Voltmail Business',
                'business_type'       => 'Cloud Communications',
                'company_description' => 'Reliable, enterprise email infrastructure and deliverability solutions.',
                'industry'            => 'Software & Communications',
                'target_audience'     => 'Modern businesses and delivery professionals',
                'key_products'        => [['name' => 'Cloud Gateway', 'benefit' => 'Reliable high-volume message delivery']],
                'status'              => 'active',
                'is_default'          => true,
            ]
        );
    }

    /**
     * Read-only preview of pending slots due for dispatch.
     * Does NOT recover stale leases, claim records, or update database state.
     */
    public function getDueSlotsPreview(int $batchSize = 20): Collection
    {
        $now = Carbon::now();

        return WarmupDispatch::with(['schedule', 'gateway', 'user'])
            ->where('status', WarmupDispatch::STATUS_PENDING)
            ->where(function ($query) use ($now) {
                $query->whereNull('scheduled_at')
                      ->orWhere('scheduled_at', '<=', $now);
            })
            ->whereHas('schedule', function ($query) {
                $query->where('status', 'active');
            })
            ->orderBy('scheduled_at')
            ->limit($batchSize)
            ->get();
    }

    /**
     * Read-only preview of slot dispatching.
     * Renders subject/body using in-memory content engine path.
     * Does NOT modify dispatch status, create history rows, or send emails.
     */
    public function previewSlot(WarmupDispatch $dispatch): array
    {
        $gateway = $dispatch->gateway;
        $gatewayStatus = $gateway?->status instanceof \BackedEnum ? $gateway->status->value : (string) $gateway?->status;
        $isGatewayActive = ($gateway && $gatewayStatus === 'active');

        $resource = $this->resolveContentResourceForPreview($dispatch->user_id);
        $content = $this->contentEngine->generateContent($resource, [
            'intent'      => 'warmup',
            'dispatch_id' => $dispatch->id,
        ]);

        $subject = $content['subject'] ?? 'Professional inquiry';
        $senderDomain = ($gateway && $gateway->address) ? (substr(strrchr($gateway->address, "@"), 1) ?: 'voltmail.io') : 'voltmail.io';
        $messageId = sprintf('<warmup-%s@%s>', $dispatch->uid, $senderDomain);

        return [
            'slot_id'             => $dispatch->id,
            'slot_number'         => $dispatch->slot_number,
            'recipient_email'     => $dispatch->recipient_email,
            'gateway_id'          => $dispatch->gateway_id,
            'gateway_name'        => $gateway?->name,
            'gateway_address'     => $gateway?->address,
            'gateway_active'      => $isGatewayActive,
            'subject'             => $subject,
            'message_id'          => $messageId,
            'template_id'         => $content['template_id'] ?? 'standard_intro',
            'content_resource_id' => $resource->id,
            'content_fingerprint' => $content['content_fingerprint'] ?? '',
            'scheduled_at'        => $dispatch->scheduled_at ? $dispatch->scheduled_at->toDateTimeString() : null,
            'status'              => $dispatch->status,
        ];
    }

    /**
     * Resolve active ContentResource for preview without persisting fallback rows.
     */
    protected function resolveContentResourceForPreview(int $userId): ContentResource
    {
        $resource = ContentResource::where('user_id', $userId)
            ->where('status', 'active')
            ->orderByDesc('is_default')
            ->first();

        if ($resource) {
            return $resource;
        }

        $mock = new ContentResource([
            'user_id'             => $userId,
            'name'                => 'Preview Business Profile',
            'company_name'        => 'Voltmail Business',
            'business_type'       => 'Cloud Communications',
            'company_description' => 'Reliable, enterprise email infrastructure and deliverability solutions.',
            'industry'            => 'Software & Communications',
            'target_audience'     => 'Modern businesses and delivery professionals',
            'key_products'        => [['name' => 'Cloud Gateway', 'benefit' => 'Reliable high-volume message delivery']],
            'status'              => 'active',
            'is_default'          => true,
        ]);
        $mock->id = 0;
        return $mock;
    }

    /**
     * Check if an error message reflects an ambiguous network timeout
     * where remote SMTP server may already have accepted the mail.
     */
    protected function isAmbiguousNetworkError(string $error): bool
    {
        $lower = strtolower($error);
        return str_contains($lower, 'timed out') ||
               str_contains($lower, 'timeout') ||
               str_contains($lower, 'connection reset') ||
               str_contains($lower, 'broken pipe') ||
               str_contains($lower, 'operation timed out');
    }
}
