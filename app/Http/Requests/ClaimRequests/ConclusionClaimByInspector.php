<?php

namespace App\Http\Requests\ClaimRequests;

use Illuminate\Foundation\Http\FormRequest;

class ConclusionClaimByInspector extends FormRequest
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
            'type' => 'required',
            'my_home_id' => 'sometimes',
        ];
    }
}
