<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QualityControlResource extends JsonResource
{

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'object_id' => $this->object_id,
            'block_id' => $this->block_id,
            'received_date' => $this->received_date,
            'inn' => $this->inn,
            'organization_name' => $this->organization_name,
            'contract_number' => $this->contract_number,
            'quantity' => $this->quantity,
            'non_compliant_quantity' => $this->non_compliant_quantity,
            'comment' => $this->comment,
            'block_name' => $this->block->name,
            'corrective_action' => $this->corrective_action,
            'material' => $this->material ? [
                'id' => $this->material_id,
                'name' => $this->material->name,
                'unit' => $this->material->unit
            ] : null,
            'used' => $this->used,
            'desc' => $this->desc,
            'images' => ImageResource::collection($this->images),
            'docs' => DocumentResource::collection($this->documents),
            'contract_images' => collect(json_decode($this->contract_images))->map(function ($file) {
                return [
                    'url' => url('storage/'.$file),
                ];
            }),
            'lab_files' => collect(json_decode($this->lab_files))->map(function ($file) {
                return [
                    'url' => url('storage/'.$file),
                ];
            }),

        ];
    }
}
