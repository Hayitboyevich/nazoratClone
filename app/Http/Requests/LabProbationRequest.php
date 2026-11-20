<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LabProbationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'block_id' => 'nullable|integer|exists:blocks,id',
            'object_id' => 'nullable|integer|exists:articles,id',
            'regular_checklist' => 'required|array',
            'regular_checklist.*.checklist_id' => 'nullable|integer|exists:check_list_answers,id',
            'regular_checklist.*.description' => 'nullable|string',
            'regular_checklist.*.type' => 'nullable|integer',
            'regular_checklist.*.question_id' => 'required|integer|exists:questions,id',
            'regular_checklist.*.floor' => 'nullable|integer',
            'regular_checklist.*.work_type_id' => 'nullable',
        ];
    }
}
