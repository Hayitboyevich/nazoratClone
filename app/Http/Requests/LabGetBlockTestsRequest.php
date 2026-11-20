<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LabGetBlockTestsRequest extends FormRequest
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
        $dependent_fields = ['article_id', 'block_id', 'work_type_id', 'question_id'];
        return [
            'article_id' => $this->dependentFieldRule('articles', $dependent_fields),
            'block_id' => $this->dependentFieldRule('blocks', $dependent_fields),
            'work_type_id' => $this->dependentFieldRule('work_types', $dependent_fields),
            'question_id' => $this->dependentFieldRule('questions', $dependent_fields),
            'main_info_id' => ['integer',
                'exists:lab_check_list_testing_main_infos,id',
                Rule::requiredIf(fn () =>
                    empty(request('article_id'))
                    and empty(request('block_id'))
                    and empty(request('work_type_id'))
                    and empty(request('question_id'))
                )],
        ];
    }

    protected function dependentFieldRule(string $table, array $dependent_fields): array
    {
        return [
            'integer',
            "exists:{$table},id",
            Rule::requiredIf(fn() => empty(request()->input('main_info_id'))),
            Rule::prohibitedIf(fn() => !empty(request()->input('main_info_id'))),
        ];
    }
}
