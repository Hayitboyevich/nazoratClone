<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class AiControlResource extends JsonResource
{

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'object_id' => $this->object_id,
            'violation_id' => $this->control_id,
            'desc' => $this->desc,
            'image' => Storage::disk('public')->url($this->image),
            'file' => $this->file,
            'video' => $this->video,
            'type' => $this->type,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'inspector_commit' => $this->inspector_commit,
            'images' => $this->images ? ImageResource::collection($this->images) : null,
            'regulations' => $this->regulations ? $this->regulations->map(function ($regulation) {
                return [
                    'id' => $regulation->id,
                    'number' => $regulation->regulation_number,
                    'deadline' => $regulation->deadline,
                    'status' => RegulationStatusResource::make($regulation->regulationStatus),
                ];
            }) : null,
        ];
    }
}
