<?php

namespace App\Models;

use App\Enums\System\ChannelTypeEnum;
use App\Enums\System\CommunicationStatusEnum;
use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;

class DispatchUnit extends Model
{
    use HasFactory, Notifiable, Filterable;

    protected $fillable = [
        'gateway_id', 'message_id', 'type', 'log_count', 'status', 'response_message',
    ];

    protected $casts = [
        'type'          => ChannelTypeEnum::class,
        'status'        => CommunicationStatusEnum::class,
    ];

    /**
     * gateway
     *
     * @return BelongsTo
     */
    public function gateway(): BelongsTo
    {
        return $this->belongsTo(Gateway::class);
    }

    /**
     * message
     *
     * @return BelongsTo
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * dispatchLogs
     *
     * @return HasMany
     */
    public function dispatchLogs(): HasMany
    {
        return $this->hasMany(DispatchLog::class);
    }
}