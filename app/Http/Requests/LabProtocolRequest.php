<?php

namespace App\Http\Requests;

use App\Models\LabGradirovkaMainInfo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LabProtocolRequest extends FormRequest
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
            'scheme' => 'required|string|in:V,G,',
            'avg_vf' => 'required|numeric',
            'design_class' => 'required|string',
            'homogeneity' => 'required|array',
            'homogeneity.n' => 'sometimes|numeric',
            'homogeneity.rm' => 'sometimes|numeric',
            'homogeneity.sm' => 'sometimes|numeric',
            'homogeneity.vm' => 'sometimes|numeric',
            'homogeneity.vr' => 'sometimes|numeric',
            'homogeneity.scheme_allowed' => 'sometimes|bool',
//            'header' => 'required|array',
            'test_place' => 'required|string',
            'deviations' => 'required|string',
            'temperature' => 'required|numeric',
            'humidity' => 'required|numeric',
            'note' => 'required|string',
            'equipment' => 'required|array',
            'equipment.*.name' => 'required|string',
            'equipment.*.serial_number' => 'sometimes|string',
            'equipment.*.certificate_number' => 'sometimes|string',
            'equipment.*.valid_until' => 'sometimes|string',
//            'coefficient' => 'required|array',
//            'coefficient.type' => 'required|string',
//            'coefficient.a' => [Rule::requiredIf(fn () => request('coefficient.type') !== 'privyazka'),'numeric'],
//            'coefficient.b' => [Rule::requiredIf(fn () => request('coefficient.type') !== 'privyazka'),'numeric'],
//            'coefficient.kt' => [Rule::requiredIf(fn () => request('coefficient.type') !== 'privyazka'),'numeric'],
//            'coefficient.kc' => [Rule::requiredIf(fn () => request('coefficient.type') !== 'gradirovka'),'numeric']
            'items' => 'required|array',
            'items.*.construction_name' => 'required|string',
            'items.*.location' => 'required|string',
            'items.*.concrete_age' => 'required|integer',
            'items.*.rm' => 'required|numeric',
            'items.*.vf' => 'required|numeric',
            'items.*.kt_kc' => 'required|numeric',
            'items.*.meets_norm' => 'required|bool',
            'items.*.measurements' => 'required|array|max:6',
            'items.*.measurements.*.place' => 'required|integer',
            'items.*.measurements.*.h' => 'required|numeric',
            'items.*.measurements.*.r' => 'required|numeric',
            'items.*.measurements.*.mark' => 'required|numeric',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $exists = LabGradirovkaMainInfo::query()
                ->where(['id' => $this->route('lab_grad_id'),'status' => 0])
                ->first();
            if (!$exists) {
                $validator->errors()->add(
                    'lab_grad_id',
                    'Bunday sinov topilmadi yoki ushbu sinovda bunday amal bajarib bo\'lmaydi!'
                );
            }
        });
    }
}
