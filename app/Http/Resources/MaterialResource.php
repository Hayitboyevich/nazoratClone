<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaterialResource extends JsonResource
{

    public function toArray($request)
    {
        if ($this->type === 'volume') {
            return [
                'id'         => $this->id,
                'name'       => $this->name,
                'old_id'     => $this->old_id,
                'categories' => MaterialResource::collection(
                    $this->children->where('type', 'category')
                ),
            ];
        }

        if ($this->type === 'category') {
            return [
                'id'         => $this->id,
                'name'       => $this->name,
                'volume_id'  => $this->parent_id,
                'old_id'     => $this->old_id,
                'groups'     => MaterialResource::collection(
                    $this->children->where('type', 'group')
                ),
            ];
        }

        if ($this->type === 'group') {
            return [
                'id'         => $this->id,
                'name'       => $this->name,
                'category_id'=> $this->parent_id,
                'old_id'     => $this->old_id,
            ];
        }

        return parent::toArray($request);
    }
}
