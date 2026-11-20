<?php

namespace App\Http\Requests;

use App\Models\LabTest;
use App\Enums\LabTestResultEnum;
use App\Models\LabTestContractItem;
use App\Support\LabTestMethodRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sinov yaratish / tahrirlash. Sinov shartnoma ilovasining bitta qatori (lab_test_contract_items) bo'yicha
 * o'tkaziladi: usul va ish turi shu qatordan olinadi, sinovlar faqat shartnomada tanlanganlari ichidan tanlanadi.
 * input_data tuzilishi usulga (lab_methods.code) bog'liq - usullarga xos qoidalar keyinchalik methodRules() ga qo'shiladi.
 */
class LabTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = (bool)$this->route('id');
        $itemId = $isUpdate
            ? LabTest::query()->whereKey($this->route('id'))->value('lab_contract_item_id')
            : (int)$this->input('lab_contract_item_id');
        $item = $itemId ? LabTestContractItem::query()->with(['workType', 'method:id,code'])->find($itemId) : null;
        $this->methodCode = $item?->method?->code;
        // qavat faqat ko'p qavatli ish turida (masalan «Konstruksiya elementlari») ko'rsatiladi
        $hasFloors = (bool)$item?->workType?->is_multiple_floor;

        return array_merge([
            // shartnoma qatori (blok + ish) yaratilgandan keyin o'zgarmaydi
            'lab_contract_item_id' => $isUpdate
                ? 'prohibited'
                : ['required', 'integer', Rule::exists('lab_test_contract_items', 'id')],
            'floor' => $hasFloors
                ? ['required', 'integer', Rule::in(array_keys($item->floor_quantities ?? []))]
                : ['prohibited'],
            // shu sinov (bayonnoma) shartnomadagi miqdorning nechtasini qoplaydi: 3 partiyadan 1 tasi, 2 tasi ...
            // shartnomadagi miqdordan oshmasligi LabTestService da tekshiriladi
            'quantity' => 'required|integer|min:1',
            // sinov o'tkazilgan sana: asboblar sertifikati shu sanaga tekshiriladi (yuborilmasa - bugun)
            'test_date' => 'nullable|date_format:Y-m-d|before_or_equal:today',
            // natija: 1 - ijobiy, 2 - salbiy; hisobni frontend qiladi. Bayonnoma shakllantirilguncha bo'sh qolishi mumkin
            'result' => 'nullable|integer|in:' . implode(',', LabTestResultEnum::values()),
            // bayonnomaga chiqadigan sinovlar - faqat shartnomada tanlanganlari ichidan
            'indicator_ids' => 'required|array|min:1',
            // qavatli ishda - shu qavatda tanlanganlari
            'indicator_ids.*' => ['integer', 'distinct', Rule::in($item ? $item->indicatorIdsFor($hasFloors && is_numeric($this->input('floor')) ? (int)$this->input('floor') : null) : [])],
            // sinovda ishlatilgan asboblar (lab_instruments.id): shu sinov turiga bog'langan, ishlayotgan va sertifikati amalda
            // bo'lishi LabTestService da tekshiriladi
            'instrument_ids' => 'nullable|array',
            'instrument_ids.*' => 'integer|distinct',
            'input_data' => 'required|array',
            'result_data' => 'nullable|array',
        ], $this->methodRules());
    }

    private ?string $methodCode = null;

    /**
     * Sinov turiga xos input_data.* qoidalari - LabTestMethodRules (qoidasi yo'q tur uchun bo'sh).
     */
    protected function methodRules(): array
    {
        return LabTestMethodRules::for($this->methodCode, $this->all())[0];
    }

    public function messages(): array
    {
        return array_merge(LabTestMethodRules::for($this->methodCode, $this->all())[1], [
            'test_date.before_or_equal' => "Sinov sanasi kelajakda bo'lishi mumkin emas",
            'result.in' => "Natija noto'g'ri: 1 - ijobiy, 2 - salbiy",
            'lab_contract_item_id.required' => 'Shartnomadagi ish tanlanishi shart',
            'lab_contract_item_id.exists' => 'Shartnomadagi ish topilmadi',
            'lab_contract_item_id.prohibited' => "Shartnomadagi ishni o'zgartirib bo'lmaydi",
            'floor.required' => 'Qavat kiritilishi shart',
            'floor.in' => "Bu qavat uchun shartnomada ish yo'q",
            'floor.prohibited' => "Bu ish turida qavat ko'rsatilmaydi",
            'quantity.required' => 'Sinov qoplaydigan miqdor kiritilishi shart',
            'quantity.min' => "Miqdor kamida 1 bo'lishi kerak",
            'indicator_ids.required' => "Kamida bitta sinov (ko'rsatkich) tanlanishi shart",
            'indicator_ids.*.in' => 'Tanlangan sinov shartnomada yo\'q',
            'input_data.required' => "Sinov ma'lumotlari kiritilishi shart",
        ]);
    }
}
