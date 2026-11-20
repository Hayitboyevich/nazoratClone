<?php

namespace App\Http\Requests;

use App\Enums\UserRoleEnum;
use App\Exceptions\GeneralJsonException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ObjectRequest extends FormRequest
{

    public function authorize(): bool
    {
        $user = Auth::user();
        return $user->getRoleFromToken() == UserRoleEnum::INSPEKSIYA->value;
    }


    public function rules(): array
    {
        return [
            'response_id' => 'required|integer|exists:dxa_responses,id',
            'uuid' => 'required',
            'guid' => 'required',
        ];
    }


    public function failedValidation(Validator $validator)
    {
        throw new GeneralJsonException($validator->errors(), 422);
    }


}
