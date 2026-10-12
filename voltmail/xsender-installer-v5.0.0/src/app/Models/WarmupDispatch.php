<?php

namespace App\Models;

use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarmupDispatch extends Model
{
    use HasFactory, Filterable;

    protected $table = 'warmup_dispatches';

    const STATUS_PENDING   = 'pending';
    const STATUS_CLAIMED   = 'claimed';
    const STATUS_SENDING   = 'sending';
    const STATUS_SENT      = 'sent';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_AMBIGUOUS = 'ambiguous';
    const STATUS_FAILED    = 'failed';

    const PLACEMENT_INBOX   = 'inbox';
    const PLACEMENT_SPAM    = 'spam';
    const PLACEMENT_UNKNOWN = 'unknown';

    protected $fillable = [
        'uid',
        'warmup_schedule_id',
        'user_id',
        'gateway_id',
        'content_resource_id',
        'recipient_email',
        'subject',
        'message_id',
        'dispatch_date',
        'slot_number',
        'scheduled_at',
        'claimed_at',
        'claimed_by',
        'status',
        'response_code',
        'response_text',
        'error_message',
        'sent_at',
        'verified_at',
        'inbox_placement',
        'meta_data',
    ];

    protected $casts = [
        'slot_number'   => 'integer',
        'dispatch_date' => 'date',
        'scheduled_at'  => 'datetime',
        'claimed_at'    => 'datetime',
        'sent_at'       => 'datetime',
        'verified_at'   => 'datetime',
        'meta_data'     => 'array',
    ];

    protected static function booted()
    {
        static::creating(function ($dispatch) {
            if (empty($dispatch->uid)) {
                $dispatch->uid = str_unique();
            }
        });
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(WarmupSchedule::class, 'warmup_schedule_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(Gateway::class);
    }

    public function contentResource(): BelongsTo
    {
        return $this->belongsTo(ContentResource::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeSent(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SENT);
    }
}
