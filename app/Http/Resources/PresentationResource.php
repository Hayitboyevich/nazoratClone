<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PresentationResource extends JsonResource
{

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'object' => [
                'task_id' => $this->object->task_id ?? $this->object->manual_task_id,
                'name' => $this->object->name
            ],
            'region' => $this->object->region ? RegionResource::make($this->object->region) : null,
            'district' => $this->object->district ? DistrictResource::make($this->object->district) : null,
            'material_id' => $this->material_id,
            'status' => $this->status,
            'number' => $this->number,
            'description' => $this->description,
            'producer_tin' => $this->producer_tin,
            'producer_name' => $this->producer_name,
            'certifier_tin' => $this->certifier_tin,
            'certifier_name' => $this->certifier_name,
            'certifier_number' => $this->certifier_number,
            'defect' => $this->defect,
            'quantity' => $this->quantity,
            'used' => $this->used,
            'internal_answer' => $this->internal_answer,
            'technic_answer' => $this->technic_answer,
            'material_name' => $this->material_name,
            'csr_code' => $this->csr_code,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'unit' => $this->unit,
            'gost' => $this->gost,
            'mxik' => $this->mxik,
            'producer_date' => $this->date,
            'gost_status' => $this->gost_status,
            'gost_doc' => $this->gost_doc,
            'gost_doc_id' => $this->gost_doc_id,
            'conclusion_at' => $this->conclusion_at,
            'images' => ImageResource::collection($this->images),
            'certificate_files' => collect(json_decode($this->certificate_files))->map(function ($file) {
                return [
                    'url' => url('storage/'.$file),
                ];
            }),
            'presentation_files' => collect(json_decode($this->presentation_files))->map(function ($file) {
                return [
                    'url' => url('storage/'.$file),
                ];
            }),
            'report_files' => collect(json_decode($this->report_files))->map(function ($file) {
                return [
                    'url' => url('storage/'.$file),
                ];
            })
        ];
    }
}
