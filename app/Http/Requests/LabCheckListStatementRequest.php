<?php

namespace App\Http\Requests;

use App\Enums\LabCheckListTestingStatusEnum;
use App\Exceptions\GeneralJsonException;
use App\Models\LabCheckListChosenTestingType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LabCheckListStatementRequest extends FormRequest
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
            ->where('actions_status', LabCheckListTestingStatusEnum::BOTH_SCHEME_CREATED->value)
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
            'question_type' => ['string',Rule::in(['В', 'Г'])],
            'testing_object_name' => "required|string|in:Quyma temir beton kostruksiyalar",
            'method_concrete_strength' => "required|string|in:ГОСТ 18105-2018 Бетоны. Правила контроля и оценки прочности. п. 8.4.4 Схема В",
            'document_number' => 'required|string',
            'testing_object_condition' => 'required|string',
            'leaving_from_testing_method' => 'required|string',
            'others_testing_work' => 'required|string',
            'lab_check_list_chosen_testing_type_id' => 'required|integer|exists:lab_check_list_chosen_testing_types,id',
        ];
    }

    protected function failedAuthorization()
    {
        throw new GeneralJsonException(message: 'You can not edit this statement', code: 403);
    }
}
