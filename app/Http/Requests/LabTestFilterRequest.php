<?php

namespace App\Http\Requests;

use App\Enums\LabTestStatusEnum;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Sinovlar ro'yxati filtrlari.
 */
class LabTestFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'nullable|integer|in:' . implode(',', LabTestStatusEnum::values()),
            'result' => 'nullable|integer|in:1,2',
            'lab_method_id' => 'nullable|integer',
            'lab_contract_id' => 'nullable|integer',
            'lab_contract_item_id' => 'nullable|integer',
            'lab_contract_block_id' => 'nullable|integer',
            'work_type_id' => 'nullable|integer',
            'floor' => 'nullable|integer',
            'region_id' => 'nullable|integer',
            'search' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|between:1,100',
        ];
    }
}
