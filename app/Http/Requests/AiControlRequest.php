<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AiControlRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }


    public function rules(): array
    {
        return [
            'id' => 'required|integer|exists:ai_controls,id',
            'inspector_commit' => 'required',
            'images' => 'required|array',
            'status' => 'required',
            'send_operator' => 'required',
            'integration' => 'sometimes',
        ];
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'status' => 2,
            'send_operator' => null, // yangi 2 bolsa success  3 xato ketgan
            'integration' => null
        ]);
    }
}
