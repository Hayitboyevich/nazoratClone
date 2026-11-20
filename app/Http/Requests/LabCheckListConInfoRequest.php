<?php

namespace App\Http\Requests;

use App\Models\LabCheckListTestingQuestionMark;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class LabCheckListConInfoRequest extends FormRequest
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
            'type' => ['required', Rule::in([1, 2])],
            'main_info_id' => [Rule::requiredIf(fn() => request('type') == 1), 'integer', 'exists:lab_check_list_testing_main_infos,id'],
            'questions' => [Rule::requiredIf(fn() => request('type') == 1), 'array'],
            'questions.*.name' => [Rule::requiredIf(fn() => request('type') == 1), 'string'],
            'questions.*.con_marks' => [Rule::requiredIf(fn() => request('type') == 1), 'array', 'size:7'],
            'questions.*.con_marks.*.mark' => [Rule::requiredIf(fn() => request('type') == 1), 'string'],
            'questions.*.con_marks.*.mark_number' => [Rule::requiredIf(fn() => request('type') == 1), 'integer', 'min:1', 'max:7'],
            'mark_indicators' => [Rule::requiredIf(fn() => request('type') == 2), 'array', 'size:60'],
            'lab_check_list_testing_question_id' => [Rule::requiredIf(fn() => request('type') == 2), 'exists:lab_check_list_testing_questions,id'],
            'mark_indicators.*.mark_id' => [Rule::requiredIf(fn() => request('type') == 2), 'integer', 'exists:lab_check_list_testing_question_marks,id'],
            'mark_indicators.*.mark' => [Rule::requiredIf(fn() => request('type') == 2), 'numeric'],
            'mark_indicators.*.mark_number' => [Rule::requiredIf(fn() => request('type') == 2), 'integer', 'min:1', 'max:10']
        ];
    }

    public function withValidator(Validator $validator)
    {
        $validator->after(function ($validator) {

            if ($this->input('type') != 2 || !$this->filled('mark_indicators')) {
                return;
            }

            $mark_ids = collect($this->input('mark_indicators'))->pluck('mark_id');

            $counts = LabCheckListTestingQuestionMark::query()
                ->whereIn('mark_id', $mark_ids)
                ->where('type', $this->input('type'))
                ->selectRaw('mark_id, count(*) as total')
                ->groupBy('mark_id')
                ->pluck('total', 'mark_id');

            foreach ($mark_ids as $mark_id) {
                if (($counts[$mark_id] ?? 0) >= 10) {
                    $validator->errors()->add(
                        'mark_indicators',
                        "Для mark_id {$mark_id} уже добавлено 10 индикаторов"
                    );
                }
            }

        });
    }
}


