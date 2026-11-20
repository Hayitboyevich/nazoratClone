<?php

namespace App\Http\Resources;

use App\Enums\UserRoleEnum;
use App\Models\LabClaim;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class LabStatisticsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = Auth::user();
        $role = $user->getRoleFromToken();
        $collection = $this->resource;
//        dd($this->resource->count());
        $data = [
            'all' => $collection->count(),
        ];

        if (in_array((int)$role, [UserRoleEnum::REG_LAB->value, UserRoleEnum::LABORANT->value, UserRoleEnum::INSPEKSIYA->value, UserRoleEnum::RES_LAB->value])) {
            $data['new'] = $collection->where('status', LabClaim::STATUS_NEW)->count();
            $data['in_lab'] = $collection->where('status', LabClaim::STATUS_IN_LAB)->count();
            $data['in_accountant'] = $collection->where('status', LabClaim::STATUS_IN_ACCOUNTANT)->count();
            $data['in_inspeksiya'] = $collection->where('status', LabClaim::STATUS_IN_INSPEKSIYA)->count();
            $data['in_customer'] = $collection->where('status', LabClaim::STATUS_IN_CUSTOMER)->count();
            $data['in_completed'] = $collection->where('status', LabClaim::STATUS_COMPLETED)->count();
            $data['in_rejected'] = $collection->where('status', LabClaim::STATUS_REJECTED)->count();
            $data['in_canceled'] = $collection->where('status', LabClaim::STATUS_CANCELLED)->count();
            $data['returned'] = $collection->where('status', LabClaim::STATUS_RETURNED)->count();
        }
        if (in_array((int)$role, [UserRoleEnum::REG_LAB->value, UserRoleEnum::LABORANT->value, UserRoleEnum::RES_LAB->value])){
            $data['in_lab_process'] = $collection->where('status', LabClaim::STATUS_IN_LAB_CONTRACT_ACTION)->count();
        }
        if ((int)$role === UserRoleEnum::BUXGALTER->value) {
            $data['new'] = $collection->where('status', LabClaim::STATUS_NEW)->count();
            $data['in_accountant'] = $collection->where('status', LabClaim::STATUS_IN_ACCOUNTANT)->count();
            $data['in_completed'] = $collection->where('status', LabClaim::STATUS_COMPLETED)->count();
            $data['returned'] = $collection->where('status', LabClaim::STATUS_RETURNED)->count();
        }

        if ((int)$role === UserRoleEnum::BUYURTMACHI->value) {
            $data['in_process'] = $collection->where('status', LabClaim::STATUS_NEW)->count() +
                $collection->where('status', LabClaim::STATUS_IN_LAB)->count() +
                $collection->where('status', LabClaim::STATUS_IN_ACCOUNTANT)->count() +
                $collection->where('status', LabClaim::STATUS_RETURNED)->count() +
                $collection->where('status', LabClaim::STATUS_IN_INSPEKSIYA)->count();
            $data['in_customer'] = $collection->where('status', LabClaim::STATUS_IN_CUSTOMER)->count();
            $data['in_completed'] = $collection->where('status', LabClaim::STATUS_COMPLETED)->count();
            $data['in_rejected'] = $collection->where('status', LabClaim::STATUS_REJECTED)->count();
            $data['in_canceled'] = $collection->where('status', LabClaim::STATUS_CANCELLED)->count();
        }

        return $data;
    }
}
