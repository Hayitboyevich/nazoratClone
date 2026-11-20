<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Buyurtmachi shartnomani bekor qiladi (sabab ixtiyoriy). Bu amalni qaytarib bo'lmaydi.
 */
class LabTestContractCancelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'comment' => 'nullable|string|max:2000',
        ];
    }
}
