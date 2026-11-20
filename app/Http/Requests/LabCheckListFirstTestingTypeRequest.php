<?php

namespace App\Http\Requests;

use App\Enums\LabCheckListTestingStatusEnum;
use App\Models\LabCheckListChosenTestingType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class LabCheckListFirstTestingTypeRequest extends FormRequest
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
            'question_type' => [Rule::prohibitedIf(fn() => $this->isMethod('get')), Rule::requiredIf(fn() => $this->isMethod('post')), Rule::in(['В', 'Г'])],
            'question_id' => ['sometimes', 'exists:questions,id'],
            'floor' => 'nullable',
            'article_id' => 'required|exists:articles,id',
            'block_id' => 'required|exists:blocks,id',
            'work_type_id' => 'required|exists:work_types,id',
            'questions' => 'sometimes|array|size:10',
            'questions.*.question_id' => [Rule::prohibitedIf(fn() => $this->isMethod('get')),'sometimes','integer', 'exists:questions,id']
        ];
    }

    public function withValidator(Validator $validator)
    {
        $validator->after(function ($validator) {
            if ($this->isMethod('post')) {
                $query = LabCheckListChosenTestingType::query()
                    ->where(['article_id' => $this->input('article_id'), 'block_id' => $this->input('block_id'),
                        'work_type_id' => $this->input('work_type_id'), 'actions_status' => LabCheckListTestingStatusEnum::INITIAL_STATUS->value]);
                if ($this->filled('floor')) {
                    $query->where('floor', $this->input('floor'));
                }
                $count = $query->count();
                if ($count > 0) {
                    $validator->errors()->add('general', 'This work type has in proccess statements!');
                }
            }
        });
    }
}
