<?php

namespace App\Http\Requests\ClaimRequests;

use App\Enums\UserRoleEnum;
use App\Models\ClaimOrganizationReview;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConclusionOrganization extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'review_id' => 'required',
            'comment' => 'required',
            'answer_type' => 'required',
            'uuid' => 'required',
            'guid' => 'required',
            'images' => [
                Rule::requiredIf(function () {
                    $review = ClaimOrganizationReview::query()->find($this->review_id);

                    return $review?->organization_id === UserRoleEnum::NOGIRONLAR_JAM->value;
                }),
                'array',
            ],
        ];
    }
}
