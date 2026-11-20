<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConcreteWorkUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }


    public function rules(): array
    {
        return [
            'id' => 'required|exists:concrete_works,id',
            'files' => 'required|array',
            'lab_status' => 'required',
        ];
    }
}
