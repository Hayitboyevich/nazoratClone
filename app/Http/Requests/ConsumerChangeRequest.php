<?php

namespace App\Http\Requests;

use App\Enums\UserRoleEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ConsumerChangeRequest extends FormRequest
{

    public function authorize(): bool
    {
        $user = Auth::user();
        return $user->getRoleFromToken() == UserRoleEnum::ADMIN->value;
    }


    public function rules(): array
    {
        return [
            'pkcs7' => 'required',
            'object_id' => 'required',
            'inn' => 'required',
            'declaration_id' => 'required',
            'organization_name' => 'required',
            'comment' => 'required',
            'files' => 'required',
        ];
    }
}
