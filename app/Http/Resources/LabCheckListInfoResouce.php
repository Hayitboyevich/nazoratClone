<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LabCheckListInfoResouce extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'checklist_id' => $this->checklist_id ?? null,
            'type' => $this->type ?? null,
            'files' => collect($this->files ?? [])
                ->map(fn($file) => config('app.url') . '/storage/' . ltrim($file, '/'))
                ->toArray(),
            'description' => $this->description ?? null,
            'block_id' => $this->block_id ?? null,
            'object_id' => $this->object_id ?? null,
            'question_id' => $this->question_id ?? null,
            'user' => [
                'id' => $this->user->id ?? null,
                'surname' => $this->user->surname ?? null,
                'name' => $this->user->name ?? null,
                'middle_name' => $this->user->middle_name ?? null
            ],
            'role' => [
                'id' => $this->role->id ?? null,
                'surname' => $this->role->name ?? null
            ],
            'date' => $this->created_at
        ];
    }
}
