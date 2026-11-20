<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FoundationBlockRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }


    public function rules(): array
    {
        return [
            'object_id' => 'required|exists:articles,id',
            'block_id' => 'required|exists:blocks,id',
            'foundation_depth' => 'sometimes',
            'foundation_slope' => 'sometimes',
            'file' => 'sometimes',
            'act_file' => 'sometimes',
            'geo_file' => 'sometimes',
            'act' => 'sometimes',
            'images' => 'sometimes',
        ];
    }
}
