<?php

namespace App\Models;

use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailSuppression extends Model
{
    use Filterable;

    public $timestamps = false;

    protected $fillable = [
        'uid',
        'user_id',
        'email_address',
        'reason',
        'source',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->uid)) {
                $model->uid = str_unique();
            }
            if (empty($model->created_at)) {
                $model->created_at = now();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if an email address is suppressed.
     */
    public static function isSuppressed(string $email, ?int $userId = null): bool
    {
        return static::where('email_address', strtolower($email))
            ->where(function ($query) use ($userId) {
                $query->whereNull('user_id');
                if ($userId) {
                    $query->orWhere('user_id', $userId);
                }
            })
            ->exists();
    }

    /**
     * Add an email to the suppression list if not already present.
     */
    public static function suppress(string $email, string $reason, string $source = 'system', ?int $userId = null): ?self
    {
        $email = strtolower($email);

        return static::firstOrCreate(
            [
                'email_address' => $email,
                'user_id' => $userId,
            ],
            [
                'reason' => $reason,
                'source' => $source,
            ]
        );
    }
}
