<?php

namespace App\Models;

use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PostWebhookLog extends Model
{
    use HasFactory, Filterable;

    protected $guarded = [];

    protected static function booted()
    {
        static::creating(function ($log) {
            $log->uid = str_unique();
        });
    }
}
