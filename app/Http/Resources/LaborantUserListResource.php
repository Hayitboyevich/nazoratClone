<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LaborantUserListResource extends JsonResource
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
            'fish' => "$this->surname $this->name $this->middle_name",
            'region' => RegionResource::make($this->region),
            'role' => RoleResource::collection($this->roles),
            'files' => collect($this->files ?? [])
                ->map(fn ($file) => config('app.url') . '/storage/' . ltrim($file, '/'))
                ->toArray()
        ];
    }
}
