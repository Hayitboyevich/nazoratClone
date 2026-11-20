<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LabTestEditRequest extends FormRequest
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
            'name' => 'required|string',
            'id' => 'required|integer|exists:lab_test_categories,id',
            'description' => 'nullable|string',
            'unity' => 'nullable|string',
            'amount' => 'nullable|integer',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => '“Nomi” maydoni to‘ldirilishi shart.',
            'parent_id.integer' => '“Ota kategoriya” son bo‘lishi kerak.',
            'description.string' => '“Me\'yoriy xujjat” matn bo‘lishi kerak.',
            'unity.string' => '“O‘lchov birligi”  bo\'lishi kerak.',
            'amount.integer' => '“O‘lchov miqdori” son bo\'lishi kerak.',
        ];
    }
}
