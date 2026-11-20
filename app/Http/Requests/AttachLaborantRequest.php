<?php

namespace App\Http\Requests;

use App\Enums\UserRoleEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttachLaborantRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'object_id' => 'required|exists:articles,id',
            'laborant_id' => [
                'required',
                'integer',
                Rule::exists('user_roles', 'user_id')->where(function ($q) {
                    $q->where('role_id', UserRoleEnum::LABORANT->value);
                }),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'laborant_id.exists' => 'Tanlangan foydalanuvchi laborant emas.',
            'object_id.exists'   => 'Bunday obyekt topilmadi.',
        ];
    }
}
