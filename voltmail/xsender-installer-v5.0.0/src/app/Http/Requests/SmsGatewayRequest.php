<?php

namespace App\Http\Requests;

use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Http\FormRequest;

class SmsGatewayRequest extends JsonRequest
{
    public function authorize()
    {
        return true;
    }

    protected function prepareForValidation()
    {
        if ($this->has('meta_data') && is_string($this->input('meta_data'))) {
            $metaData = json_decode($this->input('meta_data'), true);
            $this->merge(['meta_data' => $metaData]);
        }
    }

    public function rules()
    {
        $rules = [
            'name'                  => Str::is('*.gateway.whatsapp.device.update', Route::currentRouteName())
                                            && request()->id
                                                ? ['nullable', 'string', 'max:255']
                                                : ['required', 'string', 'max:255'],
            'gateway_mode'          => ['required', 'in:built-in,custom'],
            'is_custom_api'         => ['required', 'in:0,1'],
            'bulk_contact_limit'    => ['nullable', 'numeric', 'min:1'],
            'per_message_min_delay' => ['required', 'numeric', 'min:0', 'lte:per_message_max_delay'],
            'per_message_max_delay' => ['required', 'numeric', 'min:0', 'gte:per_message_min_delay'],
            'delay_after_count'     => ['required', 'integer', 'min:0'],
            'delay_after_duration'  => ['required', 'numeric', 'min:0'],
            'reset_after_count'     => ['required', 'integer', 'min:0'],
            'type'                  => ['required_if:gateway_mode,built-in', 'string'],
            'meta_data'             => ['nullable', 'array'],
            'address'               => $this->isEmailGatewayRoute() ? ['required', 'email', 'max:255'] : [],
        ];

        if ($this->isSmsGatewayRoute() && $this->input('is_custom_api') == '1') {
            
            $rules = array_merge($rules, [
                'meta_data.url' => ['required', 'url'],
                'meta_data.method' => ['required', 'in:GET,POST'],
                'meta_data.query_params' => ['nullable', 'array'],
                'meta_data.query_params.*.key' => ['nullable', 'string'],
                'meta_data.query_params.*.value' => ['nullable', 'string'],
                'meta_data.query_params.*.enabled' => ['nullable', 'boolean'],
                'meta_data.headers' => ['nullable', 'array'],
                'meta_data.headers.*.key' => ['nullable', 'string'],
                'meta_data.headers.*.value' => ['nullable', 'string'],
                'meta_data.headers.*.enabled' => ['nullable', 'boolean'],
                'meta_data.auth_type' => ['required', 'in:none,api_key,bearer'],
                'meta_data.api_key_name' => ['required_if:meta_data.auth_type,api_key', 'nullable', 'string'],
                'meta_data.api_key_value' => ['required_if:meta_data.auth_type,api_key', 'nullable', 'string'],
                'meta_data.bearer_token' => ['required_if:meta_data.auth_type,bearer', 'nullable', 'string'],
                'meta_data.body_type' => ['required', 'in:none,form-data,x-www-form-urlencoded,raw'],
                'meta_data.form_data' => ['nullable', 'array'],
                'meta_data.form_data.*.key' => ['nullable', 'string'],
                'meta_data.form_data.*.value' => ['nullable', 'string'],
                'meta_data.form_data.*.enabled' => ['nullable', 'boolean'],
                'meta_data.urlencoded_data' => ['nullable', 'array'],
                'meta_data.urlencoded_data.*.key' => ['nullable', 'string'],
                'meta_data.urlencoded_data.*.value' => ['nullable', 'string'],
                'meta_data.urlencoded_data.*.enabled' => ['nullable', 'boolean'],
                'meta_data.raw_type' => ['required_if:meta_data.body_type,raw', 'in:text,javascript,json,html,xml', 'nullable'],
                'meta_data.raw_body' => ['required_if:meta_data.body_type,raw', 'nullable', 'string'],
                'meta_data.status_type' => ['required', 'in:http_code,response_key'],
                'meta_data.success_codes' => ['required_if:meta_data.status_type,http_code', 'nullable', 'string'],
                'meta_data.failure_codes' => ['nullable', 'string'],
                'meta_data.status_key' => ['required_if:meta_data.status_type,response_key', 'nullable', 'string'],
                'meta_data.success_values' => ['required_if:meta_data.status_type,response_key', 'nullable', 'string'],
                'meta_data.failure_values' => ['nullable', 'string'],
                'meta_data.error_key' => ['nullable', 'string'],
                'meta_data.fallback_message' => ['nullable', 'string'],
            ]);
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $type = $this->input('type');
            $isSms = $this->isSmsGatewayRoute();
            $isEmail = $this->isEmailGatewayRoute();
            $gatewayConfig = config('setting.gateway_credentials');

            $category = $isEmail 
                            ? 'email' 
                            : ($isSms 
                                ? 'sms'
                                : "whatsapp");
            
            if ($type && $this->input('gateway_mode') === 'built-in') {
                $requiredMetaKeys = array_keys(Arr::get($gatewayConfig, "{$category}.{$type}.meta_data", []));
                $submittedMeta = $this->input('meta_data', []);

                $missingKeys = array_diff($requiredMetaKeys, array_keys($submittedMeta));
                $extraKeys = array_diff(array_keys($submittedMeta), $requiredMetaKeys);

                if (!empty($missingKeys)) {
                    $validator->errors()->add('meta_data', translate('Missing meta data fields: ') . implode(', ', $missingKeys));
                }

                if (!empty($extraKeys)) {
                    $validator->errors()->add('meta_data', translate('Unexpected meta data fields: ') . implode(', ', $extraKeys));
                }
            }

            if ($this->isSmsGatewayRoute() && $this->input('is_custom_api') === '1') {
                $queryParams = $this->input('meta_data.query_params', []);
                foreach ($queryParams as $index => $param) {
                    if (!empty($param['key']) || !empty($param['value'])) {
                        if (empty($param['key'])) {
                            $validator->errors()->add("meta_data.query_params.{$index}.key", translate('The query parameter key is required when a value is provided.'));
                        }
                        if (empty($param['value']) && !empty($param['key'])) {
                            $validator->errors()->add("meta_data.query_params.{$index}.value", translate('The query parameter value is required when a key is provided.'));
                        }
                    }
                }

                $headers = $this->input('meta_data.headers', []);
                foreach ($headers as $index => $header) {
                    if (!empty($header['key']) || !empty($header['value'])) {
                        if (empty($header['key'])) {
                            $validator->errors()->add("meta_data.headers.{$index}.key", translate('The header key is required when a value is provided.'));
                        }
                        if (empty($header['value']) && !empty($header['key'])) {
                            $validator->errors()->add("meta_data.headers.{$index}.value", translate('The header value is required when a key is provided.'));
                        }
                    }
                }

                if ($this->input('meta_data.body_type') === 'form-data') {
                    $formData = $this->input('meta_data.form_data', []);
                    foreach ($formData as $index => $data) {
                        if (!empty($data['key']) || !empty($data['value'])) {
                            if (empty($data['key'])) {
                                $validator->errors()->add("meta_data.form_data.{$index}.key", translate('The form data key is required when a value is provided.'));
                            }
                            if (empty($data['value']) && !empty($data['key'])) {
                                $validator->errors()->add("meta_data.form_data.{$index}.value", translate('The form data value is required when a key is provided.'));
                            }
                        }
                    }
                }

                if ($this->input('meta_data.body_type') === 'x-www-form-urlencoded') {
                    $urlencodedData = $this->input('meta_data.urlencoded_data', []);
                    foreach ($urlencodedData as $index => $data) {
                        if (!empty($data['key']) || !empty($data['value'])) {
                            if (empty($data['key'])) {
                                $validator->errors()->add("meta_data.urlencoded_data.{$index}.key", translate('The URL-encoded data key is required when a value is provided.'));
                            }
                            if (empty($data['value']) && !empty($data['key'])) {
                                $validator->errors()->add("meta_data.urlencoded_data.{$index}.value", translate('The URL-encoded data value is required when a key is provided.'));
                            }
                        }
                    }
                }

                if ($this->input('meta_data.status_type') === 'http_code') {
                    $successCodes = $this->input('meta_data.success_codes');
                    if (empty($successCodes)) {
                        $validator->errors()->add('meta_data.success_codes', translate('The success codes field is required when status type is HTTP code.'));
                    } else {
                        $codes = array_map('trim', explode(',', $successCodes));
                        foreach ($codes as $code) {
                            if (!is_numeric($code) || $code < 100 || $code > 599) {
                                $validator->errors()->add('meta_data.success_codes', translate('The success codes must be valid HTTP status codes between 100 and 599.'));
                                break;
                            }
                        }
                    }

                    $failureCodes = $this->input('meta_data.failure_codes');
                    if (!empty($failureCodes)) {
                        $codes = array_map('trim', explode(',', $failureCodes));
                        foreach ($codes as $code) {
                            if (!is_numeric($code) || $code < 100 || $code > 599) {
                                $validator->errors()->add('meta_data.failure_codes', translate('The failure codes must be valid HTTP status codes between 100 and 599.'));
                                break;
                            }
                        }
                    }
                }

                if ($this->input('meta_data.status_type') === 'response_key') {
                    $successValues = $this->input('meta_data.success_values');
                    if (empty($successValues)) {
                        $validator->errors()->add('meta_data.success_values', translate('The success values field is required when status type is response key.'));
                    }
                }
            }

            if ($this->input('reset_after_count') < $this->input('delay_after_count')) {
                $validator->errors()->add('reset_after_count', translate('Reset after count cannot be less than delay after count.'));
            }
        });
    }

    protected function isEmailGatewayRoute(): bool
    {
        return Str::is('*.gateway.email.*', Route::currentRouteName());
    }

    protected function isSmsGatewayRoute(): bool
    {
        return Str::is('*.gateway.sms.*', Route::currentRouteName());
    }

    public function messages()
    {
        return [
            'name.required'                                 => translate('The gateway name is required.'),
            'name.max'                                      => translate('The gateway name must not exceed 255 characters.'),
            'per_message_min_delay.required'                => translate('The per message minimum delay is required.'),
            'per_message_min_delay.numeric'                 => translate('The per message minimum delay must be a number.'),
            'per_message_min_delay.min'                     => translate('The per message minimum delay must be at least 0.'),
            'per_message_min_delay.lte'                     => translate('The per message minimum delay must be less than or equal to the maximum delay.'),
            'per_message_max_delay.required'                => translate('The per message maximum delay is required.'),
            'per_message_max_delay.numeric'                 => translate('The per message maximum delay must be a number.'),
            'per_message_max_delay.min'                     => translate('The per message maximum delay must be at least 0.'),
            'per_message_max_delay.gte'                     => translate('The per message maximum delay must be greater than or equal to the minimum delay.'),
            'delay_after_count.required'                    => translate('The delay after count is required.'),
            'delay_after_count.integer'                     => translate('The delay after count must be an integer.'),
            'delay_after_count.min'                         => translate('The delay after count must be at least 0.'),
            'delay_after_duration.required'                 => translate('The delay after duration is required.'),
            'delay_after_duration.numeric'                  => translate('The delay after duration must be a number.'),
            'delay_after_duration.min'                      => translate('The delay after duration must be at least 0.'),
            'reset_after_count.required'                    => translate('The reset after count is required.'),
            'reset_after_count.integer'                     => translate('The reset after count must be an integer.'),
            'reset_after_count.min'                         => translate('The reset after count must be at least 0.'),
            'type.required_if'                              => translate('The gateway type is required for Built-in API.'),
            'type.string'                                   => translate('The gateway type must be a string.'),
            'meta_data.array'                               => translate('The gateway settings must be a valid array.'),
            'address.required'                              => translate('The email address is required for email gateways.'),
            'address.email'                                 => translate('The address must be a valid email address.'),
            'address.max'                                   => translate('The email address must not exceed 255 characters.'),
            'meta_data.url.required'                        => translate('The API URL is required for Custom API.'),
            'meta_data.url.url'                             => translate('The API URL must be a valid URL.'),
            'meta_data.method.required'                     => translate('The HTTP method is required for Custom API.'),
            'meta_data.auth_type.required'                  => translate('The authorization type is required for Custom API.'),
            'meta_data.auth_type.in'                        => translate('The authorization type must be none, api_key, or bearer.'),
            'meta_data.api_key_name.required_if'            => translate('The API key name is required when authorization type is API Key.'),
            'meta_data.api_key_value.required_if'           => translate('The API key value is required when authorization type is API Key.'),
            'meta_data.bearer_token.required_if'            => translate('The bearer token is required when authorization type is Bearer.'),
            'meta_data.body_type.required'                  => translate('The body type is required for Custom API.'),
            'meta_data.body_type.in'                        => translate('The body type must be none, form-data, x-www-form-urlencoded, or raw.'),
            'meta_data.raw_type.required_if'                => translate('The raw type is required when body type is Raw.'),
            'meta_data.raw_type.in'                         => translate('The raw type must be text, javascript, json, html, or xml.'),
            'meta_data.raw_body.required_if'                => translate('The raw body is required when body type is Raw.'),
            'meta_data.status_type.required'                => translate('The status type is required for Custom API.'),
            'meta_data.status_type.in'                      => translate('The status type must be http_code or response_key.'),
            'meta_data.success_codes.required_if'           => translate('Success codes are required when status type is HTTP Code.'),
            'meta_data.status_key.required_if'              => translate('Status key is required when status type is Response Key.'),
            'meta_data.success_values.required_if'          => translate('Success values are required when status type is Response Key.'),
        ];
    }
}