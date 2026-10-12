<?php

namespace App\Http\Requests;

use App\Enums\System\SessionStatusEnum;
use App\Http\Utility\Api\ApiJsonResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class RegisterAndroidSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * rules
     *
     * @return array
     */
    public function rules(): array
    {
        
        return [
            'token'     => ["required", "string"],
            'status'    => ["required", "in:".SessionStatusEnum::CONNECTED->value.",".SessionStatusEnum::DISCONNECTED->value],
        ];
    }

    public function validationData(): array
    {
        return array_merge($this->all(), [
            'token' => $this->bearerToken(),
        ]);
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