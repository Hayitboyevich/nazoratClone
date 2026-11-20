<?php

namespace App\Http\Requests\ClaimRequests;

use Illuminate\Foundation\Http\FormRequest;

class SendToDirector extends FormRequest
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
            'id' => 'required',
            'buildings' => 'required|array|min:1',
            'buildings.*.name' => 'sometimes',
            'buildings.*.cadaster' => 'sometimes',
            'buildings.*.building_number' => 'sometimes|numeric|min:1',
            'buildings.*.total_area' => 'sometimes',
            'buildings.*.total_use_area' => 'sometimes',
            'buildings.*.living_area' => 'sometimes',
            'buildings.*.area' => 'sometimes',
            'buildings.*.type' => 'required'
        ];
    }
}
