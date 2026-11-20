<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Buxgalter yoki inspeksiya boshlig'i arizani laborantga qaytaradi: nima tuzatilishi kerakligi va izoh.
 */
class LabTestContractReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'comment' => 'required|string|max:2000',
            // masalan: "Bloklar ma'lumoti", "Tanlangan ishlar", "Miqdorlar", "Narx va summa", "Boshqa"
            'reasons' => 'nullable|array|max:20',
            'reasons.*' => 'required|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'comment.required' => 'Qaytarish sababini izohda yozing',
        ];
    }
}
