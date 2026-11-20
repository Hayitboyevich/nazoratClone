<?php

namespace App\Http\Resources;

use App\Enums\UserRoleEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleLocationListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' =>$this->name,
            'lat' => $this->lat,
            'long' => $this->long,
            'task_id' => $this->task_id ?? $this->manual_task_id,
            'location_building' => $this->location_building,
            'organization_name' => $this->organization_name ?? null,
            'status' => [
                'id' => $this->objectStatus->id,
                'name' => $this->objectStatus->name,
            ],

        ];

    }
}
