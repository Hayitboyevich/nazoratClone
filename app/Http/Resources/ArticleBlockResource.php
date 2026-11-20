<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleBlockResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $checklists = collect($this->checklists ?? []);

        $userIds = $checklists
            ->pluck('user_id')
            ->filter()
            ->unique()
            ->values();

        $users = User::withTrashed()
            ->whereIn('id', $userIds)
            ->get()
            ->keyBy('id');

        return [
            'id' => $this->id,
            'name' => $this->name,
            "object_name" => $this->object->name,
            'mode' => BlockModeResource::make($this->mode),
            'type' => BlockTypeResource::make($this->type),
            'block_number' => $this->block_number,
            'floor' => $this->floor,
            'construction_area' => $this->construction_area,
            'count_apartments' => $this->count_apartments,
            'height' => $this->height,
            'length' => $this->length,
            'status' => $this->status,
            'accepted' => $this->accepted,
            'selected_work_type' => $this->selected_work_type,
            'is_declaration' => $this->is_declaration,
            'foundation' => $this->foundation,
            'checklists' => $checklists->map(function ($checklist) use ($users) {

                $user = $users->get($checklist['user_id'] ?? null);

                return [
                    'work_type_id' => $checklist['work_type_id'] ?? null,
                    'floor' => $checklist['floor'] ?? null,
                    'user_id' => $checklist['user_id'] ?? null,
                    'user' => $user?->full_name,
                    'created_at' => $checklist['created_at'] ?? null,
                ];

            })->values(),
        ];
    }
}
