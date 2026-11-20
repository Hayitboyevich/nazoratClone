<?php

namespace App\Http\Requests;

use App\Exceptions\GeneralJsonException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class DxaResponseRegisterRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }


    public function rules(): array
    {
        return [
            'commit' => 'string|required',
            'long' => 'sometimes',
            'lat' => 'sometimes',
            'administrative_status_id' => 'integer|required_if:notification_type,1',
            'task_id' => 'integer|required|exists:dxa_responses,task_id',
            'images.*' => 'image|mimes:jpg,jpeg,png',
//            'documents.*' => 'sometimes',
//            'images.*' => 'required|image|mimes:jpg,jpeg,png',
            'blocks.*' => 'sometimes',
        ];
    }

    public function failedValidation(Validator $validator)
    {
        throw new GeneralJsonException($validator->errors(), 422);
    }
}
