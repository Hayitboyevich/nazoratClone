<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FoundationResource extends JsonResource
{

    public function toArray(Request $request): array
    {
        return [
            'block_id' => $this->block ? $this->block->id : null,
            'block_name' => $this->block ? $this->block->name : null,
            'foundation' => $this->block ? $this->block->foundation : null,
            'foundation_slope' => $this->foundation_slope,
            'foundation_depth' => $this->foundation_depth,
            'file' => collect(json_decode($this->file))->map(function ($item) {
                return [
                    'url' => url('storage/'.$item),
                ];
            }),
            'act_file' => collect(json_decode($this->act_file))->map(function ($item) {
                return [
                    'url' => url('storage/'.$item),
                ];
            }),
            'geo_file' => collect(json_decode($this->geo_file))->map(function ($item) {
                return [
                    'url' => url('storage/'.$item),
                ];
            }),
            'act' => collect(json_decode($this->act))->map(function ($item) {
                return [
                    'url' => url('storage/'.$item),
                ];
            }),
            'images' => ImageResource::collection($this->images),
            'status' => $this->status,
            'created_at' => $this->created_at,
            'regulations' => $this->monitoring ? $this->monitoring->regulations->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->regulation_number,
                    'status' => RegulationStatusResource::make($item->regulationStatus),
                    'deadline' => $item->deadline,
                    'created_at' => $item->created_at,
                ];
            }) : null,
        ];
    }
}
