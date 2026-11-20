<?php

namespace App\Http\Requests;

use App\Models\LabGradirovkaMainInfo;
use Illuminate\Foundation\Http\FormRequest;

class LabGradirofkaRequest extends FormRequest
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
            'lab_contract_id' => 'required|exists:lab_contracts,id',
            'work_type_id' => 'required|exists:work_types,id',
            'question_id' => 'required|exists:questions,id',
            'test_scheme_name' => 'required|string',
            'scheme_info' => 'required|array',
            'scheme_info.*.gradirovka_type_name' => 'required|string',
            'scheme_info.*.construction_name_sh' => 'sometimes|string',
            'scheme_info.*.place_sh' => 'sometimes|string',
            'scheme_info.*.mark_sh' => 'sometimes|integer',
            'scheme_info.*.concrete_year_sh' => 'sometimes|integer',
            'scheme_info.*.rate_sh' => 'sometimes|numeric',
            'scheme_info.*.construction_name_o' => 'sometimes|string',
            'scheme_info.*.place_o' => 'sometimes|string',
            'scheme_info.*.mark_o' => 'sometimes|numeric',
            'scheme_info.*.coefficient_o' => 'sometimes|numeric',
            'scheme_info.*.formula_o' => 'sometimes|numeric',
            'coefficient' => 'required|array',
            'coefficient.coefficient_a' => 'sometimes|numeric',
            'coefficient.coefficient_b' => 'sometimes|numeric',
            'coefficient.e_1' => 'sometimes|numeric',
            'coefficient.e_2' => 'sometimes|numeric',
            'coefficient.e_3' => 'sometimes|numeric',
            'coefficient.e_4' => 'sometimes|numeric',
            'coefficient.e_5' => 'sometimes|numeric',
            'coefficient.e_6' => 'sometimes|numeric',
            'coefficient.e_7' => 'sometimes|numeric',
            'coefficient.e_8' => 'sometimes|numeric',
            'coefficient.e_9' => 'sometimes|numeric',
            'coefficient.g_koef' => 'sometimes|numeric',
            'coefficient.g_shmidt' => 'sometimes|numeric',
            'coefficient.g_onix' => 'sometimes|numeric',
            'coefficient.g_ratio' => 'sometimes|numeric',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $exists = LabGradirovkaMainInfo::query()
                ->where('lab_contract_id', $this->lab_contract_id)
                ->where('status', 0)
                ->where('question_id', $this->question_id)
                ->where('work_type_id', $this->work_type_id)
                ->where('object_id', $this->object_id)
                ->where('block_id', $this->block_id)
                ->exists();

            if ($exists) {
                $validator->errors()->add(
                    'question_id',
                    'Ushbu ma\'lumot bo‘yicha yakunlanmagan gradirovka allaqachon mavjud.'
                );
            }
        });
    }
}
