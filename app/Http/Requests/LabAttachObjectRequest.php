<?php

namespace App\Http\Requests;

use App\Models\LabClaim;
use Illuminate\Foundation\Http\FormRequest;

class LabAttachObjectRequest extends FormRequest
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
            "article_id" => "required|exists:articles,id",
            "lab_claim_id" => "required|exists:lab_claims,id",
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {

            $lab_claim = LabClaim::find($this->input('lab_claim_id'));

            if (!$lab_claim) {
                return;
            }
            if ($lab_claim->status !=LabClaim::STATUS_COMPLETED) {
                $validator->errors()->add(
                    "lab_claim_id",
                    "Ariza statusi {$lab_claim->status}.Bu arizada bunday amal bajarib bo'lmaydi."
                );
            }
            if ($lab_claim->attached_to_object){
                $validator->errors()->add(
                    "lab_claim_id",
                    "Arizaga obyekt biriktirilgan {$lab_claim->object_id}.Bu arizada bunday amal bajarib bo'lmaydi."
                );
            }
        });
    }
}
