<?php

namespace App\Http\Requests;

use App\Enums\Common\Status;
use App\Enums\StatusEnum;
use App\Enums\System\ChannelTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ApiEmailDispatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxFiles = (int) site_settings('email_attachment_max_files', 5);
        $maxSizeKb = (int) site_settings('email_attachment_max_size', 10) * 1024;

        return [
            'contact' => 'required|array|min:1',
            'contact.*.email' => 'required|email|max:255',
            'contact.*.subject' => 'required|string|max:255',
            'contact.*.message' => 'required|string',
            'contact.*.gateway_identifier' => [
                'nullable',
                Rule::exists('gateways', 'uid')->where(function ($query) {
                    $query->where('status', Status::ACTIVE)
                          ->where('channel', ChannelTypeEnum::EMAIL);
                }),
            ],
            'contact.*.sender_name' => 'nullable|string|max:255',
            'contact.*.reply_to_email' => 'nullable|email|max:255',
            'contact.*.schedule_at' => 'nullable|date_format:Y-m-d H:i:s',
            'attachments'   => ['nullable', 'array', 'max:' . $maxFiles],
            'attachments.*' => ['file', 'max:' . $maxSizeKb, 'mimes:pdf,doc,docx,xls,xlsx,csv,txt,png,jpg,jpeg,gif,zip,rar,svg,webp'],
        ];
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