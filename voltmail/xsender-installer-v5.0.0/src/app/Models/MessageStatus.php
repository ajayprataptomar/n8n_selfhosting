<?php

namespace App\Models;

use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageStatus extends Model
{
    use HasFactory, Filterable;

    protected $fillable = [
        'message_id', 'status', 'whatsapp_message_id', 'status_timestamp', 'additional_data'
    ];

    protected $casts = [
        'additional_data'   => 'array',
        'status_timestamp'  => 'datetime',
    ];

    /**
     * message
     *
     * @return BelongsTo
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
