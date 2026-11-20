<?php

namespace App\Http\Requests;

use App\Enums\LabCheckListTestingStatusEnum;
use App\Exceptions\GeneralJsonException;
use App\Models\LabCheckListChosenTestingType;
use Illuminate\Foundation\Http\FormRequest;

class LabCheckListStatementMarkRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        if (!$this->filled('lab_check_list_chosen_testing_type_id')) {
            return true;
        }
        return LabCheckListChosenTestingType::where('id', $this->lab_check_list_chosen_testing_type_id)
            ->where('actions_status', LabCheckListTestingStatusEnum::STATEMENT_FIRST_CHAPTER_CREATED->value)
            ->exists();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'lab_check_list_statement_id' => 'required|integer|exists:lab_check_list_testing_statements,id',
            'lab_check_list_chosen_testing_type_id' => 'required|integer|exists:lab_check_list_chosen_testing_types,id',
            'statement_marks' => 'required|array',
            'concrete_norm' => 'required|numeric|between:-999999999.99,999999999.99',
            'statement_marks.*.first_mark' => 'required|numeric|between:-999999999.99,999999999.99',
            'statement_marks.*.lab_check_list_testing_main_info_id' => 'required|integer|exists:lab_check_list_testing_main_infos,id',
            'statement_marks.*.second_mark' => 'required|numeric|between:-999999999.99,999999999.99',
            'statement_marks.*.third_mark' => 'required|numeric|between:-999999999.99,999999999.99',
            'statement_marks.*.fourth_mark' => 'required|numeric|between:-999999999.99,999999999.99',
            'statement_marks.*.fifth_mark' => 'required|numeric|between:-999999999.99,999999999.99',
            'statement_marks.*.sixth_mark' => 'required|numeric|between:-999999999.99,999999999.99',
            'statement_marks.*.average_mark' => 'required|numeric|between:-999999999.99,999999999.99',
            'statement_marks.*.first_num' => 'required|numeric|between:-999999999.99,999999999.99',
            'statement_marks.*.second_num' => 'required|numeric|between:-999999999.99,999999999.99',
            'statement_marks.*.decision' => 'required|boolean',
            'statement_marks.*.concrete_sustainability' => 'required|numeric|between:-999999999.99,999999999.99',
        ];
    }

    protected function failedAuthorization()
    {
        throw new GeneralJsonException(message: 'You can not edit this statement', code: 403);
    }
}
