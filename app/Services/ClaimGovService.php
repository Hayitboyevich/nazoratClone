<?php

namespace App\Services;

use App\Enums\LogType;
use App\Enums\ObjectStatusEnum;
use App\Enums\RegulationStatusEnum;
use App\Enums\UserRoleEnum;
use App\Exceptions\NotFoundException;
use App\Helpers\ClaimStatuses;
use App\Helpers\MyGovOrganization;
use App\Http\Requests\ClaimGovAttachRequest;
use App\Http\Requests\ClaimGovConclusionDirectorRequest;
use App\Http\Requests\ClaimGovOrganizationRequest;
use App\Http\Requests\ClaimGovRejectRequest;
use App\Http\Requests\ClaimRequests\ConclusionClaimByInspector;
use App\Models\Article;
use App\Models\ClaimGov;
use App\Models\ClaimGovHistory;
use App\Models\ClaimGovOrganizationReview;
use App\Models\DeclarationConclusion;
use App\Models\DocumentSignature;
use App\Models\Monitoring;
use App\Models\Regulation;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Interfaces\ArticleRepositoryInterface;
use App\Repositories\Interfaces\ClaimGovRepositoryInterface;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class ClaimGovService
{
    const URL = 'https://oldmy.gov.uz/construction-acceptance-permit-v1/rest-api/';
    private HistoryService $historyService;

    public function __construct(
        protected ClaimGovRepositoryInterface $repository,
        protected EimzoService                $imzoService,
        protected ArticleRepositoryInterface $articleRepository,
        protected DeclarationService          $declarationService,
        protected ShaffofIdService             $shaffofIdService,
        private ClaimGovHistory $claimGovHistory,
    )
    {
        $this->historyService = new HistoryService('claim_gov_histories');
    }

    public function all($user, $roleId, $filters)
    {
        $query = $this->repository->all($user, $roleId);
        return $this->repository->filters($query, $roleId, $filters);
    }

    public function findById($id)
    {
        return $this->repository->findById($id);
    }

    public function count($user, $roleId, $filters)
    {
        try {
            $query = $this->all($user, $roleId, $filters);
            return [
                'all' => $query->clone()->count(),
                'new' => $query->clone()->where('status', ClaimStatuses::TASK_STATUS_ACCEPTANCE)->count(),
                'in_organization' => $query->clone()->where('status', ClaimStatuses::TASK_STATUS_SENT_ORGANIZATION)->count(),
                'in_inspector' => $query->clone()->where('status', ClaimStatuses::TASK_STATUS_INSPECTOR)->count(),
                'rejected_organization' => $query->clone()->where('status', ClaimStatuses::TASK_STATUS_ORGANIZATION_REJECTED)->count(),
                'rejected' => $query->clone()->where('status', ClaimStatuses::TASK_STATUS_REJECTED)->count(),
                'pay_progress' => $query->clone()->where('status', ClaimStatuses::PAY_PROGRESS)->count(),
                'in_director' => $query->clone()->where('status', ClaimStatuses::TASK_STATUS_DIRECTOR)->count(),
                'confirmed' => $query->clone()->where('status', ClaimStatuses::TASK_STATUS_CONFIRMED)->count(),
                'cancelled' => $query->clone()->where('status', ClaimStatuses::TASK_STATUS_CANCELLED)->count(),
            ];
        }catch (\Exception $exception){
            throw $exception;
        }
    }

    public function attachSphere(ClaimGovAttachRequest $request)
    {
        DB::beginTransaction();
        try {

            $data = [
                'claim_sphere_id' => $request->claim_sphere_id,
                'status' => ClaimStatuses::TASK_STATUS_SENT_ORGANIZATION
            ];

            $model = $this->repository->update(
                id: $request->id,
                data: $data
            );
            $monitoring = $this->repository->createMonitoring(
                claimGov: $model,
                organizations: json_encode($request['organizations'])
            );

            $this->historyService->createHistory(
                guId: $model->task_id,
                status: ClaimStatuses::TASK_STATUS_SENT_ORGANIZATION,
                type: LogType::TASK_HISTORY,
                date: null
            );
            $deadline = deadline(10, Carbon::now());

            $oldClaim = ClaimGov::query()->where('object_id', $model->object_id)->where('claim_sphere_id', $request['claim_sphere_id'])->whereIn('status', [15, 9])->latest()->first();
            if ($oldClaim) {
                $oldReviews = $oldClaim?->reviews()->where('status', true)->get();
                if ($oldReviews->count() > 0) {
                    foreach ($oldReviews as $oldReview) {
                        $jsonTable = DB::table('claim_gov_organization_reviews')->where('id', $oldReview->id)->first();
                        ClaimGovOrganizationReview::query()->create([
                            'claim_gov_id' => $model->id,
                            'organization_id' => $oldReview->organization_id,
                            'claim_sphere_id' => $model->claim_sphere_id,
                            'object_id' => $model->object_id,
                            'monitoring_id' => $monitoring->id,
                            'answer' => $jsonTable->answer,
                            'status' => $oldReview->status,
                            'deadline' => $oldReview->deadline,
                            'answered_at' => $oldReview->answered_at,
                            'user_id' => $oldReview->user_id,
                            'pkcs7' => $oldReview->pkcs7,
                            'created_at' => $oldReview->created_at,
                            'updated_at' => $oldReview->updated_at,
                        ]);

                        $apiUrl = "update/id/" . $model->task_id . "/action/conclusion-" . MyGovOrganization::URL[$oldReview->organization_id];
                        $requestData = json_decode(gzuncompress(base64_decode($jsonTable->answer)), true);

                        $dataArray['Conclusion' . MyGovOrganization::API[$oldReview->organization_id] . 'FormConstructionAcceptancePermit'] = $requestData;
                        $response = $this->postRequest($apiUrl, $dataArray);

                        if ($response->failed()){
                            throw  new \Exception('Mygovda xatolik yuz berdi!');
                        }

                    }
                }
            }

            if (!empty($request['organizations'])) {
                foreach ($request['organizations'] as $organization) {
                    if ($model->reviews->where('organization_id', $organization)->count() == 0) {
                        $this->repository->createOrganizationReview(
                            claimId: $model->id,
                            objectId: $model->object_id,
                            monitoringId: $monitoring->id,
                            organizationId: $organization,
                            deadline: $deadline,
                            sphereId: $model->claim_sphere_id
                        );
                    }

                }
            }

            $reviews = ClaimGovOrganizationReview::query()->where('claim_gov_id', $model->id)->get();
            list($isFinished, $allSuccess) = $this->checkReviewCount($reviews);

            if ($allSuccess && $isFinished) {
                $monitoring->update(['organization_answered_at' => $oldClaim?->monitoring?->organization_answered_at]);
                $this->repository->update(
                    $model->id,
                    [
                        'status' => ClaimStatuses::PAY_PROGRESS,
                        'deadline' => deadline(3, Carbon::now()),
                    ]
                );

                $dataArray['InspectorReportFormConstructionAcceptancePermit'] = [
                    'inspector_report' => '<a href="https://nazorat.mc.uz/claimgov/pdf/' . $model->id . '">Dalolatnoma</a>',
                ];

                $response = $this->postRequest("update/id/" . $model->task_id . "/action/inspector-report", $dataArray);

                if ($response->failed()) {
                    throw new \Exception('Mygovda xatolik yuz berdi');
                }

                $this->historyService->createHistory(
                    guId: $model->task_id,
                    status: ClaimStatuses::TASK_STATUS_INSPECTOR,
                    type: LogType::TASK_HISTORY,
                    date: null,
                    comment: 'Inspektorga Yuborildi!'
                );
            }

            DB::commit();
            return $model;
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    public function conclusion($user, $roleId, ClaimGovOrganizationRequest $request)
    {
        DB::beginTransaction();
        try {
//            $data = $this->imzoService->signTimestamp($request->pkcs7);
//            $is_yuridik = !empty($data['inn']);
//
//            if (empty($data)) throw new \Exception('E-imzo malumot olishda xatolik yuz berdi');
//
//            $director = $this->imzoService->checkDirector($data, $is_yuridik);
//
//            if ($director['inn'] != $user->pinfl) {
//                throw new \Exception('E-imzo egasi mos kelmadi');
//            }

            $document = DocumentSignature::query()
                ->where('guid', request('guid'))
                ->where('uuid', request('uuid'))
                ->where('user_id', $user->id)
                ->where('role_id', $roleId)
                ->first();

            if (!$document) {
                throw new \Exception('Tasdiqlash uchun hujjat topilmadi.');
            }

            if ($document->status !== 'signed') {
                $signed = $this->shaffofIdService->checkDocumentSign(
                    $user,
                    $roleId,
                    request('guid'),
                    request('uuid'),
                );

                if (!$signed) {
                    throw new \Exception('Hujjat imzolanmagan yoki tasdiqlashda xatolik yuz berdi.');
                }
            }
            $meta = ['uuid' => $document->uuid, 'guid' => $document->guid];

            $review = ClaimGovOrganizationReview::with('monitoring')->where('id', $request['review_id'])->first();
            $statusReview = $request['answer_type'] == 1;
            $objectRegion = $review->object?->region?->name_uz;
            $apiUrl = "update/id/" . $review->claim->task_id . "/action/conclusion-" . MyGovOrganization::URL[$review->organization_id];
            $apiType = MyGovOrganization::NOTE[$review->organization_id];

            if ($roleId == UserRoleEnum::MUALLIF->value) {
                $requestData = [
                    "comply_standards_authorspuer"  => $statusReview ? 'ijobiy' : 'salbiy',
                    "territory_inspectorate_authorsuper" => (in_array($review->organization_id, [6, 7, 8, 9, 10, 60, 61])) ? $objectRegion : $user?->region?->name_uz,
                    "responded_application_authorsuper"  => $user->name ? $user->name . ' ' . $user->surname : $user->organization_name,
                    "notes_authorspuer"  => $request['comment'],
                    "date_issuance_concluson_authorsuper"  => Carbon::now()->format('Y-m-d H:i:s')
                ];
            }else{
                $requestData = [
                    "comply_standards_" . $apiType => $statusReview ? 'ijobiy' : 'salbiy',
                    "territory_inspectorate_" . $apiType => (in_array($review->organization_id, [6, 7, 8, 9, 10, 61, 60])) ? $objectRegion : $user?->region?->name_uz,
                    "responded_application_" . $apiType => $user->name ? $user->name . ' ' . $user->surname : $user->organization_name,
                    "notes_" . $apiType => $request['comment'],
                    "date_issuance_concluson_" . $apiType => Carbon::now()->format('Y-m-d H:i:s')
                ];
            }

            $dataArray['Conclusion' . MyGovOrganization::API[$review->organization_id] . 'FormConstructionAcceptancePermit'] = $requestData;
            $response = $this->postRequest($apiUrl, $dataArray);

            if ($response->failed()) {
                throw new \Exception('Mygovda xatolik yuz berdi');
            }

            if ($statusReview && ($roleId == UserRoleEnum::MUALLIF->value || $roleId == UserRoleEnum::TEXNIK->value)) {
                $dataDeclaration = [
                    'object_id' => $request['object_id'],
                    'declaration_id' => $request['declaration_id'],
                    'user_id' => $user->id,
                    'role_id' => $roleId,
                    'order_number' => $request['order_number'],
                    'order_date' => $request['order_date'],
                    'contract_number' => $request['contract_number'],
                    'contract_date' => $request['contract_date'],
                    'comment' => $request['comment'],
                ];

                $declaration = $this->declarationService->concCreate($dataDeclaration);
            }

            $this->repository->updateConclusion(data: $requestData, user: $user, id: $review->id, status: $statusReview, pkcs7: $document->uuid);



//            $addInfo = ['director_full_name' => $director['full_name'] ?? $user->full_name, 'pin' => $director['pin']];

            $this->historyService->createHistory(
                guId: $review->claim->task_id,
                status: ClaimStatuses::TASK_STATUS_SENT_ORGANIZATION,
                type: LogType::TASK_HISTORY,
                date: null,
                comment: $request['comment'],
                additionalInfo: $meta
            );

            $reviews = ClaimGovOrganizationReview::query()->where('claim_gov_id', $review->claim_gov_id)->get();
            list($isFinished, $allSuccess) = $this->checkReviewCount($reviews);

            if ($isFinished) {
                $review->monitoring()->update(['organization_answered_at' => now()]);
                if ($allSuccess) {
                    $this->repository->update(
                        $review->claim_gov_id,
                        [
                            'status' => ClaimStatuses::PAY_PROGRESS,
                            'deadline' => deadline(3, Carbon::now()),
                        ]
                    );

                    $dataArray['InspectorReportFormConstructionAcceptancePermit'] = [
                        'inspector_report' => '<a href="https://nazorat.mc.uz/claimgov/pdf/' . $review->claim->id . '">Dalolatnoma</a>',
                    ];

                    $response = $this->postRequest("update/id/" . $review->claim->task_id . "/action/inspector-report", $dataArray);

                    if ($response->failed()) {
                        telegramNotification('Davlat obyektini topshirishda xatolik aniqlandi', $review->claim->task_id, $response->json());
                        throw new \Exception('Mygovda xatolik yuz berdi');
                    }

                    $this->historyService->createHistory(
                        guId: $review->claim->task_id,
                        status: ClaimStatuses::PAY_PROGRESS,
                        type: LogType::TASK_HISTORY,
                        date: null,
                        comment: 'To\'lov jarayonida!'
                    );
                }
                else {
                    $autoReject = $this->autoReject($review);
                    if (!$autoReject)
                        return false;

                    $this->repository->update(
                        $review->claim_gov_id,
                        [
                            'status' => ClaimStatuses::TASK_STATUS_ORGANIZATION_REJECTED,
                        ]
                    );

                    $this->historyService->createHistory(
                        guId: $review->claim->task_id,
                        status: ClaimStatuses::TASK_STATUS_ORGANIZATION_REJECTED,
                        type: LogType::TASK_HISTORY,
                        date: null,
                        comment: 'Ariza tashkilotlar tomonidan ijobiy xulosa taqdim etilmaganligi sababli rad etildi.'
                    );
                }
            }
            DB::commit();
            return $this->repository->findById($review->claim_gov_id);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }



    public function conclusionInspector(ConclusionClaimByInspector $request)
    {
        $claim = $this->findById($request['id']);

        if ($claim->status != ClaimStatuses::TASK_STATUS_INSPECTOR)
            return false;

        $claim->monitoring->update(
            [
                'inspector_answer' => $request['type'],
                'inspector_answered_at' => now(),
            ]
        );

        $claim->update(
            [
                'status' => ClaimStatuses::TASK_STATUS_DIRECTOR,
            ]
        );

        $this->historyService->createHistory(
            guId: $claim->task_id,
            status: ClaimStatuses::TASK_STATUS_DIRECTOR,
            type: LogType::TASK_HISTORY,
            date: null,
            comment: $request['comment']
        );

        return true;
    }

    public function autoReject($review)
    {
        $dataArray['RejectFormConstructionAcceptancePermit'] = [
            "gasn_name_reject" => 'Shaffof qurilish milliy axborot tizimi',
            "gasn_match" => 2,
            "reject" => 'Ariza tashkilotlar tomonidan ijobiy xulosa taqdim etilmaganligi sababli rad etildi.',
            "gasn_territory_reject" => $review->claim->region->name_uz
        ];

        $response = $this->postRequest("update/id/" . $review->claim->task_id . "/action/reject", $dataArray);

        if ($response->failed()) {
            telegramNotification('Davlat obyektni topshirish ariza auto rad qilindi mygov javob', $review->claim->task_id, $response->json());
            throw new \Exception('Mygovda xatolik yuz berdi');
        }

        return true;
    }

    public function conclusionDirector($user, $roleId, ClaimGovConclusionDirectorRequest $request)
    {
        DB::beginTransaction();
        try {
//            $data = $this->imzoService->signTimestamp($request->pkcs7);
//
//            if (empty($data)) throw new \Exception('E-imzo malumot olishda xatolik yuz berdi');
//
//            $director = $this->imzoService->checkDirector($data, $request->is_yuridik);
//
//            if ($director['inn'] != $user->pinfl) {
//                throw new \Exception('E-imzo egasi mos kelmadi');
//            }

            $document = DocumentSignature::query()
                ->where('guid', request('guid'))
                ->where('uuid', request('uuid'))
                ->where('user_id', $user->id)
                ->where('role_id', $roleId)
                ->first();

            if (!$document) {
                throw new \Exception('Tasdiqlash uchun hujjat topilmadi.');
            }

            if ($document->status !== 'signed') {
                $signed = $this->shaffofIdService->checkDocumentSign(
                    $user,
                    $roleId,
                    request('guid'),
                    request('uuid'),
                );

                if (!$signed) {
                    throw new \Exception('Hujjat imzolanmagan yoki tasdiqlashda xatolik yuz berdi.');
                }
            }
            $meta = ['uuid' => $document->uuid, 'guid' => $document->guid];

            $model = $this->repository->update($request['id'], ['status' => $request['status']]);

            $object = $this->articleRepository->findById($model->object_id);
            $previousStatus = $object->object_status_id;


            $this->repository->update($model->id, ['end_date' => Carbon::now()]);

            if ($model->status == ClaimStatuses::TASK_STATUS_CONFIRMED) {

                $dataArray['ResultFormConstructionAcceptancePermit'] = [
                    "gasn_name" => $user->name ? $user->name . ' ' . $user->surname : $user->organization_name,
                    "gasn_match" => 1,
                    "gasn_cause" => $request['comment'],
                    "gasn_territory" => $user?->region?->name_uz,
                    "date_issue_act_gasn" => Carbon::now()->format('Y-m-d H:i:s'),
                    "address_object_gasn" => $model->object->region->name_uz . ', ' . $model->object->district->name_uz . ', ' . $model->object->location_building,
                ];

                $response = $this->postRequest("update/id/" . $model->task_id . "/action/result", $dataArray);

                if ($response->status() != 200) {
                    throw new \Exception('Mygovda xatolik yuz berdi');
                }

                $object->update(
                    [
                        'object_status_id' => ObjectStatusEnum::SUBMITTED,
                        'closed_at' => Carbon::now()->format('Y-m-d H:i:s'),
                        'send_operator' => null,
                        'send_tax' => null,
                        'send_dshk' => null,
                    ]
                );

                $object->blocks()->update([
                    'accepted' => true
                ]);


                (new HistoryService('article_histories'))->createHistory(
                    guId: $object->id,
                    status: ObjectStatusEnum::SUBMITTED->value,
                    type: LogType::ARTICLE_HISTORY,
                    date: null,
                    comment: $request['comment'],
                    additionalInfo: [
                        'user_id' => $user->id ?? null,
                        'role_id' => $roleId ?? null,
                        'previous_status' => $previousStatus ?? null,
                        'new_status' => $object->object_status_id ?? null,
                        'meta' => $meta
                    ]
                );


            }
            if ($model->status == ClaimStatuses::TASK_STATUS_REJECTED) {

                $dataArray['RejectFormConstructionAcceptancePermit'] = [
                    "gasn_name_reject" => $user->name ? $user->name . ' ' . $user->surname : $user->organization_name,
                    "gasn_match" => 2,
                    "reject" => $request['comment'],
                    "gasn_territory_reject" => $user?->region?->name_uz
                ];

                $response = $this->postRequest("update/id/" . $model->task_id . "/action/reject", $dataArray);

                if ($response->failed()) {
                    throw new \Exception('Mygovda xatolik yuz berdi');
                }

                $this->repository->update($model->id, ['end_date' => Carbon::now()]);



            }
            $this->historyService->createHistory(
                guId: $model->task_id,
                status: $request['status'],
                type: LogType::TASK_HISTORY,
                date: null,
                comment: $request['comment'] ?? '',
                additionalInfo: $meta
            );
            DB::commit();
            return $model;
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    public function organizationPdf($id)
    {
        $review = $this->repository->organizationConclusion($id);
        $review->load(['organization', 'claim','object', 'object.region', 'object.district', 'user']);
        $domain = config('app.url')."/claim-gov/organization-pdf/$id/download";
        $qr_image = base64_encode(QrCode::format('png')->size(200)->generate($domain));
        $director_full_name = null;
        $task_id = $review->claim?->task_id;
        if ($review->organization_id == UserRoleEnum::FOYDALANUVCHI->value and $task_id){
            $history = $this->claimGovHistory
                ->where('gu_id', $task_id)
                ->whereJsonContains('content', ['role' => 60, 'status' => 4])
                ->first();
            $director_full_name = $history?->content?->additionalInfo?->director_full_name;
        }
        $pdf = Pdf::loadView('pdf.claim_gov_review', compact('review', 'qr_image', 'director_full_name'));
        $pdfContent = $pdf->output();
        return base64_encode($pdfContent);
    }

    public function getMyGovPdf($id)
    {
        try {
            $model = $this->repository->findById($id);

            $response = Http::withBasicAuth(config('app.mygov.login'), config('app.mygov.password'))->get(self::URL . 'get-pdf?id=' . $model->task_id);
            if ($response->failed()) {
                throw new \Exception('Mygovda xatolik yuz berdi');
            }
            return $response->json();

        } catch (\Exception $exception) {
            throw $exception;
        }
    }

    public function pdf($id)
    {
        $claimGov = $this->repository->findById($id);
        $object = $claimGov->object;
        $response = Http::withBasicAuth(config('app.mygov.login_mc'), config('app.mygov.password_mc'))
            ->get(config('app.mygov.url_mc') . "get-ds-info?id=" . $claimGov->task_id);
        $result = $response->json();
        Log::channel('daily')->log('info', $result);
        if ($object->id == 68381){
            $signed_info = [
                "CN" => "ILMURATOV ABROR UROLOVICH",
                "O" => "SAMARQAND VILOYATI HOKIMLIGI YAGONA BUYURTMACHI XIZMATI DM",
                "sign_date" => "13.06.2026 09:29:22",
            ];
        } elseif ($object->id == 68276){
            $signed_info = [
                "CN" => "ILMURATOV ABROR UROLOVICH",
                "O" => "SAMARQAND VILOYATI HOKIMLIGI YAGONA BUYURTMACHI XIZMATI DM",
                "sign_date" => "13.06.2026 11:04:07",
            ];
        } else {
            $signed_info = !empty($result["signatures"])?$result["signatures"][0]:null;
        }
        $domain = config('app.url') . '/api/pdf-dalolatnoma/' . $object->id;
        if (!empty($signed_info)){
            $qrImage = base64_encode(QrCode::format('png')->size(200)->generate($domain));
        } else {
            $qrImage = null;
        }
        $declaration = $claimGov->declaration;
        $designer = $object->users()->where('role_id', UserRoleEnum::LOYIHA->value)->first();
        $vendor = $object->users()->where('role_id', UserRoleEnum::QURILISH->value)->first();
        $pdf = Pdf::loadView('pdf.claim_gov', compact('qrImage','signed_info','claimGov', 'declaration', 'designer', 'vendor', 'object'));
        $pdfContent = $pdf->output();
        return base64_encode($pdfContent);
    }

    public function authorPdf($id)
    {
        $conclusion = DeclarationConclusion::query()->findOrFail($id);
        $pdf = Pdf::loadView('pdf.author-conclusion', compact('conclusion'));
        $pdfContent = $pdf->output();
        return base64_encode($pdfContent);
    }

    public function technicPdf($id)
    {
        $pdf = Pdf::loadView('pdf.technic-conclusion');
        $pdfContent = $pdf->output();
        return base64_encode($pdfContent);
    }

    public function reject($user, ClaimGovRejectRequest $request)
    {
        DB::beginTransaction();
        try {
            $data = [
                'status' => ClaimStatuses::TASK_STATUS_REJECTED,
                'end_date' => Carbon::now(),
            ];
//            $imzoData = $this->imzoService->signTimestamp($request->pkcs7);
//
//            if (empty($imzoData)) throw new \Exception('E-imzo malumot olishda xatolik yuz berdi');
//
//            $director = $this->imzoService->checkDirector($imzoData, $request->is_yuridik);
//
//            if ($director['inn'] != $user->pinfl) {
//                throw new \Exception('E-imzo egasi mos kelmadi');
//            }
            $model = $this->repository->update($request['id'], $data);

            $dataArray['RejectFormConstructionAcceptancePermit'] = [
                "gasn_name_reject" => $user->name ? $user->name . ' ' . $user->surname : $user->organization_name,
                "gasn_match" => 2,
                "reject" => $request['comment'],
                "gasn_territory_reject" => $user?->region?->name_uz
            ];

            $response = $this->postRequest("update/id/" . $model->task_id . "/action/reject", $dataArray);

            if ($response->failed()) {
                throw new \Exception('Mygovda xatolik yuz berdi');
            }

            $this->historyService->createHistory(
                guId: $model->task_id,
                status: ClaimStatuses::TASK_STATUS_REJECTED,
                type: LogType::TASK_HISTORY,
                date: null,
                comment: $request['comment'],
//                additionalInfo: $imzoData['pkcs7b64']
            );
            DB::commit();
            return $model;
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }

    }

    public function updateResponseStatus(int $taskId, int $status)
    {
        return $this->repository->updateResponseStatus(taskId: $taskId, status: $status);
    }

    public function findByTaskId($taskId)
    {
        return $this->repository->findByTaskId($taskId);
    }

    public function getClaimGov($taskId)
    {
        if ($taskId) {
            $response = $this->getRequest('get-task?id=' . $taskId);
            $consolidationDb = $this->findByTaskId($taskId);

            if ($response->status() != 200) {
                return null;
            }
            $data = $response->object();
            if (isset($data->task->id)) {
                $createdDate = Carbon::now();
                if ($data->task->current_node == "inactive")
                    $createdDate = $data->task->created_date;

                $expiryDate = deadline(1, $createdDate);
//                $expiryDate = Carbon::parse($expiryDate)
//                    ->endOfDay();
                if (!$consolidationDb) {
                    if ($data->task->status == 'new') {
                        $dataArray['AcceptFormConstructionAcceptancePermit'] = ['accept' => ' Ariza ko\'rib chiqishga qabul qilindi. nazorat.mc.uz'];
                        $success = $this->postRequest("update/id/" . $data->task->id . "/action/accept", $dataArray);

                        if ($success->status() != 200) {
                            return null;
                        }
                    }

                    return $this->repository->create($data, $expiryDate);

                } elseif ($data->task->current_node != $consolidationDb->current_node || $data->task->status != $consolidationDb->status_mygov) {
                    $status = ClaimStatuses::TASK_STATUS_ANOTHER;
                    if ($data->task->current_node == "inactive" && $data->task->status == "rejected") {
                        if ($data->entities->ConstructionAcceptancePermit->gasn_name_reject->real_value == 'Shaffof qurilish milliy axborot tizimi')
                            $status = ClaimStatuses::TASK_STATUS_ORGANIZATION_REJECTED;
                        else
                            $status = ClaimStatuses::TASK_STATUS_REJECTED;
                    }
                    if ($data->task->current_node == "inactive" && $data->task->status == "processed") {
                        $status = ClaimStatuses::TASK_STATUS_CONFIRMED;
                        $object = Article::query()->find($consolidationDb->object_id);
                        if ($object) {
                            if ($object->object_status_id != ObjectStatusEnum::SUBMITTED){
                                $this->updateObject($object, $data->task->last_update);
                            }
                        }
                    }
                    if ($data->task->current_node == "process" && $data->task->status == "statement-formation") {
                        $status = ClaimStatuses::TASK_STATUS_CONFIRMED;

                        $object = Article::query()->find($consolidationDb->object_id);
                        if ($object) {
                            if ($object->object_status_id != ObjectStatusEnum::SUBMITTED){
                                $this->updateObject($object, $data->task->last_update);
                            }
                        }
                    }
                    if ($data->task->current_node == "accept-conformation" && $data->task->status == "process") {
                        $status = ClaimStatuses::PAY_PROGRESS;
                    }
                    if ($data->task->current_node == "inactive" && $data->task->status == "not_active") {
                        $status = ClaimStatuses::TASK_STATUS_CANCELLED;
                    }
                    if ($data->task->current_node == "direction-statement-object") {
                        $status = ClaimStatuses::TASK_STATUS_ACCEPTANCE;
                    }
                    if ($data->task->current_node == "issuance-extract" && $data->task->status == "resend") {
                        $status = ClaimStatuses::TASK_STATUS_INSPECTOR;
                    }

                    $this->repository->update($consolidationDb->id, data: [
                        'current_node' => $data->task->current_node,
                        'status_mygov' => $data->task->status,
                        'status' => $status
                    ]);

                    return $consolidationDb;
                } else {
                    return null;
                }

            }
            return null;
        }
        return null;
    }

    private function updateObject($object, $date)
    {
        $object->update(
            [
                'object_status_id' => ObjectStatusEnum::SUBMITTED,
                'closed_at' => $date,
                'send_operator' => null,
                'send_tax' => null,
                'send_dshk' => null,
            ]
        );

        $object->blocks()->update([
            'accepted' => true
        ]);


        (new HistoryService('article_histories'))->createHistory(
            guId: $object->id,
            status: ObjectStatusEnum::SUBMITTED->value,
            type: LogType::ARTICLE_HISTORY,
            date: null,
            comment: '',
            additionalInfo: [
                'previous_status' => $previousStatus ?? null,
                'new_status' => $object->object_status_id ?? null,
            ]
        );
    }

    public function getRequest($url)
    {
        $response = Http::withBasicAuth(config('app.mygov.login'), config('app.mygov.password'))->timeout(10)->get(self::URL . $url);
        return $response;
    }

    public function postRequest($url, $data)
    {
        $response = Http::withBasicAuth(config('app.mygov.login'), config('app.mygov.password'))->timeout(20)->post(self::URL . $url, $data);
        return $response;
    }

    public function getOldReviews($objectId, $sphereId)
    {
        try {
            $oldClaim = ClaimGov::query()->where('object_id', $objectId)->where('claim_sphere_id', $sphereId)->whereIn('status', [15, 9])->latest()->first();
            if (!$oldClaim) return null;
            return $oldClaim?->reviews;
        } catch (\Exception $exception) {
            return null;
        }
    }

    private function checkReviewCount($reviews)
    {
        $countFinished = 0;
        $countSuccessFinished = 0;
        foreach ($reviews as $review) {
            if ($review->answered_at != null) {
                $countFinished++;
                if ($review->status)
                    $countSuccessFinished++;
            }
        }
        $isFinished = $countFinished == $reviews->count();
        $allSuccess = $countSuccessFinished == $reviews->count();
        return [$isFinished, $allSuccess];
    }

    public function getHistory($id)
    {
        $model = $this->repository->findById($id);
        return $model->histories->map(function ($history) {
            return [
                'id' => $history->id,
                'user' => $history->content->user ? User::query()->find($history->content->user, ['name', 'surname', 'middle_name', 'organization_name']) : null,
                'role' => $history->content->role ? Role::query()->find($history->content->role, ['id', 'name', 'description']) : null,
                'status' => $history?->content?->status,
                'comment' => $history->content->comment,
                'director_name' => $history->content->additionalInfo->director_name ?? null,
                'type' => $history->type,
                'created_at' => $history->created_at,
            ];
        })->sortBy('created_at')->values();
    }


    public function checkBlockStatus(int $claimId)
    {
        $errors = [];
        $claim = $this->repository->findById($claimId);
        $blocks = $claim->object->blocks;
        if ($claim->object->cost > 0)
            $errors[] = "Obyekt summasi to'liq to'lanmagan";

        foreach ($blocks as $block) {
            $monitorings = Monitoring::query()->where('block_id', $block->id)->get();

            foreach ($monitorings as $monitoring) {
                $reguluations = Regulation::query()
                    ->where('monitoring_id', $monitoring->id)
                    ->whereNotIn('regulation_status_id', [RegulationStatusEnum::LATE_EXECUTION, RegulationStatusEnum::ELIMINATED, RegulationStatusEnum::CANCELLED])
                    ->count();
                if ($reguluations > 0)
                    $errors[] = "Blok ($block->id)da $reguluations ta yopilmagan yozma ko'rsatma mavjud.";
            }

            if (!$block) {
                $errors[] = "Blok ($block->id) topilmadi.";
            } elseif ($block->status) {
                $errors[] = "Blok ($block->id) qurilish ishlari yakunlanmagan.";
            }
        }

        return $errors;
    }

    public function getRepoPDF($taskId)
    {
        $claim  = $this->findByTaskId($taskId);
        if (!$claim) throw new NotFoundException('Ariza topilmadi');

        $result = Http::withBasicAuth(config('app.mygov.login'), config('app.mygov.password'))->get(self::URL . "/get-repo-list?id=$taskId")->object();
        if (isset($result->guid) && $result->guid) {
            $file = Http::withBasicAuth(config('app.mygov.login'), config('app.mygov.password'))->get(self::URL . "/get-repo?guid=$result->guid")->object();

        } else {
            $result = (array)$result;
            if (!isset($result['status'])) {
                $count = count($result);
                if (isset($result[$count - 1]->guid) && $result[$count - 1]->guid)
                    $guid = $result[$count - 1]->guid;
                else
                    $guid = $result[0]->guid;
                $file = Http::withBasicAuth(config('app.mygov.login'), config('app.mygov.password'))->get( self::URL. "/get-repo?guid=$guid");
            } else
                throw new NotFoundException('Xulosa topilmadi');
        }
        return $file->json();
    }

    public function orgStatistic($user, $roleId)
    {
        try {
            $query = $this->repository->all($user, $roleId);
            $organizationId = $roleId == 61 ? 9 : $roleId;

            return [
                'total' => $query->clone()
                    ->whereHas('reviews', function ($q) use ($organizationId) {
                        $q->where('organization_id', $organizationId);
                    })
                    ->count(),

                'answered' => $query->clone()
                    ->whereHas('reviews', function ($q) use ($organizationId) {
                        $q->whereNotNull('answer')
                            ->where('organization_id', $organizationId);
                    })
                    ->count(),

                'not_answered' => $query->clone()
                    ->whereHas('reviews', function ($q) use ($organizationId) {
                        $q->whereNull('answer')->where("organization_id", $organizationId);
                    })
                    ->count(),
            ];

        } catch (\Exception $exception) {
            throw $exception;
        }
    }

}
