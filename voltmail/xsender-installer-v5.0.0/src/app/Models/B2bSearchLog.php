<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class B2bSearchLog extends Model
{
    protected $fillable = [
        'uid', 'user_id', 'provider_id', 'provider_type',
        'search_filters', 'total_results', 'imported_count',
        'credits_used', 'imported_to_group_id', 'status', 'error_message',
    ];

    protected $casts = [
        'search_filters' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            $model->uid = $model->uid ?: str_unique();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(B2bProvider::class, 'provider_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ContactGroup::class, 'imported_to_group_id');
    }
}
