<?php

namespace App\Models;

use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Conversation extends Model
{
    use HasFactory, Filterable;

    protected $fillable = [
        'user_id', 'gateway_id', 'contact_id', 'channel', 'status', 'last_message_at', 'unread_count', 'meta_data'
    ];

    protected $casts = [
        'meta_data'   => 'array',
        'last_message_at'  => 'datetime',
    ];

    /**
     * Summary of user
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Summary of contact
     * @return BelongsTo
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Summary of gateway
     * @return BelongsTo
     */
    public function gateway(): BelongsTo
    {
        return $this->belongsTo(Gateway::class);
    }

    /**
     * Summary of messages
     * @return HasMany
     */
    public function messages(): HasMany 
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latest();
    }

    // Basic scopes
    public function scopeWhatsapp($query)
    {
        return $query->where('channel', 'whatsapp');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForAdmin($query)
    {
        return $query->whereNull('user_id');
    }

    public function scopeOrderByActivity($query)
    {
        return $query->orderByDesc('last_message_at')
                    ->orderByDesc('updated_at');
    }
}
