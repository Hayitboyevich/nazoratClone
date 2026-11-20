<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PassLabRequest extends FormRequest
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
            'pass_test_info' => 'array|required',
            'pass_test_info.*.construction_name' => 'required',
            'pass_test_info.*.place_name' => 'required',
            'pass_test_info.*.concrete_year' => 'required',
            'pass_test_info.*.index_first' => 'required',
            'pass_test_info.*.index_second' => 'required',
            'pass_test_info.*.index_third' => 'required',
            'pass_test_info.*.index_fourth' => 'required',
            'pass_test_info.*.index_fifth' => 'required',
            'pass_test_info.*.index_sixth' => 'required'
        ];
    }
}
