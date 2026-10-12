<?php

namespace App\Http\Requests;

use App\Enums\StatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use App\Models\GeneralSetting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Closure;

class UserStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
        $credentials = json_decode(site_settings("google_recaptcha"), true);
        $status = Arr::get($credentials, "status");
        if(
            site_settings("captcha", StatusEnum::FALSE->status()) == StatusEnum::TRUE->status() 
            && site_settings("captcha_with_registration", StatusEnum::FALSE->status()) == StatusEnum::TRUE->status() 
            && $status 
            && $status == StatusEnum::TRUE->status()) {
            $rules['g-recaptcha-response'] =  ['required' , function (string $attribute, mixed $value, Closure $fail) use($credentials) {

                $g_response =  Http::asForm()->post("https://www.google.com/recaptcha/api/siteverify",[
                    "secret"=> $credentials["secret_key"],
                    "response"=> $value
                ]);

                if ($g_response["success"] == false) {
                    $fail("reCaptcha Verification failed! " . $g_response["error-codes"][0]);
                } 
            }];   
        }

        return $rules;
    }
}
