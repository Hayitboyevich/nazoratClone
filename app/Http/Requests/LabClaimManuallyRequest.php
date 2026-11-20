<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LabClaimManuallyRequest extends FormRequest
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
            'lab_claim_info' => 'required|array',
            'lab_claim_info.object_name' => 'required',
            'lab_claim_info.construction_works' => 'required',
            'lab_claim_info.sphere_id' => 'required',
            'lab_claim_info.lab_test_category_id' => [
                'required',
                'exists:lab_test_categories,id',
            ],
            'lab_claim_info.object_type_id' => 'required|exists:object_types,id',
            'lab_claim_info.phone' => 'required|string|max:20',
            'lab_claim_info.cad_num' => [Rule::requiredIf(function () {
                return request('object_type_id') == 2;
            }), 'string'],
//            'lab_claim_info.lab_testing_org_id' => 'required|exists:lab_testing_orgs,id',
            'lab_claim_info.region_id' => 'required|exists:regions,id',
            'lab_claim_info.district_id' => 'required|exists:districts,id',
            'lab_claim_info.address' => 'required|string',
            'lab_claim_info.project_estimate_file_path' => ['required', 'array'],
            'lab_claim_info.project_estimate_file_path.*' => ['file','mimes:pdf,zip,jpeg,png,jpg,svg'],
            'laborant_id' => 'required|exists:users,id',
            'lab_customer_info' => 'required|array',
            'lab_customer_info.user_id' => 'sometimes|exists:users,id',
            'lab_customer_info.pinfl' => ['sometimes', 'string'],
            'lab_customer_info.organization_name' => ['sometimes', 'string'],
            'lab_customer_info.name' => ['sometimes', 'string'],
            'lab_customer_info.surname' => ['sometimes', 'string'],
            'lab_customer_info.middle_name' => ['sometimes', 'string'],
            'lab_customer_info.phone' => ['sometimes', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'lab_claim_info.object_type_id.required' => 'Obyekt turi majburiy',
            'lab_claim_info.object_name.required' => 'Obyekt nomi majburiy',
            'lab_claim_info.construction_works.required' => 'Qurilish turi majburiy',
            'lab_claim_info.sphere_id.required' => 'Qurilish sohasi majburiy',
            'lab_claim_info.lab_test_category_id.required' => 'Labaratoriya turi majburiy',
//            'lab_claim_info.lab_testing_org_id.required' => 'Labaratoriya o\'tkazuvchi tashkilot amjburiy',
            'lab_claim_info.region_id.required' => 'Viloyat majburiy',
            'lab_claim_info.district_id.required' => 'Tuman majburiy',
            'lab_claim_info.address.required' => 'Manzil majburiy',
            'lab_claim_info.cad_num.required' => 'Agarda obyekt turi \'Bino\' bo\'lsa, kadastr raqami maydoni to\'ldirlishi shart!',
            'lab_customer_info.user_id.exists' => 'Foydalanuvchi topilmadi',
            'lab_customer_info.pinfl.required' => 'pinfl majburiy',
            'lab_customer_info.organization_name.required' => 'organization_name majburiy',
            'lab_customer_info.name.required' => 'name majburiy',
            'lab_customer_info.surname.required' => 'surname majburiy',
            'lab_customer_info.middle_name.required' => 'middle_name majburiy',
            'lab_customer_info.phone.required' => 'phone majburiy',
        ];
    }
}
