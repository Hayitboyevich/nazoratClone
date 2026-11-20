<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LabUserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->user->id,
            'fish' => $this->user->full_name,
            'name' => $this->user->name,
            'surname' => $this->user->surname,
            'middle_name' => $this->user->middle_name,
            'organization_name' => $this->user->organization_name,
            'pinfl' => $this->user->pinfl,
            'phone' => $this->user->phone,
            'role' => [
                'id' => $this->role->id,
                'name' => $this->role->name
            ]
        ];
    }
}
