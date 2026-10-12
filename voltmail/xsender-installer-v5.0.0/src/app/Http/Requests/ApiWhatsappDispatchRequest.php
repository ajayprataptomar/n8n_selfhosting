<?php

namespace App\Http\Requests;

use App\Enums\Common\Status;
use App\Enums\System\ChannelTypeEnum;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ApiWhatsappDispatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'contact' => 'required|array|min:1',
            'contact.*.number' => 'required|max:255',
            'contact.*.message' => 'required|string',
            'contact.*.gateway_identifier' => [
                'nullable',
            ],
            'contact.*.schedule_at' => 'nullable|date_format:Y-m-d H:i:s',
            'contact.*.media' => [
                'nullable',
                Rule::in(['image', 'audio', 'video', 'document']),
            ],
            'contact.*.url' => [
                'nullable',
                'url',
            ],
            'contact.*.filename' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];
        return $rules;
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => translate('Validation failed'),
            'errors' => $validator->errors(),
        ], 422));
    }
}