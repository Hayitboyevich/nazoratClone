<?php

namespace App\Http\Controllers\Api;

use App\Exports\ConcreteWorkExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConcreteWorkRequest;
use App\Http\Requests\ConcreteWorkUpdateRequest;
use App\Http\Resources\ConcreteWorkResource;
use App\Services\ConcreteWorkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ConcreteWorkController extends BaseController
{
    public function __construct(public ConcreteWorkService $service)
    {
        parent::__construct();
    }

    public function index($id = null): JsonResponse
    {
        try {
            $filters = request()->only(['object_id', 'block_id']);

            $data = $this->service->all($this->user, $this->roleId, $filters)->paginate(\request('per_page') ?? 20);

            return $this->sendSuccess(ConcreteWorkResource::collection($data),'Success', pagination($data));
        }catch (\Exception $exception){
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function create(ConcreteWorkRequest $request): JsonResponse
    {
        try {
            $data = $this->service->create($request);
            return $this->sendSuccess(ConcreteWorkResource::make($data),'Success');
        }catch (\Exception $exception){
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function update(ConcreteWorkUpdateRequest $request): JsonResponse
    {
        try {
            $data = $this->service->update($request);
            return $this->sendSuccess(ConcreteWorkResource::make($data),'Success');
        }catch (\Exception $exception){
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function report()
    {
        try {
            $filters = request()->only(['object_id', 'region_id',  'district_id']);
            return Excel::download(
                new ConcreteWorkExport($filters),
                'beton_ishlari.xlsx'
            );
        }catch (\Exception $exception){
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }
}
