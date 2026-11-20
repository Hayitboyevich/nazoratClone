<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConsumerChangeRequest;
use App\Http\Requests\DeclarationAcceptRequest;
use App\Http\Requests\DeclarationConclusionRequest;
use App\Http\Requests\DeclarationCreateRequest;
use App\Http\Requests\DeclarationUpdateRequest;
use App\Http\Resources\DeclarationConclusionResource;
use App\Http\Resources\DeclarationListResource;
use App\Models\Declaration;
use App\Models\FactType;
use App\Models\Unit;
use App\Services\DeclarationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class DeclarationController extends BaseController
{

    public function __construct(protected DeclarationService $service)
    {
        parent::__construct();
    }

    public function index($id = null): JsonResponse
    {
        try {
            $filters = request()->only(['id']);
            $data = $id
                ? $this->service->findById($id)
                : $this->service->all($filters, $this->user, $this->roleId)->paginate(request('per_page', 15));

            $resource = $id
                ? DeclarationListResource::make($data)
                : DeclarationListResource::collection($data);

            return $this->sendSuccess(
                $resource,
                'Success',
                $id ? null : pagination($data)
            );
        }catch (\Exception $exception){
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function object($id)
    {
        try {
            $data = Declaration::query()->where('object_id', $id)->first();
            return $this->sendSuccess(DeclarationListResource::make($data), 'Declaration Success');
        }catch (\Exception $exception){
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function conclusionCreate(DeclarationConclusionRequest $request): JsonResponse
    {
        try {
            $data = $this->service->concCreate($this->user, $this->roleId, $request);
            return $this->sendSuccess(DeclarationConclusionResource::make($data), 'Declaration Success');
        }catch (\Exception $exception){
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function conclusion()
    {
        try {
            $filters = request()->only(['id', 'object_id', 'declaration_id', 'role_id', 'user_id']);
            $data =  $this->service->conclusion($this->user, $this->roleId, $filters)->get();
            return $this->sendSuccess(DeclarationConclusionResource::collection($data), 'Declaration Success');
        }catch (\Exception $exception){
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function conclusionPdf($id)
    {
        try {
            $data = $this->service->concPdf($this->user, $this->roleId, $id);
            if (!$data) return $this->sendError('Xulosa topilmadi');
            return $this->sendSuccess($data, 'Success');
        }catch (\Exception $exception){
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function factTypes(): JsonResponse
    {
        try {
            $types = FactType::all(['id', 'name']);
            return $this->sendSuccess($types, 'Success');
        }catch (\Exception $exception){
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function create(DeclarationCreateRequest $request): JsonResponse
    {
        try {
            $data = $this->service->create($this->user, $this->roleId, $request);
            return $this->sendSuccess(DeclarationListResource::make($data), 'Declaration created successfully.');
        }catch (\Exception $exception){
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function accept($id, DeclarationAcceptRequest $request): JsonResponse
    {
        try {
            $data = $this->service->accept($this->user, $this->roleId, $id, $request);
//            return $this->sendSuccess(DeclarationListResource::make($data), 'Declaration accepted successfully.');
            return $this->sendSuccess($data, 'Declaration accepted successfully.');
        }catch (\Exception $exception){
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function consumerChange(ConsumerChangeRequest $request): JsonResponse
    {
        try {
             $this->service->changeConsumer($this->user, $this->roleId, $request);

            return $this->sendSuccess('success', 'Declaration changed successfully.');
        }catch (\Exception $exception){
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function update($id, DeclarationUpdateRequest $request): JsonResponse
    {
        try {
            $data = $this->service->update($this->user, $this->roleId, $id, $request);
            return $this->sendSuccess(DeclarationListResource::make($data), 'Declaration updated successfully.');
        }catch (\Exception $exception){
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function unit(): JsonResponse
    {
        try {
            $data = Unit::all(['id', 'name_uz']);
            return $this->sendSuccess($data, 'Success');
        }catch (\Exception $exception){
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function reject($id): JsonResponse
    {
        try {
            $data = $this->service->reject($this->user, $this->roleId, $id);
            return $this->sendSuccess(DeclarationListResource::make($data), 'Declaration rejected successfully.');
        }catch (\Exception $exception){
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function pdf($id): JsonResponse
    {
        try {
            $declaration = Declaration::query()->find($id);
            $vendor = $declaration->object->users()->where('role_id', UserRoleEnum::QURILISH->value)->first();
            $designer = $declaration->object->users()->where('role_id', UserRoleEnum::LOYIHA->value)->first();
            $organization = $declaration->object->users()->where('role_id', UserRoleEnum::BUYURTMACHI->value)->first();
            $internal = $declaration->object->users()->where('role_id', UserRoleEnum::ICHKI->value)->first();
            $domain = URL::to('/declaration-pdf').'/'.$declaration->id;

            $qrImage = base64_encode(QrCode::format('png')->size(200)->generate($domain));

            $pdf = Pdf::loadView('pdf.declaration', compact('declaration', 'qrImage', 'vendor', 'designer', 'organization', 'internal'));
            $pdfContent = $pdf->output();

            $base64Pdf = base64_encode($pdfContent);

            return $this->sendSuccess($base64Pdf, 'Presentation');
        }catch (\Exception $exception){
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function history($id): JsonResponse
    {
        try {
            $data = $this->service->getHistory($id);
            return $this->sendSuccess($data, 'History get successfully.');
        }catch (\Exception $exception){
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }
}
