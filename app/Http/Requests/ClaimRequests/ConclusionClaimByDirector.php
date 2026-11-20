<?php

namespace App\Http\Requests\ClaimRequests;

use Illuminate\Foundation\Http\FormRequest;

class ConclusionClaimByDirector extends FormRequest
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
            'id' => 'required',
            'comment' => 'required',
            'type' => 'required',
            'guid' => 'required',
            'uuid' => 'required',
            'file' => 'required_if:type,100|file'
        ];
    }
}
