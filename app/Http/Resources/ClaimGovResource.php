<?php

namespace App\Http\Resources;

use App\Enums\UserRoleEnum;
use App\Models\Block;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClaimGovResource extends JsonResource
{

    public function toArray(Request $request): array
    {

        $blockResponseArr = [];
        $blocks = Block::with(['type', 'mode'])->where('article_id', $this->object_id)->get();
        foreach ($blocks as $block) {
            $countChecklist = $block->getClaimChecklistCount($this->id);
            $blockArr = $block->toArray();
            $blockArr['is_filled'] = $countChecklist == 7;
            $blockResponseArr[] = $blockArr;
        }
        $user = $this->object->users()->wherePivot('role_id', UserRoleEnum::FOYDALANUVCHI->value)->first();

        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'object' => $this->object ? [
                'id' => $this->object_id,
                'name' => $this->object->name,
                'task_id' => $this->object->task_id ?? $this->object->manual_task_id,
                'gnk_id' => $this->object->gnk_id,
                'reestr_number' => $this->object->reestr_number,
                'lat' => $this->object->lat,
                'long' => $this->object->long,
                'object_type' => ObjectTypeResource::make($this->object->objectType),
                'users' => $user  ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'surname' => $user->surname,
                    'middle_name' => $user->middle_name,
                    'organization_name' => $user->organization_name,
                    'pin' => $user->pinfl,
                ] : null,
            ] : null,
            'blocks' => $blockResponseArr,
            'topography' => $this->topography,
            'region' => $this?->region?->name_uz,
            'district' =>  $this?->district?->name_uz,
            'address' => $this->address,
            'status' => $this->status,
            'legal_tin' => $this->legal_tin,
            'legal_name' => $this->legal_name,
            'declaration_id' => $this->declaration_id,
            'information_organizations_responsible' => $this->information_organizations_responsible,
            'equipment_acceptance_report' => $this->equipment_acceptance_report,
            'reviews' => ClaimGovReviewResource::collection($this->reviews),
            'sphere' => $this->sphere ? [
                'id' => $this->sphere->id,
                'name' => $this->sphere->name,
                'organizations' => $this->sphere->organizations,
                'extra' => $this->sphere->extra,
            ] : null,
            'created_at' => $this->created_at
        ];
    }
}
