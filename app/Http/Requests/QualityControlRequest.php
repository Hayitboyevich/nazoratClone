<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class QualityControlRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }


    public function rules(): array
    {
        return [
            'object_id' => 'required|integer|exists:articles,id',
            'block_id' => 'required|integer|exists:blocks,id',
            'user_id' => 'required|integer|exists:users,id',
            'role_id' => 'required|integer|exists:roles,id',
            'received_date' => 'required|date|date_format:Y-m-d',
            'inn' => 'required',
            'organization_name' => 'required',
            'contract_number' => 'required',
            'quantity' => 'required',
            'non_compliant_quantity' => 'sometimes',
            'comment' => 'sometimes',
            'corrective_action' => 'sometimes',
            'images' => 'required|array',
            'docs' => 'required|array',
            'contract_images' => 'required|array',
//            'material_id' => 'required|integer|exists:quality_control_materials,id',
            'material_id' => 'sometimes',
            'desc' => 'sometimes',
        ];
    }

    public function prepareForValidation()
    {
        $user = Auth::user();
        $roleId = $user->getRoleFromToken();
        $this->merge([
            'user_id' => Auth::id(),
            'role_id' => $roleId,
        ]);
    }
}
