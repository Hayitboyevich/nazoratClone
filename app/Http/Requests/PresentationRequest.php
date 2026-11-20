<?php

namespace App\Http\Requests;

use App\Helpers\PresentationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class PresentationRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }


    public function rules(): array
    {
        return [
            'object_id' => 'required|exists:articles,id',
            'material_id' => 'required',
            'status' => 'required',
            'number' => 'required',
            'description' => 'required',
            'producer_tin' => 'required',
            'producer_name' => 'required',
            'certifier_tin' => 'sometimes',
            'certifier_name' => 'sometimes',
            'certifier_number' => 'sometimes',
            'defect' => 'required',
            'date' => 'required',
            'quantity' => 'required',
            'used' => 'sometimes',
            'images' => 'required|array',
            'certificate_files' => 'required|array',
            'presentation_files' => 'sometimes',
            'report_files' => 'sometimes',
            'material_name' => 'sometimes',
            'csr_code' => 'sometimes',
            'unit' => 'sometimes',
            'gost' => 'sometimes',
            'mxik' => 'sometimes',
            'inspector_id' => 'sometimes',
            'deadline' => 'sometimes',
            'block_id' => 'sometimes',
        ];
    }

    public function prepareForValidation()
    {

        $this->merge([
            'number' => $this->determinateNumber(),
            'status' => PresentationStatus::CONFIRM,
            'inspector_id' => Auth::id(),
        ]);
    }

    private function determinateNumber()
    {
        $presentation = DB::table('presentations')
            ->select('number')
            ->whereRaw("CAST(REGEXP_REPLACE(number, '[^0-9]', '', 'g') AS BIGINT) = (
                    SELECT MAX(CAST(REGEXP_REPLACE(number, '[^0-9]', '', 'g') AS BIGINT))
                    FROM presentations
                    WHERE number ~ '^\\d+$'
                )")
            ->first();
        return $presentation?->number ? (int)$presentation->number + 1 : 399999;
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'status' => false,
            'message' => 'Validation failed',
            'errors'  => $validator->errors()
        ], 422));
    }
}
