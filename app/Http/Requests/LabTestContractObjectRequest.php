<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Tasdiqlangan shartnomaga obyekt biriktirish - shundan so'ng sinovlar o'tkaziladi.
 */
class LabTestContractObjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'object_id' => ['required', 'integer', Rule::exists('articles', 'id')->whereNull('deleted_at')],
        ];
    }

    public function messages(): array
    {
        return [
            'object_id.required' => 'Obyekt tanlanishi shart',
            'object_id.exists' => 'Obyekt topilmadi',
        ];
    }
}
