<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LabCheckListRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'block_id' => 'nullable|integer|exists:blocks,id',
            'object_id' => 'nullable|integer|exists:articles,id',
            'regular_checklist' => 'required|array',
            'regular_checklist.*.files' => 'nullable|array',
            'regular_checklist.*.files.*' => 'nullable|file|mimes:pdf,zip,jpeg,png,jpg,svg',
            'regular_checklist.*.user_status' => 'required|integer',
            'regular_checklist.*.checklist_id' => 'nullable|integer|exists:check_list_answers,id',
            'regular_checklist.*.description' => 'nullable|string',
            'regular_checklist.*.type' => 'nullable|integer',
            'regular_checklist.*.question_id' => 'required|integer|exists:questions,id',
            'regular_checklist.*.floor' => 'nullable|integer',
            'regular_checklist.*.work_type_id' => 'nullable',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (isset($this->regular_checklist)) {
                foreach ($this->regular_checklist as $index => $item) {
                    if (isset($item['user_status']) && $item['user_status'] == 2) {
                        $validator->sometimes(
                            "regular_checklist.$index.type",
                            'required|integer',
                            fn () => true
                        );
                    }
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'regular_checklist.*.type.required' => 'Type is required.',
            'regular_checklist.*.type.integer'  => 'type should be integer.',
        ];
    }
}
