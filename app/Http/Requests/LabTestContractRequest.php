<?php

namespace App\Http\Requests;

use App\Enums\LabTestTypeEnum;
use App\Enums\ObjectTypeEnum;
use App\Models\LabTestContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Ariza yaratish / tahrirlash (laboratoriya boshlig'i): obyekt, ekspertiza xulosasi, buyurtmachi,
 * sinov usuli va mas'ul laborant. Blok va qavat ma'lumotlarini keyin laborant kiritadi.
 */
class LabTestContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = (bool)$this->route('id');
        // qo'shimcha shartnomada obyekt va buyurtmachi asosiy shartnomadan olingan - faqat laborant va izoh tahrirlanadi
        if ($isUpdate && LabTestContract::query()->whereKey($this->route('id'))->whereNotNull('parent_id')->exists()) {
            return [
                'employee_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
                'comment' => 'nullable|string|max:2000',
            ];
        }
        $isBuilding = (int)$this->input('object_type_id') === ObjectTypeEnum::BUILDING->value;
        $hasExpertise = $this->boolean('expertise_exists');
        $tinLength = (int)$this->input('customer_type') === LabTestContract::CUSTOMER_INDIVIDUAL ? 14 : 9;

        return [
            // Obyekt
            'object_type_id' => 'required|integer|in:' . ObjectTypeEnum::LINEAR->value . ',' . ObjectTypeEnum::BUILDING->value,
            'object_name' => 'required|string|max:1000',
            'construction_type' => 'required|string|max:100',
            'region_id' => ['nullable', 'integer', Rule::exists('regions', 'id')],
            'district' => 'required|string|max:255',
            'address' => 'required|string|max:1000',
            // bino kadastr bo'yicha topiladi, tarmoqda kadastr bo'lmaydi
            'cadastral_number' => [$isBuilding ? 'required' : 'nullable', 'string', 'max:100'],
            'owner_name' => 'nullable|string|max:255',
            'object_id' => ['nullable', 'integer', Rule::exists('articles', 'id')->whereNull('deleted_at')],
            // Ekspertiza xulosasi
            'expertise_exists' => 'required|boolean',
            'expertise_number' => [$hasExpertise ? 'required' : 'nullable', 'string', 'max:100'],
            // xulosa bo'lsa hujjatlar ekspertiza tizimidan olinadi (nomi va havolasi), bo'lmasa fayl yuklanadi
            'expertise_documents' => 'nullable|array|max:50',
            'expertise_documents.*.name' => 'required|string|max:255',
            'expertise_documents.*.url' => 'nullable|string|max:1000',
            'documents' => [(!$hasExpertise && !$isUpdate) ? 'required' : 'nullable', 'array', 'max:20'],
            'documents.*' => 'file|max:20480',
            // Buyurtmachi
            'customer_type' => 'required|integer|in:' . LabTestContract::CUSTOMER_LEGAL . ',' . LabTestContract::CUSTOMER_INDIVIDUAL,
            'customer_tin' => ['required', 'digits:' . $tinLength],
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:50',
            // Sinov usuli va mas'ul laborant
            'test_type' => 'required|integer|in:' . implode(',', LabTestTypeEnum::values()),
            'employee_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'comment' => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'object_type_id.required' => 'Obyekt turini tanlang',
            'object_name.required' => 'Loyihaviy obyekt nomini kiriting',
            'construction_type.required' => 'Qurilish turini tanlang',
            'district.required' => 'Tuman yoki shaharni kiriting',
            'address.required' => 'Manzilni kiriting',
            'cadastral_number.required' => 'Kadastr raqamini kiriting',
            'expertise_exists.required' => "Ekspertiza xulosasi bor yoki yo'qligini tanlang",
            'expertise_number.required' => 'Xulosa raqamini kiriting',
            'documents.required' => 'Loyiha-smeta hujjatlarini yuklang',
            'documents.*.max' => 'Hujjat hajmi 20 MB dan oshmasligi kerak',
            'customer_tin.required' => 'STIR yoki JShShIR kiriting',
            'customer_tin.digits' => 'STIR 9 xonali, JShShIR 14 xonali bo\'lishi kerak',
            'customer_name.required' => 'Buyurtmachi nomi kiritilishi shart',
            'customer_phone.required' => 'Telefon raqamini kiriting',
            'test_type.required' => 'Sinov usulini tanlang',
            'employee_id.required' => 'Laborantni tanlang',
            'employee_id.exists' => 'Laborant topilmadi',
        ];
    }
}
