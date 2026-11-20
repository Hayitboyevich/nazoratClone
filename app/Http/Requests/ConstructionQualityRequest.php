<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ConstructionQualityRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }


    public function rules(): array
    {
        return [
            'object_id' => 'required|exists:articles,id',
            'description' => 'required_if:type,4|string',
            'type' => 'required|integer',
            'result' => 'required_if:type,3|string',
            'date' => 'required|date',
            'name' => 'required_if:type,3|string',
            'user_id' => 'required|exists:users,id',
        ];
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'user_id' => Auth::id()
        ]);
    }
}
