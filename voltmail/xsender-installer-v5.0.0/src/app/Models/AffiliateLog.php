<?php

namespace App\Models;

use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateLog extends Model
{
    use HasFactory, Filterable;

    protected $fillable = [
        'user_id',
        'referred_to',
        'subscription_id',
        'commission_amount',
        'commission_rate',
        'trx_code',
        'note',
    ];

    /**
     * Summary of user
     * @return BelongsTo
     */
    public function user(): BelongsTo {

    	return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Summary of affiliate
     * @return BelongsTo
     */
    public function affiliate(): BelongsTo {
        return $this->belongsTo(User::class, 'referred_to');
    }

    /**
     * Summary of subscription
     * @return BelongsTo
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id');
    }   
}
