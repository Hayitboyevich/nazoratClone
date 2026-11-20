<?php

namespace App\Http\Requests;

use App\Enums\ObjectStatusEnum;
use App\Enums\UserRoleEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class HolidayCreateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = Auth::user();
        return $user->getRoleFromToken() == UserRoleEnum::ADMIN->value;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'day' => 'required|unique:holidays,day',
            'role_id' => 'required|integer',
            'user_id' => 'required|integer',
            'status' => 'required|boolean',
//            'pkcs7' => 'required',
            'guid' => 'required',
            'uuid' => 'required',
        ];
    }

    public function prepareForValidation()
    {
        $this->merge([
            'status' => true,
            'user_id' => Auth::id()
        ]);
    }
}
