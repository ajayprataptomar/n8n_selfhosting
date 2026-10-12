<?php

namespace App\Http\Resources;

use App\Enums\System\ChannelTypeEnum;
use Illuminate\Http\Resources\Json\JsonResource;

class GetSmsLogResource extends JsonResource
{
    public function toArray($request)
    {
        
        return [
            'id' => $this->id,
            'created_at' => $this->created_at->toDateTimeString(),
            'status' => $this->status,
            'message' => [
                'message' => $this->message->message,
            ],
            'contact' => [
                'first_name' => $this->contact->first_name,
                'last_name' => $this->contact->last_name,
                'email_contact' => $this->contact->sms_contact,
                'meta_data' => $this->contact->meta_data,
            ],
        ];
    }
}