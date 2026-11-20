<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Qo'shimcha shartnoma yaratish / tahrirlash (laboratoriya boshlig'i). Obyekt, buyurtmachi va sinov usuli
 * asosiy shartnomadan olinadi - faqat mas'ul laborant (berilmasa asosiy shartnomaniki) va izoh kiritiladi.
 */
class LabTestContractAdditionalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'comment' => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.exists' => 'Laborant topilmadi',
        ];
    }
}
