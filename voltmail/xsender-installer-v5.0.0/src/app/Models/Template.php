<?php

namespace App\Models;

use App\Enums\Common\Status;
use App\Enums\System\ChannelTypeEnum;
use App\Enums\System\TemplateApprovalStatusEnum;
use App\Enums\System\TemplateProviderEnum;
use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Template extends Model
{
    use HasFactory, Notifiable, Filterable;

    protected $guarded = [];

    protected $casts = [

        "template_data"     => "array",
        'status'            => Status::class,
        'approval_status'   => TemplateApprovalStatusEnum::class,
        'channel'           => ChannelTypeEnum::class,
        'provider'          => TemplateProviderEnum::class,
    ];

    protected static function booted() {
        static::creating(function ($template) {
            $template->uid = str_unique();
        });
    }

    
    
    public function user() {

    	return $this->belongsTo(User::class, 'user_id');
    }

    public function cloudApi() {
        
    	return $this->belongsTo(Gateway::class, 'cloud_id');
    }

    public function scopeActive($query): Builder
    {
        return $query->where('status', Status::ACTIVE->value);
    }
    public function scopeInactive($query): Builder
    {
        return $query->where('status', Status::INACTIVE->value);
    }
    public function scopeSms($query)
    {
        return $query->where('channel', ChannelTypeEnum::SMS->value);
    }
    public function scopeWhatsapp($query)
    {
        return $query->where('channel', ChannelTypeEnum::WHATSAPP->value);
    }
    public function scopeEmail($query)
    {
        return $query->where('channel', ChannelTypeEnum::EMAIL->value);
    }

    public function scopeRoutefilter(Builder $q) :Builder{

        return $q->when(request()->routeIs('*.template.sms'),function($query) {

            return $query->sms();
        })->when(request()->routeIs('*.template.whatsapp.index'),function($query) {
            
            return $query->whatsapp();
        })->when(request()->routeIs('*.template.email'),function($query) {
            
            return $query->email();
        });
    }
}
