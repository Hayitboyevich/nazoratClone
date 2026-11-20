<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TechnicalStaffResource extends JsonResource
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
            'pin' => $this->pin,
            'birth_date' => $this->birth_date,
            'name' => $this->name,
            'middle_name' => $this->middle_name,
            'surname' => $this->surname,
            'status' => $this->status,
            'start_info' => $this->start_info,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'created_at' => $this->created_at
        ];
    }
}
