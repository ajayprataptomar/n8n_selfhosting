<?php

namespace App\Models;

use App\Enums\WithdrawLogEnum;
use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\Notifiable;

class WithdrawLog extends Model
{
    use HasFactory, Notifiable, Filterable;

    protected $fillable = [
        'method_id',
        'user_id',
        'currency_code',
        'trx_code',
        'amount',
        'charge',
        'final_amount',
        'custom_data',
        'status',
        'notes',
    ];

    protected $casts = [ "custom_data"   => "array" ];

    /**
     * Summary of user
     * @return BelongsTo
     */
    public function user(): BelongsTo {

    	return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Summary of method
     * @return BelongsTo
     */
    public function method(): BelongsTo {

    	return $this->belongsTo(WithdrawMethod::class, 'method_id', 'id');
    }

    /**
     * Summary of scopePending
     * @param mixed $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePending($query): Builder
    {
        return $query->where('status', WithdrawLogEnum::PENDING->value);
    }

    /**
     * Summary of scopeApproved
     * @param mixed $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeApproved($query): Builder
    {
        return $query->where('status', WithdrawLogEnum::APPROVED->value);
    }

    /**
     * Summary of scopeRejected
     * @param mixed $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRejected($query): Builder
    {
        return $query->where('status', WithdrawLogEnum::REJECTED->value);
    }
}
