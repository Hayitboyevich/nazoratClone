<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LabCheckListMainInfoRequest extends FormRequest
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
            'temperature' => 'required|numeric|between:-999999999.99,999999999.99',
            'relative_humidity' => 'required|string',
            'concrete_temperature' => 'required|numeric|between:-999999999.99,999999999.99',
            'first_test_tool' => 'required|string',
            'second_test_tool' => 'nullable|string',
            'third_test_tool' => 'nullable|string',
            'fourth_test_tool' => 'nullable|string',
            'fifth_test_tool' => 'nullable|string',
            'sixth_test_tool' => 'nullable|string',
            'test_equipment' => 'required|string',
            'auxiliary_equipment' => 'required|string',
            'floor' => 'nullable|integer',
            'article_id' => 'required|integer|exists:articles,id',
            'block_id' => 'required|integer|exists:blocks,id',
            'work_type_id' => 'required|integer|exists:work_types,id',
            'lab_check_list_testing_type_id' => 'required|integer|exists:lab_check_list_testing_types,id',
            'question_type' => ['string',Rule::in(['В', 'Г'])],
            'question_id' => 'required|integer|exists:questions,id',
            'lab_check_list_testing_condition_id' => 'required|integer|exists:lab_check_list_testing_conditions,id'
        ];
    }
}
