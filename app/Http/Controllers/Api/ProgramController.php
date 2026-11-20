<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProgramResource;
use App\Models\Program;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class ProgramController extends BaseController
{
    public function programs(): JsonResponse
    {
        try {
            $program = collect(json_decode(Redis::get('programs'), true));
           return $this->sendSuccess($program, 'All programs retrieved successfully.');
        }catch (\Exception $exception){
            return $this->sendError($exception->getMessage());
        }
    }
}
