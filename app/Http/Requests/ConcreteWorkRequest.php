<?php

namespace App\Http\Requests;

use App\Enums\UserRoleEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ConcreteWorkRequest extends FormRequest
{

    public function authorize(): bool
    {
        $user = Auth::user();
        return $user->getRoleFromToken() == UserRoleEnum::ICHKI->value;
    }

    public function rules(): array
    {
        return [
            'object_id' => 'required|integer|exists:articles,id',
            'user_id' => 'required|integer|exists:users,id',
            'block_id' => 'required|integer|exists:blocks,id',
            'date' => 'required|date',
            'coordinate' => 'required|string',
            'concrete_grade' => 'required|string',
            'project_concrete_grade' => 'required|string',
            'temperature' => 'required|string',
            'size' => 'required|string',
            'outside_temperature' => 'required|string',
            'mark' => 'required|string',
            'result' => 'sometimes',
            'images' => 'sometimes',
            'work_type_id' => 'sometimes',
            'floor' => 'sometimes',
        ];
    }

    public function prepareForValidation()
    {
        return $this->merge([
            'user_id' => Auth::id(),
        ]);
    }
}
