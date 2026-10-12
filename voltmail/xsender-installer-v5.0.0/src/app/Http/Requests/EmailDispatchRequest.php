<?php

namespace App\Http\Requests;

use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use App\Enums\System\ChannelTypeEnum;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Http\FormRequest;

class EmailDispatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'contacts' => ['required', function ($attribute, $value, $fail) {
                if (is_string($value)) {
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $fail('The contact must be a valid email address.');
                    }
                } elseif (is_array($value)) {
                    if (empty($value)) {
                        $fail('At least one group must be selected.');
                    }
                } elseif (!$value instanceof \Illuminate\Http\UploadedFile) {
                    $fail('The contacts field must be an email address, group selection, or CSV file.');
                }
            }],
            'message.subject'       => ['required', 'string'],
            'message.main_body'     => ['required', 'string'],
            'type'                  => ['required', Rule::in([ChannelTypeEnum::EMAIL->value])],
            'gateway_id'            => $this->isUserRoute() 
                                                    ? ['nullable', 'numeric', 'gte:-1']
                                                    : ['required', 'numeric', 'gte:-2'],
            'schedule_at'           => ['nullable', 'date_format:Y-m-d H:i'],
            'email_from_name'       => ['nullable', 'string'],
            'reply_to_address'      => ['nullable', 'email'],
            'email_attachments'     => ['nullable', 'array', 'max:' . (int) site_settings('email_attachment_max_files', 5)],
            'email_attachments.*'   => ['file', 'max:' . ((int) site_settings('email_attachment_max_size', 10) * 1024), 'mimes:pdf,doc,docx,xls,xlsx,csv,txt,png,jpg,jpeg,gif,zip,rar,svg,webp'],
        ];

        if ($this->input('gateway_id') == -2) {
            $rules['custom_gateway_parameter']          = ['required', 'array'];
            $rules['custom_gateway_parameter.name']     = ['required', 'string'];
            $rules['custom_gateway_parameter.address']  = ['required', 'email'];
            $rules['custom_gateway_parameter.custom_gateway_parameter']  = ['nullable', 'numeric', 'min:1'];
            $rules['custom_gateway_parameter.type']     = ['required', Rule::in(['smtp', 'sendgrid', 'aws', 'mailjet', 'mailgun'])];
            $rules['custom_gateway_parameter.meta_data'] = ['required', 'array'];

            $rules['custom_gateway_parameter.meta_data.*'] = [
                function ($attribute, $value, $fail) {

                    $type           = $this->input('custom_gateway_parameter.type');
                    $metaData       = $this->input('custom_gateway_parameter.meta_data');
                    $emailGateways  = config('setting.gateway_credentials.email');
                    
                    
                    $requiredFieldsMap = collect($emailGateways)
                        ->map(function ($gatewayConfig) {
                            
                        return collect(Arr::get($gatewayConfig, "meta_data"))
                            ->except('encryption') 
                            ->keys()
                            ->all();
                    })->toArray();
                    $requiredFields = Arr::get($requiredFieldsMap, $type);

                    if(!$requiredFields) $fail(translate("Could not find configurations"));

                    collect($requiredFields)
                        ->each(function ($field) use ($metaData, $fail, $type) {
                            
                            if (!isset($metaData[$field]) 
                                    || empty($metaData[$field])) 
                                $fail(translate("The meta_data.{$field} field is required for " . ucfirst($type) . " gateway."));
                        });
                        
                    if ($type == 'smtp' && Arr::has($metaData, "encription")) {

                        $validEncryptions = array_values(config('setting.gateway_credentials.email.smtp.encryption'));
                        if (!in_array(Arr::has($metaData, "encription"), $validEncryptions)) 
                            $fail(translate('The meta_data.encryption must be one of: ' . implode(', ', $validEncryptions)));
                    }
                }
            ];
        }

        return $rules;
    }

    protected function isUserRoute(): bool
    {
        return Str::is('user.*', Route::currentRouteName());
    }

    public function messages(): array
    {
        return [
            'contacts.required'             => translate('The recipient contacts are required.'),
            'schedule_at.date_format'       => translate('The schedule date must be in the format "YYYY-MM-DD HH:MM".'),
            'message.main_body.required'    => translate('The message body is required.'),
            'gateway_id.required'           => translate('The gateway ID is required.'),
            'type.required'                 => translate('The dispatch type is required.'),
            'custom_gateway_parameter.required' => translate('Custom gateway parameters are required when gateway_id is -2.'),
            'custom_gateway_parameter.name.required' => translate('The gateway name is required.'),
            'custom_gateway_parameter.address.required' => translate('The gateway address is required.'),
            'custom_gateway_parameter.address.email' => translate('The gateway address must be a valid email.'),
            'custom_gateway_parameter.type.required' => translate('The gateway type is required.'),
            'custom_gateway_parameter.type.in' => translate('The gateway type must be one of: smtp, sendgrid, aws, mailjet, mailgun.'),
            'custom_gateway_parameter.meta_data.required' => translate('The gateway meta data is required.'),
            'email_attachments.max'         => translate('You can attach a maximum of') . ' ' . (int) site_settings('email_attachment_max_files', 5) . ' ' . translate('files'),
            'email_attachments.*.max'       => translate('Each attachment must not exceed') . ' ' . (int) site_settings('email_attachment_max_size', 10) . ' MB',
            'email_attachments.*.mimes'     => translate('Allowed file types: pdf, doc, docx, xls, xlsx, csv, txt, png, jpg, jpeg, gif, zip, rar, svg, webp.'),
        ];
    }
}