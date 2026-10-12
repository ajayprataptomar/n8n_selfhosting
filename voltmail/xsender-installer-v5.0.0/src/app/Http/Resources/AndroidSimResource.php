<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AndroidSimResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                    => $this->id,
            'user_id'               => $this->user_id,
            'android_session_id'    => $this->android_session_id,
            'sim_number'            => $this->sim_number,
            'time_interval'         => $this->time_interval,
            'send_sms'              => $this->send_sms,
            'status'                => $this->status,
            'per_message_delay'     => $this->per_message_delay,
            'delay_after_count'     => $this->delay_after_count,
            'delay_after_duration'  => $this->delay_after_duration,
            'reset_after_count'     => $this->reset_after_count,
            'created_at'            => $this->created_at->toDateTimeString(),
            'updated_at'            => $this->updated_at->toDateTimeString(),
        ];
    }
}