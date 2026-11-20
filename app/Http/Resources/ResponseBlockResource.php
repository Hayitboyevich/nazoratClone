<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResponseBlockResource extends JsonResource
{

    public function toArray(Request $request): array
    {
        $checklists = collect($this->checklists ?? []);
        $actions = collect($this->actions ?? []);

        $userIds = $checklists
            ->pluck('user_id')
            ->merge($actions->pluck('user_id'))
            ->filter()
            ->unique()
            ->values();

        $users = User::withTrashed()
            ->whereIn('id', $userIds)
            ->get()
            ->keyBy('id');

        $formatData = function ($item) use ($users) {
            $user = $users->get($item['user_id'] ?? null);

            return [
                'work_type_id' => $item['work_type_id'] ?? null,
                'floor'        => $item['floor'] ?? null,
                'user_id'      => $item['user_id'] ?? null,
                'user'         => $user?->full_name,
                'created_at'   => $item['created_at'] ?? null,
            ];
        };

        return [
            'id' => $this->id,
            'name' => $this->name,
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
            'appearance_type' => $this->appearance_type,
            'selected_work_type' => $this->selected_work_type,
            'end_date' => $this->end_date,
            'task_id' => $this->task_id,
            'block_unique_id' => $this->block_unique_id,
            'foundation' => $this->foundation,

            'checklists' => $checklists
                ->map($formatData)
                ->values(),

            'actions' => $actions
                ->map($formatData)
                ->values(),
        ];
    }
}
