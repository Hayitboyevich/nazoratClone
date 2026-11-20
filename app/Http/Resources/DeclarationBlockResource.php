<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeclarationBlockResource extends JsonResource
{

    public function toArray(Request $request): array
    {
        return [
            'id' => $this?->block?->id,
            'block_name' => $this?->block?->name,
            'area' => $this->area,
            'floor' => $this->floor,
            'size' => $this->size,
            'under_area' => $this->under_area,
            'real_area' => $this->real_area,
            'real_floor' => $this->real_floor,
            'real_size' => $this->real_size,
            'real_under_area' => $this->real_under_area,
            'is_declaration' => $this?->block?->is_declaration,
            'type' => $this->block ? BlockTypeResource::make($this?->block?->type) : null,
        ];
    }
}
