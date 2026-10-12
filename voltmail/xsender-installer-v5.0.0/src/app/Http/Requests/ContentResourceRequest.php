<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContentResourceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $isUpdate = in_array($this->method(), ['PUT', 'PATCH']);

        return [
            'name'                => [$isUpdate ? 'nullable' : 'required', 'string', 'max:191'],
            'company_name'        => [$isUpdate ? 'nullable' : 'required', 'string', 'max:191'],
            'business_type'       => ['nullable', 'string', 'max:100'],
            'company_description' => ['nullable', 'string', 'max:5000'],
            'website'             => ['nullable', 'string', 'max:191'],
            'industry'            => ['nullable', 'string', 'max:100'],
            'target_audience'     => ['nullable', 'string', 'max:3000'],
            'brand_voice'         => ['nullable', 'string', 'max:100'],
            'writing_preferences' => ['nullable'],
            'products_services'   => ['nullable'],
            'faqs'                => ['nullable'],
            'templates'           => ['nullable'],
            'status'              => ['nullable', 'in:active,inactive'],
            'is_default'          => ['nullable', 'boolean'],
        ];
    }
}
