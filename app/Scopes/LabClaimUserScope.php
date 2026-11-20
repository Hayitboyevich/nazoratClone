<?php

namespace App\Scopes;

use App\Enums\UserRoleEnum;
use App\Models\LabClaim;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class LabClaimUserScope implements Scope
{

    protected array $role_statuses = [
        44 => [1, 2, 3, 4, 5, 6, 7, 8, 9, 11],
        12 => [4, 5, 6, 7, 8, 9, 11],
        43 => [1, 2, 3, 4, 5, 6, 7, 8, 9, 11],
        8 => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
        42 => [2, 3, 4, 5, 6, 7, 8, 9, 11],
        28 => [3, 4, 5, 6, 7, 8, 9]
    ];

    /**
     * @inheritDoc
     */
    public function apply(Builder $builder, Model $model)
    {
        $user = Auth::user();
        if (!$user) {
            $builder->whereRaw('1=0');
            return;
        }
        $user_role = (int)$user->getRoleFromToken();
        $role = UserRoleEnum::tryFrom($user_role);
        if (!$role or !isset($this->role_statuses[$role->value])) {
            $builder->whereRaw('1=0');
            return;
        }
        $allowedStatuses = $this->role_statuses[$role->value];
        if($role->value === UserRoleEnum::RES_LAB->value){

        }elseif (in_array($role->value, [UserRoleEnum::BUXGALTER->value, UserRoleEnum::REG_LAB->value, UserRoleEnum::INSPEKSIYA->value], true)) {
            $builder->whereHas('labTestingOrg', function ($q) use ($user) {
                $q->where('region_id', $user->region_id);
            });
//            $builder->where('lab_testing_org_id', $user->region_id);
        } else {
            $builder->whereHas('labClaimUsers', function ($q) use ($user, $user_role) {
                $q->where('user_id', $user->id)
                    ->where('role_id', $user_role);
            });
        }
        $requestedStatus = Request::get('status');
        if ($requestedStatus !== null) {
            if ((int)$requestedStatus === LabClaim::STATUS_IN_PROCESS and $user_role === UserRoleEnum::BUYURTMACHI->value) {
                $builder->whereIn('status', [LabClaim::STATUS_NEW, LabClaim::STATUS_IN_LAB,
                    LabClaim::STATUS_IN_ACCOUNTANT, LabClaim::STATUS_IN_INSPEKSIYA]);
                return;
            }
            if (!in_array((int)$requestedStatus, $allowedStatuses, true)) {
                $builder->whereRaw('1=0');
                return;
            }
            $builder->where('status', $requestedStatus);
        } else {
            $builder->whereIn('status', $allowedStatuses);
        }

    }
}
