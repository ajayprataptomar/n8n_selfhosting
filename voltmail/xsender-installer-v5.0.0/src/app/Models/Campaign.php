<?php

namespace App\Models;

use App\Enums\ServiceType;
use App\Enums\System\CampaignStatusEnum;
use App\Enums\System\ChannelTypeEnum;
use App\Enums\System\CommunicationStatusEnum;
use App\Enums\System\PriorityEnum;
use App\Enums\System\RepeatTimeEnum;
use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;

class Campaign extends Model
{
    use HasFactory, Notifiable, Filterable;

    protected $fillable = [
        'user_id', 'message_id', 'group_id', 'type', 'name', "repeat_format",
        'priority', 'repeat_time', 'status', 'schedule_at', 'meta_data',
    ];

    protected $casts = [
        'type' => ChannelTypeEnum::class,
        'priority' => PriorityEnum::class,
        'repeat_format' => RepeatTimeEnum::class,
        'status' => CampaignStatusEnum::class,
        'schedule_at' => 'datetime',
        'meta_data' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ContactGroup::class, 'group_id');
    }

    public function dispatchLogs(): HasMany
    {
        return $this->hasMany(DispatchLog::class);
    }














    public function scopeSms($query) {
        
        return $query->where('type', ServiceType::SMS->value);
    }
    public function scopeWhatsapp($query) {

        return $query->where('type', ServiceType::WHATSAPP->value);
    }
    public function scopeEmail($query) {

        return $query->where('type', ServiceType::EMAIL->value);
    }

    public function scopeRoutefilter(Builder $q) :Builder {

        return $q->when(request()->routeIs('*.communication.sms.campaign.index'),function($query) {
            
            return $query->sms();
        })->when(request()->routeIs('*.communication.whatsapp.campaign.index'),function($query) {
            
            return $query->whatsapp();
        })->when(request()->routeIs('*.communication.email.campaign.index'),function($query) {
            
            return $query->email();
        });
    }

    public function communicationLog() {

        return $this->hasMany(CommunicationLog::class, 'campaign_id', 'id');
    }

    /**
     * dispatchPendingLog
     *
     * @return HasMany
     */
    public function dispatchPendingLog(): HasMany {

        return $this->hasMany(DispatchLog::class, 'campaign_id', 'id')->where('status', CommunicationStatusEnum::PENDING->value);
    }

    public function getRelationships() {

        return ['dispatchPendingLog'];
    }
    // public function user() {

    //     return $this->belongsTo(User::class, 'user_id');
    // }

    public function setUpdatedAt($value)
    {
        if ($this->exists) {
            $this->updated_at = $value;
        }
    }

    public function unsubscribes()
    {
        return $this->hasMany(CampaignUnsubscribe::class);
    }

    /**
     *
     * @param  string  $contactUid
     * @param  int     $channel
     * @return bool
     */
    public function hasContactUnsubscribed($contactUid, $channel)
    {
        return $this->unsubscribes()
                    ->where('contact_uid', $contactUid)
                    ->where('channel', $channel)
                    ->exists();
    }
}