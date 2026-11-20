<?php

namespace App\Http\Requests;

use App\Enums\OgohAppealStatusEnum;
use Illuminate\Foundation\Http\FormRequest;

class OgohAppealAnswerRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }


    public function rules(): array
    {
        return [
            'id' => 'required|integer|exists:ogoh_appeals,id',
            'status' => 'required|integer|in:' . implode(',', OgohAppealStatusEnum::finals()),
            'comment' => 'required|string|min:5|max:2000',
            'files' => 'nullable|array|max:10',
            'files.*' => 'file|max:51200',
        ];
    }
}
