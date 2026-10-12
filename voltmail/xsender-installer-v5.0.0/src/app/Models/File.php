<?php

namespace App\Models;

use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Notifications\Notifiable;

class File extends Model
{
    use HasFactory, Notifiable, Filterable;

    protected $fillable = [
        'user_id', 'fileable_id', 'fileable_type', 'path', 'name',
        'mime_type', 'size', 'meta_data',
    ];

    protected $casts = [
        'meta_data' => 'array',
        'size' => 'integer',
    ];

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
     * fileable
     *
     * @return MorphTo
     */
    public function fileable(): MorphTo
    {
        return $this->morphTo();
    }
}