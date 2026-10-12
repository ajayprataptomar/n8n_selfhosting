<?php

namespace App\Http\Requests;

use App\Enums\System\ChannelTypeEnum;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class WhatsappDispatchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
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
            'method' => ['required'],
            'schedule_at' => ['nullable', 'date_format:Y-m-d H:i'],
            'message.message_body' => ['nullable', 'string'],
            'gateway_id' => ['required', 'numeric', 'gte:-1'],
            'type' => ['required', Rule::in([ChannelTypeEnum::WHATSAPP->value])],
        ];
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
