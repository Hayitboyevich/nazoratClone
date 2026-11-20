<?php

namespace App\Http\Requests;

use App\Models\Declaration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class DeclarationConclusionRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }


    public function rules(): array
    {
        return [
            'object_id' => 'required',
            'declaration_id' => 'required',
            'user_id' => 'required',
            'role_id' => 'required',
            'order_number' => 'required',
            'order_date' => 'required',
            'contract_number' => 'required',
            'contract_date' => 'required',
            'comment' => 'required',
        ];
    }

    public function prepareForValidation()
    {

        $declarationId = Declaration::query()
            ->where('object_id', $this->object_id)
            ->value('id');
        
        return $this->merge([
            'user_id' => Auth::id(),
            'role_id' => Auth::user()->getRoleFromToken(),
            'declaration_id' => $declarationId,
        ]);
    }
}
