<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActViolationResource extends JsonResource
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
            'title' => $this->regulationViolation ? $this->regulationViolation->violation->title : null,
            'act_status' => ActStatusResource::make($this->actStatus),
            'user' => $this->user->full_name,
            'comment' => $this->comment,
            'type' => $this->act_violation_type_id,
            'images' => ImageResource::collection($this->images),
            'documents' => DocumentResource::collection($this->documents),
            'logs' => RegulationDemandResource::collection($this->demands),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
