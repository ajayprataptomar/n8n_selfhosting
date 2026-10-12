<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WarmupScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'gateway_id'         => ['required', 'integer'],
            'daily_limit'        => ['required', 'integer', 'min:5', 'max:500'],
            'rampup_increment'   => ['required', 'integer', 'min:1', 'max:50'],
            'starting_quota'     => ['nullable', 'integer', 'min:1', 'max:50'],
            'reply_rate_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'start_date'         => ['nullable', 'date'],
            // IMAP settings (optional if already configured on gateway)
            'imap_host'          => ['nullable', 'string', 'max:255'],
            'imap_port'          => ['nullable', 'integer', 'min:1', 'max:65535'],
            'imap_encryption'    => ['nullable', 'string', 'in:ssl,tls,none,starttls'],
            'imap_username'      => ['nullable', 'string', 'max:255'],
            'imap_password'      => ['nullable', 'string', 'max:255'],
        ];
    }
}
