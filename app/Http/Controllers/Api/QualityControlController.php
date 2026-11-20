<?php

namespace App\Http\Controllers\Api;

use App\Exports\QualityControlExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\QualityControlRequest;
use App\Http\Requests\QualityControlUpdateRequest;
use App\Http\Resources\QualityControlResource;
use App\Models\QualityControlMaterial;
use App\Services\QualityControlService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class QualityControlController extends BaseController
{
    public function __construct(public QualityControlService $service)
    {
        parent::__construct();
    }

    public function index()
    {
        try {
            $filters = request()->only(['object_id', 'block_id']);
            $data = $this->service->all($filters, $this->user, $this->roleId)->orderBy('created_at', 'desc')->paginate(request('per_page') ?? 15);
            return $this->sendSuccess(QualityControlResource::collection($data), 'Quality control list', pagination($data));
        }catch (\Exception $exception){
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function report()
    {
        try {
            $filters = request()->only(['object_id', 'region_id',  'district_id']);
            return Excel::download(
                new QualityControlExport($filters),
                'quality_controls.xlsx'
            );
        }catch (\Exception $exception){
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function createInternal(QualityControlRequest $request)
    {
        try {
            $data = $this->service->create($request);
            return $this->sendSuccess(QualityControlResource::make($data), 'Quality control created', 201);
        }catch (\Exception $exception){
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function updateInternal(QualityControlUpdateRequest $request)
    {
        try {
            $data = $this->service->update($request);
            return $this->sendSuccess(QualityControlResource::make($data), 'Quality control created', 201);
        }catch (\Exception $exception){
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function materialList()
    {
        try {
            $data = QualityControlMaterial::query()->select(['id', 'name', 'unit'])->get();
            return $this->sendSuccess($data, 'Quality control list');
        }catch (\Exception $exception){
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }


}
