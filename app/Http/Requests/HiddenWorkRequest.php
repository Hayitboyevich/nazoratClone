<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HiddenWorkRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'object_id' => ['required', 'integer', 'exists:articles,id'],
            'block_id' => ['required', 'integer', 'exists:blocks,id'],

            'organization_id' => ['required', 'integer'],
            'vendor_id' => ['required', 'integer'],
            'designer_id' => ['required', 'integer'],

            'address' => ['required', 'string'],
            'presentation_date' => ['required', 'date'],

            'internal_id' => ['required', 'integer'],
            'technic_id' => ['required', 'integer'],
            'author_id' => ['required', 'integer'],

            'desc' => ['required', 'string'],
            'project_name' => ['required', 'string'],
            'drawing_number' => ['required', 'string'],
            'drawing_date' => ['required', 'date'],

            'materials' => ['required', 'string'],
//            'certificate_info' => ['required', 'string'],

            'has_project_deviation' => ['required', 'boolean'],

            'project_drawing_number' => ['required', 'string'],
            'project_drawing_date' => ['required', 'date'],

            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],

            'comment' => ['required', 'string'],
            'post_permit_works' => ['required', 'string'],
            'status' => ['required', 'integer'],
            'work_type_id' => ['sometimes'],
            'floor' => ['sometimes'],
            'author_answer' => ['sometimes'],
            'technic_answer' => ['sometimes'],
            'images' => ['sometimes', 'array'],

        ];
    }

    public function prepareForValidation()
    {
        $this->merge([
            'status' => 1,
            'author_answer' => null,
            'technic_answer' => null,
            'has_project_deviation' => filter_var(
                $this->has_project_deviation,
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            ),
        ]);
    }
}
