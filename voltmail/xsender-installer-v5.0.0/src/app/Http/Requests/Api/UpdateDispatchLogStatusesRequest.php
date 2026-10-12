<?php

namespace App\Http\Requests\Api;

use App\Enums\System\CommunicationStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Http\Utility\Api\ApiJsonResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateDispatchLogStatusesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true; // Authorization is handled by the middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'logs' => ['required', 'array', 'min:1'],
            'logs.*.id' => ['required', 'integer', Rule::exists('dispatch_logs', 'id')],
            'logs.*.status' => ['required', Rule::in(CommunicationStatusEnum::getValues())],
            'logs.*.response_message' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) {
                    preg_match('/logs\.(\d+)\.response_message/', $attribute, $matches);
                    $index = $matches[1] ?? null;

                    $status = $this->input("logs.{$index}.status");
                    
                    if ($status == CommunicationStatusEnum::FAIL->value && is_null($value)) {
                        $fail(translate('The response_message is required when the status is failed.'));
                    }

                    if ($status != CommunicationStatusEnum::FAIL->value && !is_null($value)) {
                        $fail(translate('The response_message should only be provided when the status is failed.'));
                    }
                },
            ],
        ];
    }

    /**
     * Get custom messages for validation errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'logs.required' => 'The logs array is required.',
            'logs.array' => 'The logs must be an array.',
            'logs.min' => 'The logs array must contain at least one item.',
            'logs.*.id.required' => 'Each log must have an ID.',
            'logs.*.id.integer' => 'Each log ID must be an integer.',
            'logs.*.id.exists' => 'The specified log ID does not exist.',
            'logs.*.status.required' => 'Each log must have a status.',
            'logs.*.status.in' => 'The status must be one of: ' . implode(', ', CommunicationStatusEnum::getValues()),
            'logs.*.response_message.string' => 'The response_message must be a string.',
        ];
    }

    /**
     * failedValidation
     *
     * @param Validator $validator
     * 
     * @return never
     */
    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            ApiJsonResponse::validationError($validator->errors())
        );
    }
}