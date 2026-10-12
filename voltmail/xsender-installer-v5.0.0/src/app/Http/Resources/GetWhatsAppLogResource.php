<?php

namespace App\Http\Resources;

use App\Enums\System\ChannelTypeEnum;
use Illuminate\Http\Resources\Json\JsonResource;

class GetWhatsAppLogResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'created_at' => $this->created_at->toDateTimeString(),
            'status' => $this->status,
            'message' => [
                'message'   => @$this?->message?->message,
                'file_info' => @$this?->message?->file_info,
            ],
            'contact' => [
                'first_name'    => @$this?->contact?->first_name,
                'last_name'     => @$this?->contact?->last_name,
                'email_contact' => @$this?->contact?->whatsapp_contact,
                'meta_data'     => @$this?->contact?->meta_data,
            ],
        ];
    }
}