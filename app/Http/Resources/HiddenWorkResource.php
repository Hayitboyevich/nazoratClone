<?php

namespace App\Http\Resources;

use App\Enums\LogType;
use App\Models\ObjectStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HiddenWorkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'object' => $this->object->name,
            'block_id' => $this->block_id,
            'block' => $this->block->name,

            'status' => $this->status,

            'organization' => $this->organization ? ['id' => $this->organization_id, 'name' => $this->organization->organization_name] : null,
            'vendor' => $this->vendor ? ['id' => $this->vendor_id, 'name' => $this->vendor->organization_name] : null,
            'designer' => $this->designer ? ['id' => $this->designer_id, 'name' => $this->designer->organization_name] : null,

            'address' => $this->address,
            'presentation_date' => $this->presentation_date,


            'internal' => $this->internal ? ['id' => $this->internal_id, 'name' => $this->internal->full_name] : null,
            'technic' => $this->technic ? ['id' => $this->technic_id, 'name' => $this->technic->full_name] : null,
            'author' => $this->author ? ['id' => $this->author_id, 'name' => $this->author->full_name] : null,

            'desc' => $this->desc,

            'project_name' => $this->project_name,
            'drawing_number' => $this->drawing_number,
            'drawing_date' => $this->drawing_date,

            'materials' => $this->materials,
            'certificate_info' => $this->certificate_info,

            'has_project_deviation' => (bool) $this->has_project_deviation,

            'project_drawing_number' => $this->project_drawing_number,
            'project_drawing_date' => $this->project_drawing_date,

            'start_date' => $this->start_date,
            'end_date' => $this->end_date,

            'comment' => $this->comment,
            'post_permit_works' => $this->post_permit_works,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'technic_answer' => $this->technic_answer,
            'author_answer' => $this->author_answer,
            'work_type' =>$this->workType ?  WorkTypeResource::make($this->workType) : null,
            'floor' => $this->floor,

            'images' => $this->images ? ImageResource::collection($this->images) : null,

            'history' => $this->histories->map(function ($history) {
                return [
                    'id' => $history->id,
                    'user' => $history->content->user ? User::query()->find($history->content->user, ['name', 'surname', 'middle_name']) : null,
                    'role' => $history->content->role ? Role::query()->find($history->content->role, ['name', 'description']) : null,
                    'status' => $history->content->status,
                    'type' => $history->type,
                    'is_change' => $history->type ? LogType::getHiddenWorkLabel($history->type) : null,
                    'created_at' => $history->created_at,
                ];
            })->sortByDesc('created_at')->values(),
        ];
    }
}
