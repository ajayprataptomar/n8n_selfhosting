<?php

namespace App\Models;

use App\Enums\System\ChannelTypeEnum;
use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Notifications\Notifiable;

class Message extends Model
{
    use HasFactory, Notifiable, Filterable;

    protected $fillable = [
        'user_id', 'type', 'subject', 'main_body', 'message', 'meta_data', 'is_campaign', 'file_info', 'template_id',
        'conversation_id'
    ];

    protected $casts = [
        'type'          => ChannelTypeEnum::class,
        'meta_data'     => 'array',
        'file_info'     => 'array',
        'is_campaign'   => 'boolean',
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
     * campaigns
     *
     * @return HasMany
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    /**
     * dispatchLogs
     *
     * @return HasMany
     */
    public function dispatchLogs(): HasMany
    {
        return $this->hasMany(DispatchLog::class);
    }

    /**
     * template
     *
     * @return BelongsTo
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    /**
     * conversation
     *
     * @return BelongsTo
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * statuses
     *
     * @return HasMany
     */
    public function statuses(): HasMany
    {
        return $this->hasMany(MessageStatus::class);
    }

    /**
     * Summary of participants
     * @return HasMany
     */
    public function participants(): HasMany
    {
        return $this->hasMany(MessageParticipant::class);
    }

    /**
     * Summary of files
     * @return MorphMany
     */
    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'fileable');
    }
}