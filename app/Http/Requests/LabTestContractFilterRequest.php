<?php

namespace App\Http\Requests;

use App\Enums\LabTestContractStatusEnum;
use App\Enums\LabTestTypeEnum;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Arizalar ro'yxati filtrlari.
 */
class LabTestContractFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'nullable|integer|in:' . implode(',', LabTestContractStatusEnum::values()),
            'test_type' => 'nullable|integer|in:' . implode(',', LabTestTypeEnum::values()),
            // waiting - joriy foydalanuvchi qarorini kutayotganlar, returned - laborantga qaytarilganlar
            'filter' => 'nullable|string|in:waiting,returned',
            'employee_id' => 'nullable|integer',
            'region_id' => 'nullable|integer',
            'object_id' => 'nullable|integer',
            // asosiy shartnomaning qo'shimcha shartnomalari
            'parent_id' => 'nullable|integer',
            'search' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|between:1,100',
        ];
    }
}
