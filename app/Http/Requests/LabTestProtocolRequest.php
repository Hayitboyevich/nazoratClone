<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Bayonnoma shakllantirish. Ma'lumotlar ixtiyoriy - berilsa, yuborishdan oldin oxirgi tahrir sifatida saqlanadi.
 */
class LabTestProtocolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'input_data' => 'nullable|array',
            'result_data' => 'nullable|array',
            // natija: oldin (create / update da) yuborilmagan bo'lsa shu yerda majburiy - LabTestService tekshiradi
            'result' => 'nullable|integer|in:' . implode(',', \App\Enums\LabTestResultEnum::values()),
        ];
    }

    public function messages(): array
    {
        return [
            'result.in' => "Natija noto'g'ri: 1 - ijobiy, 2 - salbiy",
        ];
    }
}
