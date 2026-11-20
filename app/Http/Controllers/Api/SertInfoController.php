<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SertService;

class SertInfoController extends BaseController
{

    public function __construct(protected SertService $sertService)
    {
        parent::__construct();
    }

    public function getUserByPinf()
    {
        try {
            $data = $this->sertService->sertGetUserInfo(pinfl: request('pinfl'));

            if (!$data) return $this->sendError('Ma\'lumot topilmadi');

            return $this->sendSuccess($data, 'Success');

        }catch (\Exception $exception){
            return $this->sendError('Xatoli aniqlandi', $exception->getMessage());
        }
    }
}
