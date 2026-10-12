<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rules\Enum;
use Illuminate\Foundation\Http\FormRequest;
use App\Enums\System\TemplateApprovalStatusEnum;

class TemplateApprovalRequest extends FormRequest
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
        return [
            "approval_status" => ["required", new Enum(TemplateApprovalStatusEnum::class)],
            "remarks"   => ["required", "string", "max:255"]
        ];
    }
}
