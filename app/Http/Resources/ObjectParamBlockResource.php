<?php

namespace App\Http\Resources;

use App\Enums\UserRoleEnum;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ObjectParamBlockResource extends JsonResource
{

    public function toArray(Request $request): array
    {
        $customer = $this->users()->where('role_id', UserRoleEnum::BUYURTMACHI->value)->first();
        $designer = $this->users()->where('role_id', UserRoleEnum::LOYIHA->value)->first();
        $builder = $this->users()->where('role_id', UserRoleEnum::QURILISH->value)->first();
        return [
            'task_id' => $this->task_id ?? $this->manual_task_id,
            'customer_tin'  => $customer ? $customer->pinfl : null,
//            'customer_name'  => $customer ? ($customer->name ? $customer->full_name : $customer->organization_name) : null,
            'object_name' => $this->name,
            'region_soato' => $this->region->soato,
            'region_name' => $this->region->name_uz,
            'district_soato' => $this->district->soato,
            'district_name' => $this->district->name_uz,
            'finish_date' => $this->closed_at,
            'start_date' =>  Carbon::parse($this->created_at)->format('Y-m-d'),
            'designer_name' => $designer ? ($designer->name ? $designer->full_name : $designer->organization_name) : null,
            'designer_tin' => $designer ? $designer->pinfl : null,
            'builder_name' => $builder ? ($builder->name ? $builder->full_name : $builder->organization_name) : null,
            'builder_tin' => $builder? $builder->pinfl : null,
            'blocks' => $this->blocks
                ? $this->blocks()->where('accepted', true)->get()->filter()->map(function ($block) {
                    return [
                        'id' => $block?->id,
                        'name' => $block?->name,
                        'floor' => $block?->floor,
                        'type' => $block->type ? BlockTypeResource::make($block->type) : null,
                        'area' => $block?->construction_area,
                        'apartment' => $block?->count_apartments,
                    ];
                })->values() : null,
            'buildings_above_5_floors' => $this->blocks()->whereRaw('floor::integer > 5')->count(),
            'buildings_with_5_floors' => $this->blocks()->whereRaw('floor::integer = 5')->count(),
            'buildings_with_4_floors' => $this->blocks()->whereRaw('floor::integer = 4')->count(),
            'buildings_with_3_floors' => $this->blocks()->whereRaw('floor::integer = 3')->count(),
            'buildings_with_2_floors' => $this->blocks()->whereRaw('floor::integer = 2')->count(),
            'buildings_with_1_floor' => $this->blocks()->whereRaw('floor::integer = 1')->count(),
        ];
    }
}
