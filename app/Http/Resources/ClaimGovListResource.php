<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClaimGovListResource extends JsonResource
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
            'task_id' => $this->task_id,
            'object' => $this->object ? [
                'id' => $this->object_id,
                'name' => $this->object->name,
                'task_id' => $this->object->task_id ?? $this->object->manual_task_id,
                'gnk_id' => $this->object->gnk_id,
                'lat' => $this->object->lat,
                'long' => $this->object->long,
                'object_type' => ObjectTypeResource::make($this->object->objectType)
            ] : null,
            'region' => $this?->region?->name_uz,
            'district' =>  $this?->district?->name_uz,
            'address' => $this->address,
            'status' => $this->status,
            'legal_tin' => $this->legal_tin,
            'legal_name' => $this->legal_name,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}
