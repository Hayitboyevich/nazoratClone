<?php

namespace App\Http\Requests;

use App\Enums\LabCertificateDeviationMeaningEnum;
use App\Enums\LabCertificateDocTypeEnum;
use App\Enums\LabCertificateValueFormEnum;
use Illuminate\Foundation\Http\FormRequest;

class LabInstrumentCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isCreate = !$this->route('id');
        $calibration = 'doc_type,' . LabCertificateDocTypeEnum::CALIBRATION->value;
        $verification = 'doc_type,' . LabCertificateDocTypeEnum::VERIFICATION->value;

        return [
            'lab_instrument_id' => ($isCreate ? 'required' : 'prohibited') . '|integer|exists:lab_instruments,id,deleted_at,NULL',
            'doc_type' => 'required|integer|in:' . implode(',', LabCertificateDocTypeEnum::values()),
            'number' => 'required|string|max:100',
            'issued_at' => 'required|date',
            'valid_to' => 'required|date|after:issued_at',
            'value_form' => 'required|integer|in:' . implode(',', LabCertificateValueFormEnum::values()),

            // qiyoslash: faqat ±Δ
            'delta' => "required_if:$verification|prohibited_if:$calibration|nullable|numeric|gt:0",

            // kalibrlash: nuqtalar va tuzatma sozlamalari
            'use_correction' => "prohibited_if:$verification|nullable|boolean",
            'deviation_meaning' => "prohibited_if:$verification|nullable|integer|in:" . implode(',', LabCertificateDeviationMeaningEnum::values()),
            'points' => "required_if:$calibration|prohibited_if:$verification|array|max:100",
            'points.*.x' => 'required|numeric|distinct',
            'points.*.u' => 'required|numeric|gt:0',
            'points.*.d' => 'required|numeric',

            // sertifikat darhol tasdiqlangan holda yaratiladi - fayl majburiy; tahrirlashda yuborilmasa eskisi qoladi
            'file' => ($isCreate ? 'required' : 'nullable') . '|file|mimes:pdf,jpg,jpeg,png|max:20480',
            'comment' => 'nullable|string|max:2000',
            'ignore_warnings' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'number.required' => 'Hujjat raqami kiritilmagan',
            'file.required' => 'Sertifikat fayli biriktirilmagan',
            'delta.required_if' => 'Qiyoslash guvohnomasi uchun ±Δ majburiy',
            'delta.prohibited_if' => 'Kalibrlash sertifikatida ±Δ yuborilmaydi',
            'use_correction.prohibited_if' => 'Qiyoslash guvohnomasida tuzatma sozlamasi yuborilmaydi',
            'deviation_meaning.prohibited_if' => "Qiyoslash guvohnomasida og'ish ma'nosi yuborilmaydi",
            'points.required_if' => 'Kalibrlash sertifikati uchun kalibrlash nuqtalari majburiy',
            'points.prohibited_if' => 'Qiyoslash guvohnomasida kalibrlash nuqtalari yuborilmaydi',
            'points.*.x.required' => 'Nuqta qiymati (x) kiritilmagan',
            'points.*.x.distinct' => 'Nuqtalar takrorlanmasligi kerak',
            'points.*.u.required' => 'Noaniqlik (U) kiritilmagan',
            'points.*.u.gt' => "U musbat bo'lishi kerak",
            'points.*.d.required' => "Og'ish (d) kiritilmagan",
        ];
    }
}
