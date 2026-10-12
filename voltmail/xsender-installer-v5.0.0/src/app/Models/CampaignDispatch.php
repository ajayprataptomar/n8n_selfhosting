<?php

namespace App\Models;

use App\Enums\Campaign\CampaignChannel;
use App\Enums\Campaign\DispatchStatus;
use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignDispatch extends Model
{
    use HasFactory, Filterable;

    protected $fillable = [
        'campaign_id',
        'campaign_message_id',
        'contact_id',
        'channel',
        'gateway_id',
        'android_sim_id',
        'dispatch_log_id',
        'status',
        'scheduled_at',
        'sent_at',
        'delivered_at',
        'error_message',
        'retry_count',
        'meta_data',
    ];

    protected $casts = [
        'channel' => CampaignChannel::class,
        'status' => DispatchStatus::class,
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'meta_data' => 'array',
        'retry_count' => 'integer',
    ];

    // ============ Relationships ============

    /**
     * Get the campaign
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(UnifiedCampaign::class, 'campaign_id');
    }

    /**
     * Get the campaign message
     */
    public function campaignMessage(): BelongsTo
    {
        return $this->belongsTo(CampaignMessage::class, 'campaign_message_id');
    }

    /**
     * Get the contact
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Get the gateway used
     */
    public function gateway(): BelongsTo
    {
        return $this->belongsTo(Gateway::class);
    }

    // ============ Scopes ============

    /**
     * Scope to pending dispatches
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', DispatchStatus::PENDING);
    }

    /**
     * Scope to queued dispatches
     */
    public function scopeQueued(Builder $query): Builder
    {
        return $query->where('status', DispatchStatus::QUEUED);
    }

    /**
     * Scope to processing dispatches
     */
    public function scopeProcessing(Builder $query): Builder
    {
        return $query->where('status', DispatchStatus::PROCESSING);
    }

    /**
     * Scope to sent dispatches
     */
    public function scopeSent(Builder $query): Builder
    {
        return $query->where('status', DispatchStatus::SENT);
    }

    /**
     * Scope to delivered dispatches
     */
    public function scopeDelivered(Builder $query): Builder
    {
        return $query->where('status', DispatchStatus::DELIVERED);
    }

    /**
     * Scope to failed dispatches
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', DispatchStatus::FAILED);
    }

    /**
     * Scope to successful dispatches
     */
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->whereIn('status', DispatchStatus::successStatuses());
    }

    /**
     * Scope to failed/bounced dispatches
     */
    public function scopeUnsuccessful(Builder $query): Builder
    {
        return $query->whereIn('status', DispatchStatus::failureStatuses());
    }

    /**
     * Scope to retryable dispatches
     */
    public function scopeRetryable(Builder $query, int $maxRetries = 3): Builder
    {
        return $query->whereIn('status', [DispatchStatus::FAILED, DispatchStatus::BOUNCED])
            ->where('retry_count', '<', $maxRetries);
    }

    /**
     * Scope to dispatches by channel
     */
    public function scopeForChannel(Builder $query, string $channel): Builder
    {
        return $query->where('channel', $channel);
    }

    /**
     * Scope to SMS dispatches
     */
    public function scopeSms(Builder $query): Builder
    {
        return $query->where('channel', CampaignChannel::SMS);
    }

    /**
     * Scope to Email dispatches
     */
    public function scopeEmail(Builder $query): Builder
    {
        return $query->where('channel', CampaignChannel::EMAIL);
    }

    /**
     * Scope to WhatsApp dispatches
     */
    public function scopeWhatsapp(Builder $query): Builder
    {
        return $query->where('channel', CampaignChannel::WHATSAPP);
    }

    /**
     * Scope to ready for processing
     */
    public function scopeReadyToProcess(Builder $query): Builder
    {
        return $query->where('status', DispatchStatus::PENDING)
            ->where(function ($q) {
                $q->whereNull('scheduled_at')
                    ->orWhere('scheduled_at', '<=', now());
            });
    }

    // ============ Accessors & Helpers ============

    /**
     * Check if dispatch is successful
     */
    public function isSuccessful(): bool
    {
        return $this->status->isSuccess();
    }

    /**
     * Check if dispatch failed
     */
    public function isFailed(): bool
    {
        return $this->status->isFailure();
    }

    /**
     * Check if dispatch is in progress
     */
    public function isInProgress(): bool
    {
        return $this->status->isInProgress();
    }

    /**
     * Check if dispatch can be retried
     */
    public function canRetry(int $maxRetries = 3): bool
    {
        return $this->status->canRetry() && $this->retry_count < $maxRetries;
    }

    /**
     * Mark as queued
     */
    public function markAsQueued(): void
    {
        $this->update(['status' => DispatchStatus::QUEUED]);
    }

    /**
     * Mark as processing
     */
    public function markAsProcessing(): void
    {
        $this->update(['status' => DispatchStatus::PROCESSING]);
    }

    /**
     * Mark as sent
     */
    public function markAsSent(): void
    {
        $this->update([
            'status' => DispatchStatus::SENT,
            'sent_at' => now(),
        ]);
    }

    /**
     * Mark as delivered
     */
    public function markAsDelivered(): void
    {
        $this->update([
            'status' => DispatchStatus::DELIVERED,
            'delivered_at' => now(),
        ]);
    }

    /**
     * Mark as failed
     */
    public function markAsFailed(string $errorMessage = null): void
    {
        $this->update([
            'status' => DispatchStatus::FAILED,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Mark as bounced
     */
    public function markAsBounced(string $errorMessage = null): void
    {
        $this->update([
            'status' => DispatchStatus::BOUNCED,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Mark as opened
     */
    public function markAsOpened(): void
    {
        // Email never receives an SMTP delivery receipt, so it stays at SENT after dispatch.
        // A tracking-pixel hit proves both delivery and open, so allow SENT → OPENED.
        if (in_array($this->status, [DispatchStatus::SENT, DispatchStatus::DELIVERED])) {
            $this->update(['status' => DispatchStatus::OPENED]);
        }
    }

    /**
     * Mark as clicked
     */
    public function markAsClicked(): void
    {
        // A click proves delivery + open, so upgrade from sent, delivered, or opened.
        if (in_array($this->status, [DispatchStatus::SENT, DispatchStatus::DELIVERED, DispatchStatus::OPENED])) {
            $this->update(['status' => DispatchStatus::CLICKED]);
        }
    }

    /**
     * Mark as replied
     */
    public function markAsReplied(): void
    {
        $this->update(['status' => DispatchStatus::REPLIED]);
    }

    /**
     * Increment retry count
     */
    public function incrementRetry(): void
    {
        $this->increment('retry_count');
        $this->update(['status' => DispatchStatus::PENDING]);
    }

    /**
     * Get contact address for this channel
     */
    public function getContactAddress(): ?string
    {
        if (!$this->contact) {
            return null;
        }

        return match ($this->channel) {
            CampaignChannel::SMS => $this->contact->sms_contact,
            CampaignChannel::EMAIL => $this->contact->email_contact,
            CampaignChannel::WHATSAPP => $this->contact->whatsapp_contact,
            default => null,
        };
    }

    /**
     * Add metadata
     */
    public function addMetadata(string $key, mixed $value): void
    {
        $metaData = $this->meta_data ?? [];
        $metaData[$key] = $value;
        $this->update(['meta_data' => $metaData]);
    }

    /**
     * Get metadata value
     */
    public function getMetadata(string $key, mixed $default = null): mixed
    {
        return $this->meta_data[$key] ?? $default;
    }
}
