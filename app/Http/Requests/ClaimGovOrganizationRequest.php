<?php

namespace App\Http\Requests;

use App\Models\Declaration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ClaimGovOrganizationRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'review_id' => 'required',
            'comment' => 'required',
            'answer_type' => 'required',
            'uuid' => 'required',
            'guid' => 'required',
            'object_id' => 'required',
            'declaration_id' => 'required',
            'order_number' => 'sometimes',
            'order_date' => 'sometimes',
            'contract_number' => 'sometimes',
            'contract_date' => 'sometimes',
        ];
    }


    public function prepareForValidation()
    {

        $declarationId = Declaration::query()
            ->where('object_id', $this->object_id)
            ->value('id');

        return $this->merge([
            'declaration_id' => $declarationId,
        ]);
    }
}
