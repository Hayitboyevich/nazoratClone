<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LabCheckListGetQuestionMarksRequest extends FormRequest
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
            'type' => 'required|string|in:1,2',
            'main_info_id' => [Rule::requiredIf(fn() => request('type') == 1), 'string','exists:lab_check_list_testing_main_infos,id'],
            'lab_check_list_testing_question_id' => [Rule::requiredIf(fn() => request('type') == 2), 'string','exists:lab_check_list_testing_questions,id']
        ];
    }
}
