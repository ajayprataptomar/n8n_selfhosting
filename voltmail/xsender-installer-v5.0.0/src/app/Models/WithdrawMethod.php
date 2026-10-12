<?php

namespace App\Models;

use App\Traits\Filterable;
use App\Enums\Common\Status;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WithdrawMethod extends Model
{
    use HasFactory, Notifiable, Filterable;
    protected $fillable = [
        'uid',
        'currency_code',
        'name',
        'duration',
        'minimum_amount',
        'maximum_amount',
        'fixed_charge',
        'percent_charge',
        'note',
        'parameters',
        'status',
        'image'
    ];
    protected $casts = [
        "duration"   => "array",
        "parameters" => "array",
    ];

    protected static function booted()
    {
        static::creating(function ($contact) {
            
            $contact->uid    = str_unique();
            $contact->status = Status::ACTIVE->value;
        });
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
}
