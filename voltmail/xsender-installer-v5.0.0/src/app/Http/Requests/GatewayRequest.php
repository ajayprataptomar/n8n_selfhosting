<?php

namespace App\Http\Requests;

use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Http\FormRequest;

class GatewayRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true; // Adjust authorization logic as needed
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = [
            'name'                  => Str::is('*.gateway.whatsapp.device.update', Route::currentRouteName())
                                            && request()->id
                                                ? ['nullable', 'string', 'max:255']
                                                : ['required', 'string', 'max:255'],

            'bulk_contact_limit'    => ['nullable', 'numeric', 'min:1'],
            'per_message_min_delay' => ['required', 'numeric', 'min:0', 'lte:per_message_max_delay'],
            'per_message_max_delay' => ['required', 'numeric', 'min:0', 'gte:per_message_min_delay'],
            'delay_after_count'     => ['required', 'integer', 'min:0'],
            'delay_after_duration'  => ['required', 'numeric', 'min:0'],
            'reset_after_count'     => ['required', 'integer', 'min:0'],
            'meta_data'             => ['nullable', 'array'],
            'address'               => $this->isEmailGatewayRoute() ? ['required', 'email', 'max:255'] : [],
        ];
        if(!request()->routeIs("*.gateway.whatsapp.device.*"))
            $rules["type"] = ['required', 'string'];

        return $rules;
    }

    /**
     * withValidator
     *
     * @param Validator $validator
     * 
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
        $type           = $this->input('type');
        $isSms          = $this->isSmsGatewayRoute();
        $isEmail        = $this->isEmailGatewayRoute();
        $gatewayConfig  = config('setting.gateway_credentials');

            $category = $isEmail 
                            ? 'email' 
                            : ($isSms 
                                ? 'sms'
                                : "whatsapp");
            
            // if (!array_key_exists($type, $gatewayConfig[$category])) {
            //     $validator->errors()->add('type', translate('The selected gateway type is invalid.'));
            //     return;
            // }

            $requiredMetaKeys   = array_keys(Arr::get($gatewayConfig, "{$category}.{$type}.meta_data", []));
            
            $submittedMeta      = $this->input('meta_data', []);

            $missingKeys    = array_diff($requiredMetaKeys, array_keys($submittedMeta));
            $extraKeys      = array_diff(array_keys($submittedMeta), $requiredMetaKeys);

            if (!empty($missingKeys)) {
                $validator->errors()->add('meta_data', translate('Missing meta data fields: ') . implode(', ', $missingKeys));
            }

            if (!empty($extraKeys)) {
                $validator->errors()->add('meta_data', translate('Unexpected meta data fields: ') . implode(', ', $extraKeys));
            }

            if ($this->input('reset_after_count') < $this->input('delay_after_count')) {
                $validator->errors()->add('reset_after_count', translate('Reset after count cannot be less than delay after count.'));
            }
        });
    }

    /**
     * isEmailGatewayRoute
     *
     * @return bool
     */
    protected function isEmailGatewayRoute(): bool
    {
        return Str::is('*.gateway.email.*', Route::currentRouteName());
    }

    /**
     * isSmsGatewayRoute
     *
     * @return bool
     */
    protected function isSmsGatewayRoute(): bool
    {
        return Str::is('*.gateway.sms.*', Route::currentRouteName());
    }

    /**
     * Get custom messages for validation errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'name.required' => translate('The gateway name is required.'),
            'name.max' => translate('The gateway name must not exceed 255 characters.'),
            'per_message_delay.required' => translate('The per message delay is required.'),
            'per_message_delay.numeric' => translate('The per message delay must be a number.'),
            'per_message_delay.min' => translate('The per message delay must be at least 0.'),
            'delay_after_count.required' => translate('The delay after count is required.'),
            'delay_after_count.integer' => translate('The delay after count must be an integer.'),
            'delay_after_count.min' => translate('The delay after count must be at least 0.'),
            'delay_after_duration.required' => translate('The delay after duration is required.'),
            'delay_after_duration.numeric' => translate('The delay after duration must be a number.'),
            'delay_after_duration.min' => translate('The delay after duration must be at least 0.'),
            'reset_after_count.required' => translate('The reset after count is required.'),
            'reset_after_count.integer' => translate('The reset after count must be an integer.'),
            'reset_after_count.min' => translate('The reset after count must be at least 0.'),
            'type.required' => translate('The gateway type is required.'),
            'type.string' => translate('The gateway type must be a string.'),
            'meta_data.required' => translate('The gateway settings (meta data) are required.'),
            'meta_data.array' => translate('The gateway settings must be a valid array.'),
            'address.required' => translate('The email address is required for email gateways.'),
            'address.email' => translate('The address must be a valid email address.'),
            'address.max' => translate('The email address must not exceed 255 characters.'),
        ];
    }
}