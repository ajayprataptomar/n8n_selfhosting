<?php

namespace App\Http\Requests;

use App\Enums\Common\Status;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Http\Utility\Api\ApiJsonResponse;
use Illuminate\Validation\Rules\Enum;

class ManageAndroidSimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'sim_number'            => 'required|string|max:20',
            'per_message_delay'     => 'nullable|numeric|min:0',
            'delay_after_count'     => 'nullable|integer|min:0',
            'delay_after_duration'  => 'nullable|numeric|min:0',
            'reset_after_count'     => 'nullable|integer|min:0',
            'status'                => ['nullable', new Enum(Status::class)],
        ];

        if ($this->isMethod('POST') && $this->route()->getName() === 'api.gateway.sms.android.sim.update') {
            $rules['id'] = 'required|exists:android_sims,id';
        }
        return $rules;
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            ApiJsonResponse::validationError($validator->errors())
        );
    }
}