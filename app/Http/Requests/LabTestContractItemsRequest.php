<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shartnoma ilovasi (laborant kiritadi): har bir blok uchun tanlangan ishlar, ular ichidagi sinovlar va miqdor.
 * Ro'yxat to'liq yuboriladi - oldingi tanlov almashtiriladi. Narx va summalar serverda hisoblanadi.
 * Ko'p qavatli ish turida miqdor har bir qavat uchun alohida: floor_quantities = {"1": 20, "2": 22}.
 */
class LabTestContractItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => 'required|array|min:1|max:1000',
            'items.*.block_id' => 'required|integer',
            'items.*.work_type_id' => ['required', 'integer',
                Rule::exists('work_types', 'id')->where('is_laboratoriya', true)->where('status', true)],
            'items.*.lab_method_id' => ['required', 'integer',
                Rule::exists('lab_methods', 'id')->where('status', true)->whereNull('deleted_at')],
            // qavatsiz ishda majburiy; qavatli ishda - floor_indicator_ids da ko'rsatilmagan qavatlar uchun
            'items.*.indicator_ids' => 'required_without:items.*.floor_indicator_ids|array|min:1',
            // ko'p qavatli ish turida sinovlar har qavat uchun alohida: qavat raqami => sinovlar
            'items.*.floor_indicator_ids' => 'nullable|array|max:60',
            'items.*.floor_indicator_ids.*' => 'array|min:1',
            'items.*.floor_indicator_ids.*.*' => 'integer',
            // «distinct» ishlatilmaydi: u barcha qatorlar bo'yicha solishtiradi, bir sinov esa turli qatorlarda takrorlanadi
            'items.*.indicator_ids.*' => 'integer',
            // qavatsiz ish turida (kotlovan, fundament) - bitta miqdor
            'items.*.quantity' => 'nullable|integer|between:0,100000',
            'items.*.proposed_quantity' => 'nullable|integer|min:0',
            // ko'p qavatli ish turida - qavat raqami => miqdor
            'items.*.floor_quantities' => 'nullable|array|max:60',
            'items.*.floor_quantities.*' => 'integer|between:0,100000',
            'items.*.proposed_floor_quantities' => 'nullable|array|max:60',
            'items.*.proposed_floor_quantities.*' => 'integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Kamida bitta ish tanlanishi shart',
            'items.*.block_id.required' => "Blok ko'rsatilishi shart",
            'items.*.work_type_id.exists' => 'Ish turi topilmadi yoki laboratoriyaga tegishli emas',
            'items.*.lab_method_id.exists' => 'Sinov usuli topilmadi',
            'items.*.indicator_ids.required_without' => 'Har bir ishda kamida bitta sinov tanlanishi shart',
            'items.*.floor_indicator_ids.*.min' => 'Qavatda kamida bitta sinov tanlanishi shart',
            'items.*.indicator_ids.required' => 'Har bir ishda kamida bitta sinov tanlanishi shart',
        ];
    }
}
