<?php

namespace App\Http\Controllers\Api;



use App\Http\Requests\HolidayCreateRequest;
use App\Http\Resources\HolidayResource;
use App\Models\DocumentSignature;
use App\Models\Holiday;
use App\Services\EimzoService;
use App\Services\ShaffofIdService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class HolidayController extends BaseController
{
    public function __construct(
        protected EimzoService $imzoService,
        protected ShaffofIdService $shaffofIdService
    )
    {
        $this->middleware('auth');
        parent::__construct();
    }

    public function index(): JsonResponse
    {
        try {

            $holidays = Holiday::query()
                ->select('id', 'name', 'day', 'created_at')
                ->orderBy('created_at', 'desc')
                ->get()
                ->groupBy(function ($holiday) {
                    return Carbon::parse($holiday->day)->format('Y');
                });

            $holidays = $holidays->toArray();


            return $this->sendSuccess($holidays, 'Holidays retrieved successfully.');
        }catch (\Exception $exception){
            return $this->sendError('Xatolik', $exception->getMessage());
        }
    }


    public function create(HolidayCreateRequest $request): JsonResponse
    {
        try {
            $document = DocumentSignature::query()
                ->where('guid', $request->guid)
                ->where('uuid', $request->uuid)
                ->where('user_id', $this->user->id)
                ->where('role_id', $this->roleId)
                ->first();

            if (!$document) {
                return $this->sendError('Tasdiqlash uchun hujjat topilmadi.');
            }

            if ($document->status !== 'signed') {
                $signed = $this->shaffofIdService->checkDocumentSign(
                    $this->user,
                    $this->roleId,
                    $request->guid,
                    $request->uuid
                );

                if (!$signed) {
                    return $this->sendError('Hujjat imzolanmagan yoki tasdiqlashda xatolik yuz berdi.');
                }
            }

            $holiday = DB::transaction(function () use ($request, $document) {
                $holiday = Holiday::query()->create(
                    $request->except(['guid', 'uuid'])
                );

                $document->signable()->associate($holiday);
                $document->save();

                return $holiday;
            });

            return $this->sendSuccess(
                new HolidayResource($holiday),
                'Holiday created successfully.'
            );
        } catch (\Throwable $exception) {
            report($exception);

            return $this->sendError('Xatolik yuz berdi.', $exception->getMessage());
        }
    }
}
