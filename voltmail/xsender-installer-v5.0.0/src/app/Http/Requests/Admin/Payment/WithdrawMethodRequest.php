<?php

namespace App\Http\Requests\Admin\Payment;

use App\Enums\WithdrawDurationEnum;
use App\Rules\FileExtentionCheckRule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Foundation\Http\FormRequest;

class WithdrawMethodRequest extends FormRequest
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
        $uid = $this->route('withdraw');
        $rules = [
            'name'  => [
                "required", 
                'unique:withdraw_methods,name,' . ($uid ?? 'NULL') . ',uid'
            ],
            'image' => ["nullable",'image', new FileExtentionCheckRule(json_decode(site_settings('mime_types'),true))],
            'currency_code' => [
                'required',
                'in:'.implode(',', array_keys(getActiveCurrencies()))
            ],
            'percent_charge' => [
                'nullable',
                'numeric',
                'gte:0',
                'max:100'
            ],
            'fixed_charge' => [
                'nullable',
                'numeric',
                'gte:0'
            ],
            'minimum_amount' => [
                'nullable',
                'numeric',
                'gte:0',
                'lte:maximum_amount'
            ],
            'maximum_amount' => [
                'nullable',
                'numeric',
                'gte:0'
            ],
            'duration.*' => [
                'required',
                'array'
            ],
            'duration.unit' => [
                'required',
                new Enum(WithdrawDurationEnum::class)
            ],
            'duration.value' => [
                'required',
                'numeric'
            ],
            'note' => [
                'required',
                'string'
            ],
            'field_name' => [
                'required',
                'array',
                'min:1',
                function ($attribute, $value, $fail) {
                    if (count($value) !== count(array_unique($value))) {
                        $fail('The ' . textFormat(['_'], $attribute, ' ') . ' field contains duplicate values.');
                    }
                }
            ],
            'field_type' => [
                'required',
                'array',
                'min:1'
            ],
        ];
        
        return $rules;
    }
} 