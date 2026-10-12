<?php

namespace App\Http\Requests\User;

use App\Models\WithdrawMethod;
use App\Rules\FileExtentionCheckRule;
use Illuminate\Foundation\Http\FormRequest;

class WithdrawRequest extends FormRequest
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
            'method_uid'                => ['required', 'exists:withdraw_methods,uid'],
            'withdraw_amount'           => ['required', 'numeric', 'min:0.01'],
            'withdraw_fixed_charge'     => ['required', 'numeric'],
            'withdraw_percent_charge'   => ['required', 'numeric'],
            'withdraw_total_charge'     => ['required', 'numeric'],
            'withdraw_total'            => ['required', 'numeric'],
            'withdraw_final_amount'     => ['required', 'numeric'],
            'withdraw_currency_code'    => ['required', 'string'],
        ];

        $method = WithdrawMethod::active()
                                    ->where('uid', $this->input('method_uid'))
                                    ->first();

        if (!$method) {

            $rules['method_uid'][] = function ($attribute, $value, $fail) {
                $fail(translate('The selected withdraw method is invalid or inactive.'));
            };
        }
        $mime_types = implode(',', json_decode(site_settings('mime_types'), true));

        if ($method && is_array($method->parameters)) {
            foreach ($method->parameters as $key => $param) {
                $field_type = $param['field_type'] ?? null;
                $field_name = $param['field_name'] ?? $key;
                $field_rules = ['required'];

                if ($field_type === 'file') {
                    $field_rules[] = 'file';
                    $field_rules[] = 'max:2048';
                    $field_rules[] = 'mimes:' . $mime_types;
                    $field_rules[] = new FileExtentionCheckRule(json_decode(site_settings('mime_types'), true));
                } elseif ($field_type === 'text') {
                    $field_rules[] = 'max:191';
                } elseif ($field_type === 'textarea') {
                    $field_rules[] = 'max:10000';
                }

                $rules[$field_name] = $field_rules;
            }
        }

        return $rules;
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @return void
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        if ($this->expectsJson()) {
            
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422)
            );
        }
        parent::failedValidation($validator);
    }
}
