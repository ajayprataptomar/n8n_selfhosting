<?php

namespace App\Models;

use App\Enums\System\SessionStatusEnum;
use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;

class AndroidSession extends Model
{
    use HasFactory, Notifiable, Filterable;

    protected $fillable = [
        'user_id', 'name', 'qr_code', 'token', 'status', 'meta_data', 'expires_at',
    ];

    protected $casts = [
        'status'        => SessionStatusEnum::class,
        'meta_data'     => 'array',
        'expires_at'    => 'datetime',
    ];

    public function scopeInitiated($query): Builder
    {
        return $query->where('status', SessionStatusEnum::INITIATED->value);
    }

    public function scopeConnected($query): Builder
    {
        return $query->where('status', SessionStatusEnum::CONNECTED->value);
    }

    public function scopeDisconnected($query): Builder
    {
        return $query->where('status', SessionStatusEnum::DISCONNECTED->value);
    }

    public function scopeExpired($query): Builder
    {
        return $query->where('status', SessionStatusEnum::EXPIRED->value);
    }

    /**
     * user
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * androidSims
     *
     * @return HasMany
     */
    public function androidSims(): HasMany
    {
        return $this->hasMany(AndroidSim::class);
    }
    
}