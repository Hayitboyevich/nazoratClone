<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LabTestPriceRequest extends FormRequest
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
            'price' => 'required',
            'id' => 'required|exists:lab_test_categories,id',
            'old_price_id' => 'nullable|exists:lab_test_prices,id',
        ];
    }

    public function messages(): array
    {
        return [
            'price.required' => '“Narx” maydoni to‘ldirilishi shart.',
            'id.required' => '“sinov usuli” maydoni to‘ldirilishi shart.'
        ];
    }
}
