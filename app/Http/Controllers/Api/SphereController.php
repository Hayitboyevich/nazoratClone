<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SphereResource;
use App\Models\Sphere;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class SphereController extends BaseController
{
    public function spheres(): JsonResponse
    {
        try {
            $spheres = collect(json_decode(Redis::get('spheres'), true));

            if ($name = request('name')) {
                $name = mb_strtolower($name);

                $spheres = $spheres->filter(function ($sphere) use ($name) {
                    return str_contains(
                        mb_strtolower($sphere['name_uz']),
                        $name
                    );
                });
            }

            return $this->sendSuccess($spheres, 'All Spheres');
        }catch (\Exception $exception){
            return $this->sendError($exception->getMessage());
        }
    }
}
