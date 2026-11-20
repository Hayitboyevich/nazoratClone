<?php

namespace App\Http\Requests;

use App\Enums\UserRoleEnum;
use Illuminate\Foundation\Http\FormRequest;

class ClaimUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = auth()->user();
        $roleId = $user->getRoleFromToken();
        return $roleId == UserRoleEnum::OPERATOR->value;
    }

    public function rules(): array
    {
        return [
            'guid' => 'required',
            'building_address' => 'sometimes|nullable|string',
        ];
    }
}
