<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\DistrictResource;
use App\Http\Resources\RegionResource;
use App\Models\District;
use App\Models\Region;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class RegionController extends BaseController
{
    public function regions(): JsonResponse
    {
        $regions = json_decode(Redis::get('regions'), true);
        return $this->sendSuccess($regions, 'All Regions');
    }

    public function districts($id = null): JsonResponse
    {
        try {
            $districts = json_decode(
                Redis::get("districts:{$id}"),
                true
            );
           return $this->sendSuccess($districts, 'All Districts');
        }catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }
}
