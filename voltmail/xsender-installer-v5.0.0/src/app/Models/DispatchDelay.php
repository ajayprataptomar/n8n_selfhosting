<?php

namespace App\Models;

use App\Enums\System\ChannelTypeEnum;
use Illuminate\Database\Eloquent\Model;

class DispatchDelay extends Model
{
     protected $table = 'dispatch_delays';

     protected $fillable = [
          'user_id',
          'gateway_id',
          'channel',
          'dispatch_id',
          'dispatch_type',
          'delay_value',
          'applies_from',
     ];

     protected $casts = [
        'channel' => ChannelTypeEnum::class,
    ];
}
