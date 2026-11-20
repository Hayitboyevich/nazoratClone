<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TechnicalStaffRequest;
use App\Http\Resources\TechnicalStaffResource;
use App\Models\Article;
use App\Models\TechnicalStaff;
use Illuminate\Http\Request;

class TechnicalStaffController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index($id)
    {
        try{
            $staff = TechnicalStaff::query()->where('object_id', $id)->paginate(request('per_page' ?? 30));
            return $this->sendSuccess(TechnicalStaffResource::collection($staff), 'Success', pagination($staff));
        }catch (\Exception $exception){
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function create(TechnicalStaffRequest $request)
    {
        try {
            $staff = TechnicalStaff::query()->create($request->validated());
            return $this->sendSuccess(TechnicalStaffResource::make($staff), 'Success');
        }catch (\Exception $exception){
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function update($id, TechnicalStaffRequest $request)
    {
        try {
            $staff = TechnicalStaff::query()->findOrFail($id);
            $staff->update($request->validated());

            return $this->sendSuccess(TechnicalStaffResource::make($staff), 'Success');
        }catch (\Exception $exception){
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }
}
