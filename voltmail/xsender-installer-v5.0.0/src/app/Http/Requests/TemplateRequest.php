<?php

namespace App\Http\Requests;

use App\Enums\ServiceType;
use App\Enums\System\ChannelTypeEnum;
use Illuminate\Validation\Rules\Enum;
use App\Enums\System\TemplateProviderEnum;
use Illuminate\Foundation\Http\FormRequest;

class TemplateRequest extends FormRequest
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
            'name'          => request()->method() == "PATCH" 
                                    ? ['nullable', 'string', 'max:255']
                                    : ['required', 'string', 'max:255'],
            'channel'       => ['required', new Enum(ChannelTypeEnum::class)],
            'provider'      => request()->channel == ChannelTypeEnum::EMAIL->value 
                                    ? ['required', new Enum(TemplateProviderEnum::class)]
                                    : ['nullable', new Enum(TemplateProviderEnum::class)],
            'template_data' => ['required', 'array'],
            'template_json' => ['nullable', 'string']
        ];
        return $rules;
    }
}
