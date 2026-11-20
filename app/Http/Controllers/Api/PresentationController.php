<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRoleEnum;
use App\Exports\PresentationExport;
use App\Http\Requests\PresentationRequest;
use App\Http\Resources\MaterialResource;
use App\Http\Resources\PresentationResource;
use App\Models\Inspection;
use App\Models\Material;
use App\Models\Presentation;
use App\Services\PresentationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\URL;
use Maatwebsite\Excel\Facades\Excel;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class PresentationController extends BaseController
{
    public function __construct(protected PresentationService $service)
    {
        parent::__construct();
    }

    public function index($id = null): JsonResponse
    {
        try {
            $filters = request()->only(['object_id', 'status', 'task_id','region_id', 'district_id',  'object_type', 'funding_source', 'start_date', 'end_date']);
            $data = $id
                ? $this->service->findById($id)
                : $this->service->all($filters, $this->user, $this->roleId)->paginate(request('per_page', 15));

            $resource = $id
                ? PresentationResource::make($data)
                : PresentationResource::collection($data);

            return $this->sendSuccess(
                $resource,
                'Success',
                $id ? null : pagination($data)
            );

        } catch (\Exception $exception) {
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function count(): JsonResponse
    {
        try {
            $filters = request()->only(['object_id', 'status', 'task_id','region_id', 'district_id',  'object_type', 'funding_source', 'start_date', 'end_date']);
            $data = $this->service->count($filters, $this->user, $this->roleId);
            return $this->sendSuccess($data, 'Count');
        }catch (\Exception $exception){
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function material(): JsonResponse
    {
        try {
            $volumes = Material::with([
                'children.children'
            ])->where('type', 'volume')->get();
            return $this->sendSuccess(MaterialResource::collection($volumes), 'Success');
        }catch (\Exception $exception){
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function create(PresentationRequest $request): JsonResponse
    {
        try {
            $data = $this->service->create($request, $this->user, $this->roleId);
            return $this->sendSuccess(PresentationResource::make($data), 'Presentation created successfully.');
        } catch (\Exception $exception) {
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function accept($id): JsonResponse
    {
        try {
            $data = $this->service->accept($id, $this->user, $this->roleId);
            return $this->sendSuccess(PresentationResource::make($data), 'Presentation updated successfully.');
        } catch (\Exception $exception) {
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function sendInspector()
    {
        try {
            $data = $this->service->sendInspector($this->user, $this->roleId);
            return $this->sendSuccess(PresentationResource::make($data), 'Presentation updated successfully.');

        }catch (\Exception $exception){
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function report()
    {
        try {

            $filters = request()->only([
                'object_id',
                'status',
                'task_id',
                'region_id',
                'district_id',
                'object_type',
                'funding_source',
                'start_date',
                'end_date'
            ]);

            return Excel::download(
                new PresentationExport(
                    $this->service->all($filters, $this->user, $this->roleId)
                ),
                'statistic.xlsx'
            );

        } catch (\Exception $exception) {
            return $this->sendError(
                'Xatolik aniqlandi',
                $exception->getMessage()
            );
        }
    }

    public function pdf($id): JsonResponse
    {
        try {
            $presentation = Presentation::find($id);
            $inspection = Inspection::query()->where(['region_id' => $presentation->object->region_id])->first();
            $buyurtmachi = $presentation->object->users()->wherePivot('role_id', UserRoleEnum::BUYURTMACHI->value)->first();
            $pudrat = $presentation->object->users()->wherePivot('role_id', UserRoleEnum::QURILISH->value)->first();
            $domain = URL::to('/presentation-pdf').'/'.$id;


            $qrImage = base64_encode(QrCode::format('png')->size(200)->generate($domain));
            $pdf = PDF::loadView('pdf.presentation', compact('presentation', 'inspection', 'buyurtmachi', 'pudrat', 'qrImage'));
            $pdfContent = $pdf->output();

            $base64Pdf = base64_encode($pdfContent);

            return $this->sendSuccess($base64Pdf, 'Presentation');
        }catch (\Exception $exception){
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function pdfDeed($id): JsonResponse
    {
        try {
            $presentation = Presentation::find($id);
            $inspection = Inspection::query()->where(['region_id' => $presentation->object->region_id])->first();
            $buyurtmachi = $presentation->object->users()->wherePivot('role_id', UserRoleEnum::BUYURTMACHI->value)->first();
            $pudrat = $presentation->object->users()->wherePivot('role_id', UserRoleEnum::QURILISH->value)->first();
            $domain = URL::to('/presentation-deed-pdf').'/'.$id;


            $qrImage = base64_encode(QrCode::format('png')->size(200)->generate($domain));
            $pdf = PDF::loadView('pdf.presentation-deed', compact('presentation', 'inspection', 'buyurtmachi', 'pudrat', 'qrImage'));
            $pdfContent = $pdf->output();

            $base64Pdf = base64_encode($pdfContent);

            return $this->sendSuccess($base64Pdf, 'Presentation');
        }catch (\Exception $exception){
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function reject($id): JsonResponse
    {
        try {
            $data = $this->service->reject($id, $this->user, $this->roleId);
            return $this->sendSuccess(PresentationResource::make($data), 'Presentation updated successfully.');
        } catch (\Exception $exception) {
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function history($id): JsonResponse
    {
        try {
            $data = $this->service->getHistory($id);
            return $this->sendSuccess($data, 'Presentation history get successfully.');
        } catch (\Exception $exception) {
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }
}
