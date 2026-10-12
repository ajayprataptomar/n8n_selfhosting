<?php

namespace App\Http\Requests\Admin;

use App\Enums\StatusEnum;
use App\Enums\System\SessionStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class AndroidApiRequest extends FormRequest
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
            'name' => [
                'required', 
                'username_format', 
                // 'unique:android_apis,name,'.request()->input('id')
            ],
            // 'password' => [
            //     'required', 
            // ],
        ];

        if (request()->routeIs('*.gateway.sms.android.update')) {
            // $rules['id'] = ['required', 'exists:android_apis,id'];
            $rules['status'] = ['required', new Enum(SessionStatusEnum::class)];

        } 
        // else {
        //     $rules['password'] = ['confirmed'];
        // }
        
        return $rules;
    }
}
