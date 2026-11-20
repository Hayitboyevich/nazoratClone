<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LabCheckListTestingFilterRequest extends FormRequest
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
            'check_condition_status' => 'sometimes|bool',
            'lab_check_list_chosen_testing_type_id' => 'sometimes|exists:lab_check_list_chosen_testing_types,id',
            'article_id' => 'sometimes|exists:articles,id',
            'block_id' => 'sometimes|exists:blocks,id',
            'work_type_id' => 'sometimes|exists:work_types,id',
            'statement_id' => 'sometimes|exists:lab_check_list_testing_statements,id',
            'floor' => 'sometimes|string'
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {

            $fields = [
                'floor',
                'article_id',
                'block_id',
                'work_type_id',
                'check_condition_status',
                'statement_id'
            ];

            $hasAtLeastOne = collect($fields)
                ->contains(fn ($field) => $this->filled($field));

            if (! $hasAtLeastOne) {
                $validator->errors()->add(
                    'filters',
                    'At least one filter parameter is required.'
                );
            }
        });
    }
}
