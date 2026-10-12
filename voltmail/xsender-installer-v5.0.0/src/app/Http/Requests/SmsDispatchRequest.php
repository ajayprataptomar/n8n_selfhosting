<?php

namespace App\Http\Requests;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Enums\System\ChannelTypeEnum;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Foundation\Http\FormRequest;
use App\Enums\System\Gateway\SmsGatewayTypeEnum;

class SmsDispatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contacts' => ['required', function ($attribute, $value, $fail) {
                if (is_string($value)) {
                    if (!preg_match('/^\d+$/', $value)) {
                        $fail('The contact must be a valid phone number.');
                    }
                } elseif (is_array($value)) {
                    if (empty($value)) {
                        $fail('At least one group must be selected.');
                    }
                } elseif (!$value instanceof \Illuminate\Http\UploadedFile) {
                    $fail('The contacts field must be a phone number, group selection, or CSV file.');
                }
            }],
            'method' =>  $this->isUserRoute() 
                            ? ['nullable', new Enum(SmsGatewayTypeEnum::class)]
                            : ['required', new Enum(SmsGatewayTypeEnum::class)],
            'schedule_at' => ['nullable', 'date_format:Y-m-d H:i'],
            'sms_type' => ['required', Rule::in(['plain', 'unicode'])],
            'message.message_body' => ['required', 'string'],
            'gateway_id' => $this->isUserRoute() 
                                ? ['nullable', 'numeric', 'gte:-1']
                                : ['required', 'numeric', 'gte:-1'],
            'type' => ['required', Rule::in([ChannelTypeEnum::SMS->value])],
        ];
    }

    protected function isUserRoute(): bool
    {
        return Str::is('user.*', Route::currentRouteName());
    }

    public function messages(): array
    {
        return [
            'contacts.required' => translate('The recipient contacts are required.'),
            'method.required' => translate('The dispatch method is required.'),
            'schedule_at.date_format' => translate('The schedule date must be in the format "YYYY-MM-DD HH:MM".'),
            'sms_type.required' => translate('The SMS type is required.'),
            'message.message_body.required' => translate('The message body is required.'),
            'gateway_id.required' => translate('The gateway ID is required.'),
            'type.required' => translate('The dispatch type is required.'),
        ];
    }
}