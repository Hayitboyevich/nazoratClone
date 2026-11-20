<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Bloklar (laborant kiritadi). Ro'yxat to'liq yuboriladi: id si bor blok yangilanadi, id siz - yaratiladi,
 * yuborilmagan blok (ishlari bilan birga) o'chiriladi.
 * data - erkin tuzilish: {gps, fund: {tur, B, V, S, L}, floors: [{kol, rig, pl, arm, wall, chok}]}.
 */
class LabTestContractBlocksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'blocks' => 'required|array|min:1|max:50',
            'blocks.*.id' => 'nullable|integer|distinct',
            'blocks.*.name' => 'required|string|max:255',
            'blocks.*.floors_count' => 'required|integer|between:1,60',
            // data ichiga alohida qoida yozilmaydi: aks holda validated() faqat qoidasi bor kalitlarni qoldiradi
            'blocks.*.data' => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'blocks.required' => "Kamida bitta blok kiritilishi shart",
            'blocks.*.name.required' => "Blok nomi ko'rsatilmagan",
            'blocks.*.floors_count.required' => 'Qavatlar soni kiritilishi shart',
            'blocks.*.floors_count.between' => "Qavatlar soni 1 dan 60 gacha bo'lishi kerak",
        ];
    }
}
