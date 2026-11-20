<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConclusionComplianceRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }


    public function rules(): array
    {
        return [
            'id' => 'required',
            'questions' => 'required|array',
            'status' => 'required',
            'comment' => 'nullable|string',
        ];
    }
}
