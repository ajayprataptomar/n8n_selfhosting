<?php

namespace App\Models;

use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentGenerationHistory extends Model
{
    use HasFactory, Filterable;

    protected $table = 'content_generation_histories';

    protected $fillable = [
        'uid',
        'user_id',
        'content_resource_id',
        'gateway_id',
        'template_id',
        'template_version',
        'subject',
        'body',
        'content_fingerprint',
        'variables_used',
        'reuse_count',
        'last_used_at',
        'dispatch_reference',
    ];

    protected $casts = [
        'variables_used' => 'array',
        'reuse_count'    => 'integer',
        'last_used_at'   => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->uid)) {
                $model->uid = str_unique(30);
            }
        });
    }

    /**
     * Relationship: Owner tenant
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relationship: Associated business content resource
     */
    public function contentResource(): BelongsTo
    {
        return $this->belongsTo(ContentResource::class, 'content_resource_id');
    }

    /**
     * Relationship: Gateway through which email was sent
     */
    public function gateway(): BelongsTo
    {
        return $this->belongsTo(Gateway::class, 'gateway_id');
    }

    /**
     * Scope: Filter by tenant
     */
    public function scopeUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: Find by fingerprint for deduplication
     */
    public function scopeFingerprint(Builder $query, string $hash): Builder
    {
        return $query->where('content_fingerprint', $hash);
    }
}
