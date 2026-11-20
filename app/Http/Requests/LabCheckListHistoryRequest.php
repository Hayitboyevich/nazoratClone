<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LabCheckListHistoryRequest extends FormRequest
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
            'object_id' => 'required|exists:articles,id',
            'block_id' => 'required|exists:blocks,id',
            'work_type_id' => 'required|exists:work_types,id',
            'question_id' => 'required|exists:questions,id',
            'floor' => 'nullable'
        ];
    }
}
