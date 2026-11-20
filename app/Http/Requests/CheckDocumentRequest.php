<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckDocumentRequest extends FormRequest
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
            'id' => 'sometimes',
            'redirect_url' => 'required',
            'title' => 'required',
            'body' => 'required',
            'table' => 'required',
            'test' => 'sometimes',
        ];
    }
}
