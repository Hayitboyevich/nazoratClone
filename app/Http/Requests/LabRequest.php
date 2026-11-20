<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class LabRequest extends FormRequest
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
        $user = Auth::user();
        return [
            'object_name' => 'required',
            'construction_works' => 'required',
            'sphere_id' => 'required',
            'lab_test_category_id' => [
                'required',
                'exists:lab_test_categories,id',
            ],
            'lab_testing_org_id' => [
                'required',
                'exists:lab_testing_orgs,id',
            ],
            'object_type_id' => 'required|exists:object_types,id',
            'phone' => 'required|string',
            'reestr_num' => 'nullable|integer',
            'cad_num' => Rule::requiredIf(function () {
                return request('object_type_id') == 2;
            }),
            'region_id' => 'required',
            'district_id' => 'required',
            'address' => 'required',
            'project_estimate_file_path' => [Rule::requiredIf(function () {
                return  empty(request('reestr_num'));
            }), 'array'],
            'project_estimate_file_path.*' => [Rule::requiredIf(function () {
                return  empty(request('reestr_num'));
            }), 'file','mimes:pdf,zip,jpeg,png,jpg,svg'],
            'director_full_name' => Rule::requiredIf(fn() =>
                $user && strlen($user->pinfl) === 9
            )
        ];
    }

    public function messages(): array
    {
        return [
            'object_type_id.required' => 'Obyekt turi majburiy',
            'object_name.required' => 'Obyekt nomi majburiy',
            'construction_works.required' => 'Qurilish turi majburiy',
            'sphere_id.required' => 'Qurilish sohasi majburiy',
            'lab_test_category_id.required' => 'Labaratoriya turi majburiy',
            'lab_testing_org_id.required' => 'Labaratoriya o\'tkazuvchi tashkilot amjburiy',
            'region_id.required' => 'Viloyat majburiy',
            'district_id.required' => 'Tuman majburiy',
            'address.required' => 'Manzil majburiy',
            'cad_num.required' => 'Agarda obyekt turi \'Bino\' bo\'lsa, kadastr raqami maydoni to\'ldirlishi shart!'
        ];
    }
}
