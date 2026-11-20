<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeclarationAcceptRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }


    public function rules(): array
    {
        return [
            'guid' => 'required',
            'uuid' => 'required',
            'vendor_comment' => 'required',
            'user' => 'sometimes',
            'is_yuridik' => 'sometimes',
        ];
    }
}
