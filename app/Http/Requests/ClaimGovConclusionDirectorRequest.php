<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClaimGovConclusionDirectorRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }


    public function rules(): array
    {
        return [
            'id' => 'required|integer|exists:claim_govs,id',
            'status' => 'required|integer|between:5,20,15',
            'comment' => 'sometimes',
            'guid' => 'required',
            'uuid' => 'required',
        ];
    }
}
