<?php

namespace App\Models;

use App\Enums\Common\Status;
use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\Notifiable;

class AndroidSim extends Model
{
    use HasFactory, Notifiable, Filterable;

    protected $fillable = [
        'user_id', 'android_session_id', 'sim_number', 'time_interval',
        'send_sms', 'status', 'meta_data', 'per_message_delay',
        'delay_after_count', 'delay_after_duration', 'reset_after_count',
    ];

    protected $casts = [
        'status'                => Status::class,
        'meta_data'             => 'array',
        'time_interval'         => 'integer',
        'send_sms'              => 'boolean',
        'per_message_delay'     => 'float',
        'delay_after_count'     => 'integer',
        'delay_after_duration'  => 'float',
        'reset_after_count'     => 'integer',
    ];

    public function scopeActive($query): Builder
    {
        return $query->where('status', Status::ACTIVE->value);
    }

    public function scopeInactive($query): Builder
    {
        return $query->where('status', Status::INACTIVE->value);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function androidSession(): BelongsTo
    {
        return $this->belongsTo(AndroidSession::class);
    }
}