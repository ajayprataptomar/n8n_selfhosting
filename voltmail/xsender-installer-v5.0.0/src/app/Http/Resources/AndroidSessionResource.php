<?php

namespace App\Http\Resources;

use App\Http\Resources\AndroidSimCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AndroidSessionResource extends JsonResource
{
    public function toArray($request): array
    {
        $data = [
            'id'            => $this->id,
            'user_id'       => $this->user_id,
            'name'          => $this->name,
            'qr_code'       => $this->qr_code,
            'status'        => $this->status->value,
            'expires_at'    => $this->expires_at ? $this->expires_at->toDateTimeString() : null,
            'created_at'    => $this->created_at->toDateTimeString(),
            'updated_at'    => $this->updated_at->toDateTimeString(),
        ];

        if ($this->relationLoaded('androidSims') && $this->androidSims) {
            $data['android_sims'] = new AndroidSimCollection($this->androidSims);
        }

        if (isset($this->android_sims_count)) {
            $data['android_sims_count'] = $this->android_sims_count;
        }

        return $data;
    }
}