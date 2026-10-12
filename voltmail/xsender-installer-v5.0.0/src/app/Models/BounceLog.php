<?php

namespace App\Models;

use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BounceLog extends Model
{
    use Filterable;

    protected $fillable = [
        'uid',
        'user_id',
        'dispatch_log_id',
        'email_address',
        'bounce_type',
        'bounce_code',
        'bounce_message',
        'provider',
        'processed',
        'processed_at',
    ];

    protected $casts = [
        'processed' => 'boolean',
        'processed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->uid)) {
                $model->uid = str_unique();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dispatchLog(): BelongsTo
    {
        return $this->belongsTo(DispatchLog::class);
    }

    public function scopeHardBounces($query)
    {
        return $query->where('bounce_type', 'hard');
    }

    public function scopeSoftBounces($query)
    {
        return $query->where('bounce_type', 'soft');
    }

    public function scopeComplaints($query)
    {
        return $query->where('bounce_type', 'complaint');
    }

    public function scopeUnprocessed($query)
    {
        return $query->where('processed', false);
    }
}
