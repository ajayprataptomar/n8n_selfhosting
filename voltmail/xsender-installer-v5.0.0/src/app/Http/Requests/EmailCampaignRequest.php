<?php

namespace App\Http\Requests;

use App\Enums\StatusEnum;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Http\FormRequest;

class EmailCampaignRequest extends FormRequest
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
    protected function prepareForValidation()
    {
        if(!$this->has('gateway_id')) {
            $user = auth()->user();
            $this->merge([
                'gateway_id' => $this->getGatewayID($user),
            ]);
        }
    }
    private function getGatewayID($user) {

        $availble_email_gateways = json_decode(site_settings('accessible_email_gateways'), true);
        if($user
            && $user?->gateway_credentials && isset($user->gateway_credentials->specific_gateway_access, $user->gateway_credentials->accessible_email_gateways)
            && $user->gateway_credentials->specific_gateway_access == StatusEnum::TRUE->status()) {

            $availble_email_gateways = $user->gateway_credentials->accessible_email_gateways;
        }

        // When the admin has not configured an "accessible_email_gateways" list AND the
        // user is not on a per-user gateway plan, this value can be null or empty. Falling
        // through to array_flip(null) used to fatal with TypeError on PHP 8.x.
        // '-1' is the magic "auto-pick" value that GatewayService::assignGateway already
        // understands — it will pick any active admin gateway for the channel.
        if (!is_array($availble_email_gateways) || empty($availble_email_gateways)) {
            return '-1';
        }

        $gateway_id = array_rand(array_flip($availble_email_gateways));
        return (string) $gateway_id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'contacts'      => 'required|array',
            'name'          => 'required',
            'schedule_at'   => ['required', 'date_format:Y-m-d H:i'],
            'repeat_time'   => ['required', 'integer', 'gte:0'],
            'repeat_format' => [
                'nullable',
                'required_if:repeat_time,>0'
            ],
            'message'              => 'array',
            'message.subject'      => 'required',
            'message.main_body' => 'required',
            'gateway_id'            => $this->isUserRoute() 
                                                    ? ['nullable', 'numeric', 'gte:-1']
                                                    : ['required', 'numeric', 'gte:-2'],
        ];
    }
    protected function isUserRoute(): bool
    {
        return Str::is('user.*', Route::currentRouteName());
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ((int)$this->input('repeat_time') > 0 && empty($this->input('repeat_format'))) {
                $validator->errors()->add('repeat_format', 'The repeat format field is required when repeat time is greater than 0.');
            }
        });
    }
}
