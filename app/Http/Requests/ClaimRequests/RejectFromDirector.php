<?php

namespace App\Http\Requests\ClaimRequests;

use Illuminate\Foundation\Http\FormRequest;

class RejectFromDirector extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }


    public function rules(): array
    {
        return [
            'id' => 'required',
            'comment' => 'required',
            'to_role' => 'required|in:12,5',
            'guid' => 'required',
            'uuid' => 'required'
        ];
    }
}
