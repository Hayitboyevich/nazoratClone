<?php

namespace App\Http\Requests;

use App\Enums\LabInstrumentStatusEnum;
use App\Enums\LabInstrumentTypeEnum;
use App\Enums\LabInstrumentUnitEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LabInstrumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // kod viloyat ichida takrorlanmas
            'code' => ['required', 'string', 'max:100',
                Rule::unique('lab_instruments', 'code')
                    ->ignore($this->route('id'))
                    ->where('region_id', auth('api')->user()?->region_id)
                    ->whereNull('deleted_at')],
            'type' => 'required|integer|in:' . implode(',', LabInstrumentTypeEnum::values()),
            'name' => 'required|string|max:255',
            // asbob ishlatiladigan sinov turlari (lab_methods): sinovda faqat shu turlarga bog'langan asbob tanlanadi
            'lab_method_ids' => 'required|array|min:1',
            'lab_method_ids.*' => ['integer', 'distinct', Rule::exists('lab_methods', 'id')->where('status', true)->whereNull('deleted_at')],
            'serial_number' => 'nullable|string|max:100',
            'unit' => 'required|integer|in:' . implode(',', LabInstrumentUnitEnum::values()),
            // termogigrometr kabi asboblarda diapazon manfiydan boshlanadi (-20 °C)
            'range_from' => 'required|numeric',
            'range_to' => 'required|numeric|gt:range_from',
            'resolution' => 'required|numeric|min:0',
            'status' => 'nullable|integer|in:' . implode(',', LabInstrumentStatusEnum::values()),
            'comment' => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Kod kiritilishi shart',
            'code.unique' => 'Bu kod allaqachon mavjud',
            'name.required' => 'Nomi kiritilishi shart',
            'lab_method_ids.required' => 'Asbob ishlatiladigan sinov turi tanlanishi shart',
            'lab_method_ids.min' => 'Kamida bitta sinov turi tanlanishi shart',
            'lab_method_ids.*.exists' => 'Sinov turi topilmadi',
            'range_to.gt' => "Diapazon oxiri boshidan katta bo'lishi kerak",
        ];
    }
}
