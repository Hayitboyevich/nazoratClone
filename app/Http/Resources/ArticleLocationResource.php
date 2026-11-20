<?php

namespace App\Http\Resources;

use App\Enums\UserRoleEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleLocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $loyihachi = $this->users()->where('role_id', UserRoleEnum::LOYIHA->value)->first();
        $pudrat = $this->users()->where('role_id', UserRoleEnum::QURILISH->value)->first();
        return [
            'id' => $this->id,
            'name' =>$this->name,
            'task_id' => $this->task_id ?? $this->manual_task_id,
            'created_at' => $this->created_at,
            'sphere_id' => $this->sphere_id,
            'location_building' => $this->location_building,
            'difficulty' => $this->difficulty?->difficulty,
            'organization_name' => $this->organization_name ?? null,
            'loyiha' => $loyihachi ? $loyihachi->organization_name :  null,
            'pudrat' => $pudrat ? $pudrat->organization_name :  null,
            'status' => [
                'id' => $this->objectStatus->id,
                'name' => $this->objectStatus->name,
            ],
            'closed_at' => $this->closed_at,
            'region_soato' => $this->region ? $this->region->soato : null,
            'district_soato' => $this->district ? $this->district->soato : null,
            'deadline' => $this->deadline,
            'lat' => $this->lat,
            'rating' => $this->rating,
            'long' => $this->long,
            'number_protocol' => $this->number_protocol,
            'reestr_number' => $this->reestr_number,
            'block_count' => $this->blocks()->count() ?? 0,
            'apartment_count' => $this->blocks->sum('count_apartments') ?? 0,
            'blocks' => $this->blocks ? $this->blocks->map(function ($block) {
                return [
                    'id' => $block->id,
                    'name' => $block->name,
                    'apartment_count' => $block->count_apartments,
                    'accepted' => $block->accepted,
                    'area' => $block->contruction_area,
                    'floor' => $block->floor,
                ];
            }) : null,
        ];

    }
}
