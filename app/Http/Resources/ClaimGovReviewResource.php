<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClaimGovReviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'answer' => $this->answer,
            'organization_id' => $this->organization_id,
            'status' => $this->status,
            'deadline' => $this->deadline,
            'answered_at' => $this->answered_at,
            'created_at' => $this->created_at,
        ];
    }
}
