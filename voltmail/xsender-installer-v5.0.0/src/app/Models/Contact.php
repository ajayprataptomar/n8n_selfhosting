<?php

namespace App\Models;

use App\Traits\Filterable;
use App\Enums\Common\Status;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Builder;
use App\Enums\System\EmailVerificationStatusEnum;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Contact extends Model
{
    use HasFactory, Notifiable, Filterable;

    protected $fillable = [
        'uid',
        'user_id',
        'group_id',
        'meta_data',
        'whatsapp_contact',
        'email_contact',
        'sms_contact',
        'last_name',
        'first_name',
        'status',
        'email_verification'
    ];

    protected $casts = [
        "meta_data"             => "object",
        "email_verification"    => EmailVerificationStatusEnum::class,
    ];

    protected static function booted()
    {
        static::creating(function ($contact) {

            $contact->uid    = str_unique();
            $contact->status = Status::ACTIVE->value;
        });

        static::created(function ($contact) {
            // Fire automation new_contact trigger (non-blocking, queued)
            try {
                app(\App\Services\Automation\TriggerHandlerService::class)
                    ->handleNewContact($contact);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Automation new_contact trigger failed', [
                    'contact_id' => $contact->id,
                    'error' => $e->getMessage()
                ]);
            }
        });
    }

    /**
     * group
     *
     * @return BelongsTo
     */
    public function group(): BelongsTo
    {
    	return $this->belongsTo(ContactGroup::class, 'group_id', 'id');
    }

    /**
     * dispatchLog
     *
     * @return HasOne
     */
    public function dispatchLog(): HasOne
    {
        return $this->hasOne(DispatchLog::class, 'contact_id', 'id');
    }

    /**
     * scopeAdmin
     *
     * @return Builder
     */
    public function scopeAdmin(): Builder
    {
        return $this->whereNull('user_id');
    }

    /**
     * user
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * scopeActive
     *
     * @param mixed $query
     * 
     * @return Builder
     */
    public function scopeActive($query): Builder
    {
        return $query->where('status', Status::ACTIVE->value);
    }

    /**
     * scopeInactive
     *
     * @param mixed $query
     * 
     * @return Builder
     */
    public function scopeInactive($query): Builder 
    {
        return $query->where('status', Status::INACTIVE->value);
    }
    
    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * unsubscribes
     *
     * @return HasMany
     */
    public function unsubscribes(): HasMany
    {
        return $this->hasMany(CampaignUnsubscribe::class, 'contact_uid', 'uid');
    }

    /**
     * @param  int  $campaignId
     * @param  int  $channel
     * @return bool
     */
    public function hasUnsubscribedFrom($campaignId, $channel): bool
    {
        return $this->unsubscribes()
                    ->where('campaign_id', $campaignId)
                    ->where('channel', $channel)
                    ->exists();
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function messageParticipants(): MorphMany
    {
        return $this->morphMany(MessageParticipant::class, 'participantable');
    }

    public function scopeWhatsappContacts($query)
    {
        return $query->whereNotNull('whatsapp_contact');
    }

    /**
     * Get a meta data value by key
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getMetaValue(string $key, $default = null)
    {
        $metaData = $this->meta_data;

        // Handle if meta_data is a string (JSON)
        if (is_string($metaData)) {
            $metaData = json_decode($metaData, true);
        }

        // Handle if meta_data is an object
        if (is_object($metaData)) {
            $metaData = (array) $metaData;
        }

        if (!is_array($metaData) || !isset($metaData[$key])) {
            return $default;
        }

        $value = $metaData[$key];

        // Handle nested format: { "date_of_birth": { "value": "1990-01-14", "type": "1" } }
        if (is_array($value) && isset($value['value'])) {
            return $value['value'];
        }

        // Handle object format
        if (is_object($value) && isset($value->value)) {
            return $value->value;
        }

        // Handle simple format: { "date_of_birth": "1990-01-14" }
        return $value;
    }

    /**
     * Get contact's date of birth
     *
     * @return \Carbon\Carbon|null
     */
    public function getDateOfBirth(): ?\Carbon\Carbon
    {
        $dob = $this->getMetaValue('date_of_birth');

        if (empty($dob)) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($dob);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Check if today is contact's birthday
     *
     * @param int $daysBefore Check X days before actual birthday
     * @return bool
     */
    public function isBirthdayToday(int $daysBefore = 0): bool
    {
        $dob = $this->getDateOfBirth();

        if (!$dob) {
            return false;
        }

        $targetDate = now()->addDays($daysBefore);

        return $dob->month === $targetDate->month
            && $dob->day === $targetDate->day;
    }
}
