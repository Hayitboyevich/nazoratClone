<?php

namespace App\Http\Requests;

use App\Helpers\DeclarationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class DeclarationUpdateRequest extends FormRequest
{

    public function rules(): array
    {
        return [
            'user_id' => 'required|integer|exists:users,id',
            'fact_type_id' => 'required|integer|exists:fact_types,id',
            'fact_number' => 'required',
            'fact_date' => 'required',
            'construction_price' => 'required',
            'instrument_price' => 'required',
            'fond_construction_price' => 'required',
            'fond_instrument_price' => 'required',
            'note' => 'required',
            'capacity' => 'sometimes',
            'unit_id' => 'sometimes',
            'general_project_info' => 'sometimes',
            'general_project_queue' => 'sometimes',
            'process_project_info' => 'sometimes',
            'process_project_queue' => 'sometimes',
            'product_production' => 'required',
            'solution_desc' => 'required',
            'labor_protection' => 'required',
            'done_work' => 'sometimes',
            'done_note' => 'required',
            'status' => 'required',
            'blocks' => 'sometimes',
            'garant_period' => 'sometimes',
        ];
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'user_id' => Auth::id(),
            'status' => DeclarationStatus::BUILDER
        ]);
    }
}
