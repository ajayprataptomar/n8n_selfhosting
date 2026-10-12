<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SmsGatewayRequest extends FormRequest
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
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'per_message_delay' => ['required', 'numeric', 'min:0'],
            'delay_after_count' => ['required', 'integer', 'min:0'],
            'delay_after_duration' => ['required', 'numeric', 'min:0'],
            'reset_after_count' => ['required', 'integer', 'min:0'],
            'type' => ['required', "string"],
            'meta_data' => ['required', 'array'],
        ];
        return $rules;
    }
}
