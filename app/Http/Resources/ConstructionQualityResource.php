<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConstructionQualityResource extends JsonResource
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
            'name' => $this->name,
            'result' => $this->result,
            'description' => $this->description,
            'type' => $this->type,
            'date' => $this->date,
            'created_at' => $this->created_at
        ];
    }
}
