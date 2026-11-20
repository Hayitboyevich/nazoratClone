<?php

namespace App\Http\Controllers\Api;

use App\Enums\DxaResponseStatusEnum;
use App\Enums\LawyerStatusEnum;
use App\Enums\LogType;
use App\Enums\UserRoleEnum;
use App\Exports\DxaResponseExport;
use App\Http\Requests\DxaResponseEditRequest;
use App\Http\Requests\DxaResponseInspectorRequest;
use App\Http\Requests\DxaResponseRegisterRequest;
use App\Http\Requests\DxaResponseRejectRequest;
use App\Http\Requests\ResponseFineRequest;
use App\Http\Resources\DxaResponseListResource;
use App\Http\Resources\DxaResponseResource;
use App\Http\Resources\DxaStatusResource;
use App\Http\Resources\ResponseBlockResource;
use App\Models\Article;
use App\Models\DxaResponse;
use App\Models\DxaResponseStatus;
use App\Models\Inspection;
use App\Models\LabClaim;
use App\Models\Rekvizit;
use App\Models\ResponseFine;
use App\Services\ArticleService;
use App\Services\CheckListAnswerService;
use App\Services\DxaResponseService;
use App\Services\MonitoringService;
use App\Services\RegulationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Maatwebsite\Excel\Facades\Excel;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class RegisterController extends BaseController
{
    public function __construct(
        protected DxaResponseService     $service,
        protected ArticleService         $articleService,
        protected RegulationService      $regulationService,
        protected MonitoringService      $monitoringService,
        protected CheckListAnswerService $checkListService,
    )
    {
        $this->middleware('auth');
        parent::__construct();
    }

    public function registers(): JsonResponse
    {
        $query = $this->service->getRegisters($this->user, $this->roleId, 1);
        $filters = request()->only(['customer_name', 'funding_source','in_compliance', 'manual', 'start_date', 'end_date', 'sphere_id', 'status', 'inspector_id', 'region_id', 'object_type', 'task_id', 'district_id', 'lawyer_status']);
        $registers = $this->service->searchRegisters($query, $filters)
            ->when(isset($filters['status']) && $filters['status'] == DxaResponseStatusEnum::ACCEPTED->value, function ($query) {
                $query->orderByRaw("confirmed_at IS NULL, confirmed_at " . request('sort_by_confirmed', 'DESC'));
            }, function ($query) {
                $query->orderBy('created_at', request('sort_by_date', 'DESC'));
            })
            ->paginate(request('per_page', 10));

        return $this->sendSuccess(DxaResponseListResource::collection($registers), 'All registers  successfully.', pagination($registers));
    }

    public function reRegister(): JsonResponse
    {
        $query = $this->service->getRegisters($this->user, $this->roleId, 2);
        $filters = request()->only(['customer_name', 'funding_source', 'start_date', 'end_date', 'sphere_id', 'inspector_id', 'status', 'object_type', 'task_id', 'district_id', 'region_id', 'lawyer_status']);
        $registers = $this->service->searchRegisters($query, $filters)
            ->when(isset($filters['status']) && $filters['status'] == 4, function ($query) {
                $query->orderByRaw("confirmed_at IS NULL, confirmed_at " . request('sort_by_confirmed', 'DESC'));
            }, function ($query) {
                $query->orderBy('created_at', request('sort_by_date', 'DESC'));
            })
            ->paginate(request('per_page', 10));

        return $this->sendSuccess(DxaResponseListResource::collection($registers), 'All registers  successfully.', pagination($registers));
    }

    public function edit(DxaResponseEditRequest $request): JsonResponse
    {
        try {
            DxaResponse::query()->find($request->id)->update(['category_object_dictionary' => $request->category_object_dictionary]);
            return $this->sendSuccess([], 'Success');
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }


    public function totalCount(): JsonResponse
    {
        try {
            $data = [
                'register' => $this->service->getRegisters($this->user, $this->roleId, 1)->count(),
                're_register' => $this->service->getRegisters($this->user, $this->roleId, 2)->count(),
                'petition' => $this->service->getClaims($this->user, $this->roleId)->count(),
                'checklist' => $this->checkListService->getChecklists($this->user, $this->roleId, 1)->count(),
                'object' => $this->articleService->getObjects($this->user, $this->roleId)->count(),
                'contracts' => LabClaim::query()->count(),
                'regulation' => $this->regulationService->getRegulations($this->user, $this->roleId)->count(),
                'monitoring' => $this->monitoringService->getMonitorings($this->user, $this->roleId)->count()
            ];

            return $this->sendSuccess($data, 'All data');
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage());
        }
    }

    public function checkFundingSource($id)
    {
        try {
            $data = $this->service->check($id);
            return $this->sendSuccess($data, 'Success');
        } catch (\Exception $exception) {

        }
    }

    public function getRegister($id): JsonResponse
    {
        try {
            $response = DxaResponse::query()->findOrFail($id);
            return $this->sendSuccess(DxaResponseResource::make($response), 'Register successfully.');
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function sendCourt(): JsonResponse
    {
        try {
            $response = DxaResponse::query()->findOrFaiL(request('id'));

            $response->update([
                'lawyer_status_id' => LawyerStatusEnum::PROCESS->value
            ]);

            return $this->sendSuccess([], 'Data saved successfully');
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage());
        }
    }

    public function fine(ResponseFineRequest $request): JsonResponse
    {
        try {
            $response = DxaResponse::query()->findOrFaiL($request->id);
            $fine = new ResponseFine();
            $fine->dxa_response_id = $response->id;
            $fine->organization_name = $request->organization_name;
            $fine->user_type = $request->user_type;
            $fine->inn = $request->inn;
            $fine->full_name = $request->full_name;
            $fine->pinfl = $request->pinfl;
            $fine->position = $request->position;
            $fine->decision_series = $request->decision_series;
            $fine->decision_number = $request->decision_number;
            $fine->substance = $request->substance;
            $fine->substance_item = $request->substance_item;
            $fine->amount = $request->amount;
            $fine->date = $request->date;
            $fine->save();

            if ($request->hasFile('images')) {
                foreach ($request->images as $image) {
                    $path = $image->store('images/fines', 'public');
                    $fine->images()->create(['url' => $path]);
                }
            }
            if ($request->hasFile('files')) {
                foreach ($request->files as $document) {
                    $path = $document->store('document/fines', 'public');
                    $fine->documents()->create(['url' => $path]);
                }
            }

            $response->update([
                'lawyer_status_id' => LawyerStatusEnum::ADMINISTRATIVE->value,
            ]);

            return $this->sendSuccess([], 'Data saved successfully');
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function status(): JsonResponse
    {
        if (request()->get('id')) {
            $register = DxaResponseStatus::findOrFail(request()->get('id'));
            return $this->sendSuccess(DxaStatusResource::make($register), 'Register successfully.');
        }
        $statuses = DxaResponseStatus::query()->get();
        return $this->sendSuccess(DxaStatusResource::collection($statuses), 'All registers status  successfully.');
    }

    public function getPDF(): JsonResponse
    {
        try {
            if (request('type') == 1) {
                $response = Http::timeout(15)
                    ->connectTimeout(5)
                    ->withBasicAuth(
                        config('app.mygov.login'),
                        config('app.mygov.password'),
                    )->get(config('app.mygov.linear') . '/get-pdf?id=' . request('id'));
                return $this->sendSuccess($response->json(), 'PDF file generated successfully.');

            }
            if (request('type') == 2) {
                $response = Http::timeout(15)
                    ->connectTimeout(5)
                    ->withBasicAuth(
                        config('app.mygov.login'),
                        config('app.mygov.password'),
                    )->get(config('app.mygov.url') . '/get-pdf?id=' . request('id'));
                return $this->sendSuccess($response->json(), 'PDF file generated successfully.');
            }
            return $this->sendSuccess(null, 'File not found');


        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function sendInspector(DxaResponseInspectorRequest $request): JsonResponse
    {
        try {
            $this->service->data = [
                'type' => $request->post('type'),
                'task_id' => $request->post('task_id'),
                'inspector_id' => $request->post('inspector_id'),
                'gnk_id' => $request->post('gnk_id'),
                'funding_source_id' => $request->post('funding_source_id'),
                'sphere_id' => $request->post('sphere_id'),
                'program_id' => $request->post('program_id'),
                'end_term_work' => $request->post('end_term_work'),
                'register_id' => $this->user->id
            ];

            $response = $this->service->sendInspector($this->user, $this->roleId);
            return $this->sendSuccess(DxaResponseResource::make($response), 'Inspector sent successfully.');

        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }

    }

    public function getExpertiseBlocks($id): JsonResponse
    {
        try {
            $response = DxaResponse::query()->findOrFaiL($id);
            $this->service->expertiseBlocks($response);
            return $this->sendSuccess(ResponseBlockResource::collection($response->blocks), 'Expertise blocks successfully.');
        } catch (\Exception $exception) {
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function checkFileInvest($id): JsonResponse
    {
        $response = DxaResponse::query()->findOrFail($id);
        $data = $this->service->checkHasFileInvest($response);
        return $this->sendSuccess($data, 'File invest successfully.');

    }

    public function sendInspection($id): JsonResponse
    {
        try {
            $response = DxaResponse::findOrFail($id);
            
            if (is_null($response->category_object_dictionary)) return $this->sendError('Murakkablik toifasi mavjud emas.');

            $file = $this->service->checkHasFileInvest($response);

            if ($file) {
                if (empty($file['contract_file_link'])) {
                    return $this->sendError('Invest kompaniyasi bilan shartnoma rasmiylashtirilmagan');
                }
                $response->update(['invest_response' => json_encode($file)]);
            }

            if ((int)$response->funding_source_id == 1) {
                $user = $response->supervisors()
                    ->where('role_id', UserRoleEnum::QURILISH->value)
                    ->first();

                if (!$user) {
                    return $this->sendError('Qurilish tashkiloti ma\'lumotlari topilmadi');
                }

                $tenderResponse = Http::timeout(10)
                    ->connectTimeout(5)
                    ->get("https://apisitender.mc.uz/api/tender-by-gnk-id", [
                    'gnk_id' => $response->gnk_id
                ]);

                if ($tenderResponse->successful()) {
                    $vendorTin = data_get($tenderResponse->json(), 'result.data.vendor_tin');
                    if ($user->stir_or_pinfl != $vendorTin) {
                        return $this->sendError('Qurilish tashkilotining STIR raqami tender platformadagi ma\'lumotlarga mos kelmadi');
                    }
//                    return $this->sendError('Tenderdan qurilish tashkiloti ma\'lumotlari topilmadi');
                }
            }

            $response->update([
                'dxa_response_status_id' => DxaResponseStatusEnum::INSPECTION,
                'send_operator' => null,
                'inspection_send_at' => now()
            ]);

            $this->service->saveHistory($response, $this->user, $this->roleId, LogType::SEND_INSPECTION);

            return $this->sendSuccess(
                DxaResponseResource::make($response),
                'Inspection sent successfully.'
            );

        } catch (\Exception $exception) {
            return $this->sendError(
                'Xatolik aniqlandi',
                $exception->getMessage()
            );
        }
    }


    public function rejectInspection($id): JsonResponse
    {
        try {
            $response = DxaResponse::query()->findOrFail($id);
            $data = $this->service->rejectInspection($this->user, $this->roleId, $response, request('comment'));

            return $this->sendSuccess(DxaResponseResource::make($data), 'Inspection sent successfully.');

        } catch (\Exception $exception) {
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function sendRegister(DxaResponseRegisterRequest $request): JsonResponse
    {
        try {
            $this->service->data = [
                'task_id' => $request->post('task_id'),
                'administrative_status_id' => $request->post('administrative_status_id'),
                'long' => $request->post('long'),
                'lat' => $request->post('lat'),
                'commit' => $request->post('commit'),
                'blocks' => $request->post('blocks') ?? null
            ];

            if ($request->hasFile('images')) {
                $this->service->data['images'] = $request->file('images');
            }

//            if ($request->hasFile('documents')){
//                $this->service->data['documents'] = $request->file('documents');
//            }

            $response = $this->service->sendRegister();

            return $this->sendSuccess(DxaResponseResource::make($response), 'Register successfully.');
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function sendUpdate()
    {
        DB::beginTransaction();
        try {
            $comment = request('comment');

            $response = DxaResponse::query()->where('id', request('id'))->first();
            $data = $this->service->updateMyGov($response, $comment);

            if ($data != 200) return $this->sendError('Mygovda xatolik aniqlandi', $data);

            $response->update(['dxa_response_status_id' => DxaResponseStatusEnum::SEND_UPDATE]);
            $this->service->saveHistory($response, $this->user, $this->roleId, LogType::SEND_UPDATE, $comment);

            DB::commit();
            return $this->sendSuccess([], 'Register successfully.');
        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function sphere(): JsonResponse
    {
        try {
            $client = new Client(['timeout' => 10, 'connect_timeout' => 5]);
            $apiCredentials = config('app.passport.login') . ':' . config('app.passport.password');

            $resClient = $client->post('https://api.shaffofqurilish.uz/api/v1/request/monitoring-soha',
                [
                    'headers' => [
                        'Authorization' => 'Basic ' . base64_encode($apiCredentials),
                    ],

                ]);

            $response = json_decode($resClient->getBody(), true);

            return $this->sendSuccess($response['result']['data']['data'], 'Sphere successfully.');
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function registerCount(): JsonResponse
    {

        try {
            $registers = $this->service->getRegisters($this->user, $this->roleId, 1);
            $filters = request()->only(['customer_name', 'funding_source', 'start_date', 'end_date', 'sphere_id', 'object_type', 'task_id', 'district_id', 'region_id']);
            $query = $this->service->searchRegisters($registers, $filters);
            $data = [
                'all' => $query->clone()->count(),
                'new' => $query->clone()->where('dxa_response_status_id', DxaResponseStatusEnum::NEW)->count(),
                'in_inspector' => $query->clone()->where('dxa_response_status_id', DxaResponseStatusEnum::SEND_INSPECTOR)->count(),
                'in_register' => $query->clone()->where('dxa_response_status_id', DxaResponseStatusEnum::IN_REGISTER)->count(),
                'accepted' => $query->clone()->where('dxa_response_status_id', DxaResponseStatusEnum::ACCEPTED)->count(),
                'rejected' => $query->clone()->where('dxa_response_status_id', DxaResponseStatusEnum::REJECTED)->count(),
                'cancelled' => $query->clone()->where('dxa_response_status_id', DxaResponseStatusEnum::CANCELED)->count(),
                'inspection' => $query->clone()->where('dxa_response_status_id', DxaResponseStatusEnum::INSPECTION)->count(),
                'send_update' => $query->clone()->where('dxa_response_status_id', DxaResponseStatusEnum::SEND_UPDATE)->count(),
            ];
            return $this->sendSuccess($data, 'Response count retrieved successfully.');
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function lawyerCount(): JsonResponse
    {
        try {
            $query = $this->service->getRegisters($this->user, $this->roleId, 1);

            $data = [
                'all' => $query->clone()->count(),
                'new' => $query->clone()->where('lawyer_status_id', LawyerStatusEnum::NEW->value)->count(),
                'process' => $query->clone()->where('lawyer_status_id', LawyerStatusEnum::PROCESS->value)->count(),
                'administrative' => $query->clone()->where('lawyer_status_id', LawyerStatusEnum::ADMINISTRATIVE->value)->count(),
                'disassembly' => $query->clone()->where('lawyer_status_id', LawyerStatusEnum::DISASSEMBLY->value)->count(),
            ];
            return $this->sendSuccess($data, 'Response count retrieved successfully.');
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function reRegisterCount(): JsonResponse
    {

        try {
            $registers = $this->service->getRegisters($this->user, $this->roleId, 2);
            $filters = request()->only(['customer_name', 'funding_source', 'start_date', 'end_date', 'sphere_id', 'status', 'object_type', 'task_id', 'district_id', 'region_id', 'lawyer_status']);
            $query = $this->service->searchRegisters($registers, $filters);

            $data = [
                'all' => $query->clone()->count(),
                'new' => $query->clone()->where('dxa_response_status_id', DxaResponseStatusEnum::NEW)->count(),
                'in_inspector' => $query->clone()->where('dxa_response_status_id', DxaResponseStatusEnum::SEND_INSPECTOR)->count(),
                'in_register' => $query->clone()->where('dxa_response_status_id', DxaResponseStatusEnum::IN_REGISTER)->count(),
                'accepted' => $query->clone()->where('dxa_response_status_id', DxaResponseStatusEnum::ACCEPTED)->count(),
                'rejected' => $query->clone()->where('dxa_response_status_id', DxaResponseStatusEnum::REJECTED)->count(),
                'cancelled' => $query->clone()->where('dxa_response_status_id', DxaResponseStatusEnum::CANCELED)->count(),
                'inspection' => $query->clone()->where('dxa_response_status_id', DxaResponseStatusEnum::INSPECTION)->count(),
                'send_update' => $query->clone()->where('dxa_response_status_id', DxaResponseStatusEnum::SEND_UPDATE)->count(),
            ];
            return $this->sendSuccess($data, 'Response count retrieved successfully.');
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function rejectRegister(DxaResponseRejectRequest $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $comment = $request->post('reject_comment');

            $response = DxaResponse::query()->where('task_id', $request->post('task_id'))->first();
            $data = $this->service->sendMyGovReject($response, $comment);

            if (!$data) return $this->sendError('Mygovda xatolik aniqlandi');

            $this->service->sendReject($response, $comment);

            $this->service->saveHistory($response, $this->user, $this->roleId, LogType::DXA_REJECTED, $comment);

            DB::commit();
            return $this->sendSuccess([], 'Register successfully.');
        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function oferta($id)
    {
        try {
            $dxa = DxaResponse::query()->findOrFail($id);
            $region = $dxa->region;
            $inspection = Inspection::query()->where('region_id', $dxa->region_id)->first();
            $rekvizit = Rekvizit::query()->where('district_id', $dxa->district_id)->first();
            $domain = 'https://nazorat.mc.uz/mygov/pdf' . $id;

            $qrImage = base64_encode(QrCode::format('png')->size(200)->generate($domain));

            $pdf = Pdf::loadView('pdf.object', compact('inspection', 'region', 'dxa', 'rekvizit', 'qrImage'));
            $pdfOutput = $pdf->output();
            $pdfBase64 = base64_encode($pdfOutput);
            return $this->sendSuccess($pdfBase64, 'PDF');
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function history($id)
    {
        try{
           $data =  $this->service->getHistory($id);
           return $this->sendSuccess($data, 'Response count retrieved successfully.');
        }catch (\Exception $exception){
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function getRegisterExcel($type)
    {
        try {

            $timestamp = time();
            $fileName = 'ariza' . $timestamp . '.xlsx';

            return Excel::download(new DxaResponseExport(service: $this->service, type: $type, user: $this->user, roleId: $this->roleId), $fileName);
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getLine());
        }
    }


}
