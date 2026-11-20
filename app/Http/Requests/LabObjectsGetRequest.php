<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LabObjectsGetRequest extends FormRequest
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
            'task_id' => 'bail|sometimes',
            'region_id' => 'bail|sometimes|exists:regions,id',
            'district_id' => ['bail', Rule::exists('districts', 'id')->where('region_id', $this->input('region_id'))],
            'per_page' => 'sometimes',
            'page' => 'sometimes',
        ];
    }
}
