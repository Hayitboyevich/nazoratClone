<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ObjectLabratoryRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }


    public function rules(): array
    {
        return [
            'object_id' => 'required|exists:articles,id',
            'laboratory_type' => 'required',
            'laboratory_cost' => 'sometimes',
            'guid' => 'required',
            'uuid' => 'required',
            'comment' => 'required',
        ];
    }
}
