<?php

namespace App\Models;

use App\Enums\Common\Status;
use App\Enums\StatusEnum;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, Filterable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'gateway_credentials'   => 'object',
        'address'               => 'object',  
        'is_erasing'            => 'boolean',
        'total_entries'         => 'integer',
        'total_deleted_entries' => 'integer',
    ];

    protected static function booted()
    {
        static::creating(function ($contact) {
            $contact->uid = str_unique();
        });
    }
    public function scopeVerified($query)
    {
        return $query->where('email_verified_status', StatusEnum::TRUE->status());
    }
    public function scopeUnverified($query)
    {
        return $query->where('email_verified_status', StatusEnum::FALSE->status());
    }

     /**
     * scopeActive
     *
     * @return Builder
     */
    public function scopeActive(): Builder|User {
        return $this->where(function(Builder $q): Builder {
            return $q->where('status', StatusEnum::TRUE->status())
                        ->orWhere("status", Status::ACTIVE->value);
        });
    }

    public function scopeBanned($query)
    {
        return $query->where('status', StatusEnum::FALSE->status());
    }

    /**
     * androidSession
     *
     * @return HasMany
     */
    public function androidSession(): HasMany
    {
        return $this->hasMany(AndroidSession::class, 'user_id');
    }

    /**
     * androidSims
     *
     * @return HasMany
     */
    public function androidSims(): HasMany
    {
        return $this->hasMany(AndroidSim::class, 'user_id');
    }

    /**
     * campaigns
     *
     * @return HasMany
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class, 'user_id');
    }

    /**
     * campaignUnsubscribers
     *
     * @return HasMany
     */
    public function campaignUnsubscribers(): HasMany
    {
        return $this->hasMany(CampaignUnsubscribe::class, 'user_id');
    }

    /**
     * contacts
     *
     * @return HasMany
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'user_id');
    }

    /**
     * contactGroups
     *
     * @return HasMany
     */
    public function contactGroups(): HasMany
    {
        return $this->hasMany(ContactGroup::class, 'user_id');
    }

    /**
     * creditLogs
     *
     * @return HasMany
     */
    public function creditLogs(): HasMany
    {
        return $this->hasMany(CreditLog::class, 'user_id');
    }

    /**
     * dispatchDelays
     *
     * @return HasMany
     */
    public function dispatchDelays(): HasMany
    {
        return $this->hasMany(DispatchDelay::class, 'user_id');
    }

    /**
     * dispatchLogs
     *
     * @return HasMany
     */
    public function dispatchLogs(): HasMany
    {
        return $this->hasMany(DispatchLog::class, 'user_id');
    }

    /**
     * gateways
     *
     * @return HasMany
     */
    public function gateways(): HasMany
    {
        return $this->hasMany(Gateway::class, 'user_id');
    }

    /**
     * imports
     *
     * @return HasMany
     */
    public function imports(): HasMany
    {
        return $this->hasMany(Import::class, 'user_id');
    }

    /**
     * messages
     *
     * @return HasMany
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Import::class, 'user_id');
    }

    /**
     * paymentLogs
     *
     * @return HasMany
     */
    public function paymentLogs(): HasMany
    {
        return $this->hasMany(PaymentLog::class, 'user_id');
    }

    /**
     * webhookLogs
     *
     * @return HasMany
     */
    public function webhookLogs(): HasMany
    {
        return $this->hasMany(PostWebhookLog::class, 'user_id');
    }

    /**
     * subscriptions
     *
     * @return HasMany
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Import::class, 'user_id');
    }

    /**
     * supportTickets
     *
     * @return HasMany
     */
    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class, 'user_id');
    }

    /**
     * templates
     *
     * @return HasMany
     */
    public function templates(): HasMany
    {
        return $this->hasMany(Template::class, 'user_id')->latest();
    }

    /**
     * transactions
     *
     * @return HasMany
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Template::class, 'user_id')->latest();
    }






    public function ticket()
    {
        return $this->hasMany(SupportTicket::class, 'user_id');
    }

    public function emailGroup()
    {
        return $this->hasMany(ContactGroup::class, 'user_id');
    }

    

    public function emailContact()
    {
        return $this->hasMany(Contact::class, 'user_id');
    }

    /**
     * Get referral user
     *
     * @return BelongsTo
     */
    public function referral():BelongsTo {
        return  $this->belongsTo(User::class,"referral_id",'id');
    }



    /**
     * Get all affiliate users
     *
     * @return HasMany
     */
    public function affiliateUsers():HasMany {
        return  $this->hasMany(User::class,"referral_id",'id');
    }

    /**
     * Summary of affiliateLogs
     * @return HasMany
     */
    public function affiliateLogs():HasMany {
        return  $this->hasMany(AffiliateLog::class,"user_id",'id');
    }


    

    

    public function runningSubscription() {

        return $this->hasMany(Subscription::class, 'user_id')->where('status', Subscription::RUNNING)->orWhere('status', Subscription::RENEWED)->first();
    }

    public function scopeRoutefilter(Builder $q) :Builder{

        return $q->when(request()->routeIs('admin.user.banned'),function($query) {

            return $query->banned();
        })->when(request()->routeIs('admin.user.active'),function($query) {
            
            return $query->active();
        });
    }
}
