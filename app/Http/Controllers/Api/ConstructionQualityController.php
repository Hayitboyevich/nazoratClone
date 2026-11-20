<?php

namespace App\Http\Controllers\Api;

use App\Exports\ConstructionQualityAssessmentsExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConstructionQualityRequest;
use App\Http\Resources\ConstructionQualityResource;
use App\Http\Resources\QualityControlResource;
use App\Models\ConstructionQualityAssessment;
use App\Models\QualityControl;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ConstructionQualityController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index($type, $objectId)
    {
        try {
            $model = ConstructionQualityAssessment::query()->where('type', $type)->where('object_id', $objectId)->paginate(request('per_page') ?? 30);
            return $this->sendSuccess(ConstructionQualityResource::collection($model), 'Success', pagination($model));
        }catch (\Exception $exception){
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function report()
    {
        $filters = request()->only(['object_id', 'region_id',  'district_id']);
        return Excel::download(
            new ConstructionQualityAssessmentsExport($filters),
            'yopiq_ishlar.xlsx'
        );
    }

    public function create(ConstructionQualityRequest $request)
    {
        try {
            $model = ConstructionQualityAssessment::query()->create($request->validated());
            return $this->sendSuccess(new ConstructionQualityResource($model), 'Success');
        }catch (\Exception $exception){
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }
}
