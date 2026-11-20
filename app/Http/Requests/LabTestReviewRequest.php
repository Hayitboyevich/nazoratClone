<?php

namespace App\Http\Requests;

use App\Enums\LabTestStatusEnum;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Rahbar qarori: tasdiqlash (4), tahrirga qaytarish (3) yoki rad etish (5).
 */
class LabTestReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $decisions = [
            LabTestStatusEnum::CONFIRMED->value,
            LabTestStatusEnum::RETURNED->value,
            LabTestStatusEnum::REJECTED->value,
        ];

        return [
            'status' => 'required|integer|in:' . implode(',', $decisions),
            // rad etish va qaytarishda sabab majburiy
            'comment' => 'nullable|required_unless:status,' . LabTestStatusEnum::CONFIRMED->value . '|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Qaror tanlanishi shart',
            'status.in' => "Qaror noto'g'ri",
            'comment.required_unless' => 'Sabab kiritilishi shart',
        ];
    }
}
