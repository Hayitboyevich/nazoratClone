<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TechnicalStaffRequest extends FormRequest
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
            'object_id' => 'required|exists:articles,id',
            'pin' => 'required',
            'birth_date' => 'required',
            'name' => 'required',
            'middle_name' => 'required',
            'surname' => 'required',
            'start_date' => 'required',
            'end_date' => 'required',
            'start_info' => 'required',
            'status' => 'required',
        ];
    }

}
