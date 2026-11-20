<?php

namespace App\Models\Traits;

use App\Enums\UserRoleEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait LabTrait
{
    public function scopeForLaborant(Builder $query): Builder
    {
        $is_jwt = request()->bearerToken() !== null;
        if ($is_jwt && Auth::user() && in_array((int)Auth::user()->getRoleFromToken(), [UserRoleEnum::LABORANT->value, UserRoleEnum::REG_LAB->value,
                UserRoleEnum::RES_LAB->value])) {
            $query->where('is_laboratoriya', true);
        }

        return $query;
    }
}
