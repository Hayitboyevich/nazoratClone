<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConcreteWorkResource extends JsonResource
{

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'object_id' => $this->object_id,
            'block_id' => $this->block_id,
            'block_name' => $this->block->name,
            'date' => $this->date,
            'coordinate' => $this->coordinate,
            'concrete_grade' => $this->concrete_grade,
            'project_concrete_grade' => $this->project_concrete_grade,
            'temperature' => $this->temperature,
            'size' => $this->size,
            'outside_temperature' => $this->outside_temperature,
            'mark' => $this->mark,
            'result' => $this->result,
            'files' => $this->documents ? DocumentResource::collection($this->documents) : null,
            'images' => $this->images ? ImageResource::collection($this->images) : null,
            'lab_status' => $this->lab_status,
            'work_type' =>$this->workType ?  WorkTypeResource::make($this->workType) : null,
            'floor' => $this->floor
        ];
    }
}
