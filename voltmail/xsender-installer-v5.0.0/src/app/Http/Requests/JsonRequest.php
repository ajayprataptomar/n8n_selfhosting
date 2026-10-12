<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;

class JsonRequest extends FormRequest
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

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param ValidatorContract $validator
     * @return void
     *
     * @throws HttpResponseException
     */
    protected function failedValidation(ValidatorContract $validator)
    {
        $errors = $validator->errors()->all();
        $message = implode(' ', $errors); 

        throw new HttpResponseException(
            response()->json([
                'status' => 'error',
                'message' => $message,
            ], 422)
        );
    }
}