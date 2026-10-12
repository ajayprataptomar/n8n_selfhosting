<?php

namespace App\Models;

use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MessageParticipant extends Model
{
    use HasFactory, Filterable;

    protected $fillable = [
        'message_id',
        'participantable_id',
        'participantable_type',
        'role',
    ];

    /**
     * Summary of message
     * @return BelongsTo
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * Summary of participantable
     * @return MorphTo
     */
    public function participantable(): MorphTo
    {
        return $this->morphTo();
    }
}
