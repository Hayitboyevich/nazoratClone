<?php

namespace App\Services;

use App\Enums\LogType;
use App\Enums\ObjectStatusEnum;
use App\Enums\RegulationStatusEnum;
use App\Enums\UserRoleEnum;
use App\Enums\WorkTypeStatusEnum;
use App\Helpers\ClaimStatuses;
use App\Http\Requests\ClaimRequests\AcceptTask;
use App\Http\Requests\ClaimRequests\AttachBLockAndOrganization;
use App\Http\Requests\ClaimRequests\AttachObject;
use App\Http\Requests\ClaimRequests\ClaimSendToMinstroy;
use App\Http\Requests\ClaimRequests\ConclusionClaimByDirector;
use App\Http\Requests\ClaimRequests\ConclusionClaimByInspector;
use App\Http\Requests\ClaimRequests\ConclusionOrganization;
use App\Http\Requests\ClaimRequests\ManualConfirmDirector;
use App\Http\Requests\ClaimRequests\RejectClaimByOperator;
use App\Http\Requests\ClaimRequests\RejectFromDirector;
use App\Http\Requests\ClaimRequests\SendToDirector;
use App\Http\Requests\ClaimUpdateRequest;
use App\Http\Requests\ConclusionComplianceRequest;
use App\Models\Article;
use App\Models\Block;
use App\Models\Claim;
use App\Models\ClaimMonitoring;
use App\Models\ClaimOperator;
use App\Models\ClaimOrganizationReview;
use App\Models\DocumentSignature;
use App\Models\Monitoring;
use App\Models\Regulation;
use App\Models\Response;
use App\Models\Role;
use App\Repositories\Interfaces\ArticleRepositoryInterface;
use App\Repositories\Interfaces\ClaimRepositoryInterface;
use GuzzleHttp\Client;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PhpParser\Node\Stmt\TryCatch;


class ClaimService
{
    private ClaimRepositoryInterface $claimRepository;
    private HistoryService $historyService;
    private ArticleRepositoryInterface $articleRepository;

    private string $url = 'https://oldmy.gov.uz/completed-buildings-registration-cadastral-v2/rest-api/';

    public function __construct(
        protected ArticleService   $articleService,
        protected QuestionService  $questionService,
        protected EimzoService     $imzoService,
        protected ShaffofIdService $shaffofIdService,
        protected MyHomeService    $myHomeService,


        ClaimRepositoryInterface   $claimRepository,
        ArticleRepositoryInterface $articleRepository
    )
    {
        $this->claimRepository = $claimRepository;
        $this->articleRepository = $articleRepository;
        $this->historyService = new HistoryService('claim_histories');
    }

    public function getActiveResponses()
    {
        return $this->claimRepository->getActiveResponses();
    }

    public function getExpiredTaskList()
    {
        return $this->claimRepository->getExpiredTaskList();
    }


    public function getExpirationDate(string $startDate, int $duration): null|string
    {
        for ($day = 1; $day <= $duration; $day++) {
            $current = Carbon::parse($startDate)->addDays($day)->format('d-m-Y');

            if (Carbon::parse($startDate)->addDays($day)->isWeekend() || in_array($current, config('app.holidays'))) {
                $duration++;
            }
        }

        return Carbon::parse($startDate)->addDays($duration)->toDateString();
    }

    public function taskIsExpired(string $expirationDate): bool
    {
        $today = Carbon::today()->timestamp;
        $expirationDate = Carbon::parse($expirationDate)->timestamp;

        return $expirationDate <= $today;
    }

    public function updateTaskExpiration(int $guId, bool $isExpired, string $expirationDate): bool
    {
        return $this->claimRepository->updateClaim(
            guId: $guId,
            data: [
                'expired' => ($isExpired) ? 1 : 0,
                'expiry_date' => $expirationDate
            ]
        );
    }

    public function update(ClaimUpdateRequest $request)
    {
        return $this->claimRepository->updateClaim(
            guId: $request->guid,
            data: ['building_address' => $request->building_address],
        );
    }

    public function getClaimById(int $id, ?int $role_id)
    {
        return $this->claimRepository->getClaimById(id: $id, role_id: $role_id);
    }

    public function getClaimByTaskId($taskId)
    {
        return $this->claimRepository->getClaimByTaskId($taskId);
    }

    public function getObjects(int $id, $filters, ?string $type)
    {
        return $this->claimRepository->getObjects(id: $id, filters: $filters, type: $type);
    }


    public function getClaimByGUID(int $guid)
    {
        return $this->claimRepository->getClaimByGUID(guid: $guid);
    }


    public function getStatistics(?int $regionId, ?int $districtId)
    {
        return $this->claimRepository->getStatistics(regionId: $regionId, districtId: $districtId);
    }

    public function getTaskStatisticsRepeated(int $region = null): array
    {
        return $this->claimRepository->getStatisticsRepeated($region);
    }

    public function getStatisticsForInspector()
    {
        return $this->claimRepository->getStatisticsForInspector();
    }

    public function getStatisticsCount(
        ?int    $regionId,
        ?int    $districtId,
        ?int    $expired,
        ?string $dateFrom,
        ?string $dateTo,
    )
    {
        return $this->claimRepository->getStatisticsCount(
            regionId: $regionId,
            districtId: $districtId,
            expired: $expired,
            dateFrom: $dateFrom,
            dateTo: $dateTo
        );
    }

    public function getOrganizationStatistics(int $roleId, ?int $regionId, ?int $districtId, ?string $dateFrom, ?string $dateTo)
    {
        return $this->claimRepository->organizationStatistics(
            roleId: $roleId,
            regionId: $regionId,
            districtId: $districtId,
            dateFrom: $dateFrom,
            dateTo: $dateTo
        );
    }

    public function getTaskList(
        ?int    $regionId,
        ?int    $task_id,
        ?int    $object_task_id,
        ?string $name,
        ?string $customer,
        ?string $sender,
        ?int    $districtId,
        ?string $sortBy,
        ?int    $status,
        ?int    $expired,
        ?int    $role_id,
        ?string $start_date,
        ?string $end_date,
        ?int    $sphereId,
        ?int    $type,
    )
    {
        return $this->claimRepository->getList(
            regionId: $regionId,
            task_id: $task_id,
            object_task_id: $object_task_id,
            name: $name,
            customer: $customer,
            sender: $sender,
            districtId: $districtId,
            sortBy: $sortBy,
            status: $status,
            expired: $expired,
            role_id: $role_id,
            start_date: $start_date,
            end_date: $end_date,
            sphereId: $sphereId,
            type: $type,
        );
    }


    public function updateResponseStatus(int $guId, int $status)
    {
        return $this->claimRepository->updateResponseStatus(guId: $guId, status: $status);
    }


    public function getPDF(int $task_id)
    {
        $result = Http::withBasicAuth(config('app.mygov.login'), config('app.mygov.password'))->get($this->url . "get-pdf?id=$task_id");
        return $result->json();
    }

    public function getConclusionPDF(int $task_id)
    {
        $result = Http::withBasicAuth(config('app.mygov.login'), config('app.mygov.password'))->get($this->url . "get-repo-list?id=$task_id")->object();
        if (isset($result->guid) && $result->guid) {
            $file = Http::withBasicAuth(config('app.mygov.login'), config('app.mygov.password'))->get($this->url . "get-repo?guid=$result->guid")->object();

        } else {
            $result = (array)$result;
            if (!isset($result['status'])) {
                $count = count($result);
                if (isset($result[$count - 1]->guid) && $result[$count - 1]->guid)
                    $guid = $result[$count - 1]->guid;
                else
                    $guid = $result[0]->guid;
                $file = Http::withBasicAuth(config('app.mygov.login'), config('app.mygov.password'))->get($this->url . "get-repo?guid=$guid");
            } else
                return ['status' => 404];
        }
        return $file->json();
    }


    public function sendToMinstroy($guid, $comment): bool
    {
        $dataArray['SendObjectToMinstroyV2FormCompletedBuildingsRegistrationCadastral'] = [
            'comment_to_send_minstroy' => $comment,
        ];

        $claimObject = $this->getClaimByGUID(guid: $guid);

        $response = $this->PostRequest("update/id/" . $claimObject->gu_id . "/action/send-object-to-minstroy", $dataArray);
        if ($response->status() != 200) {
            return false;
        }

        $claimObject->update(
            [
                'status' => ClaimStatuses::TASK_STATUS_SENT_ANOTHER_ORG,
                'end_date' => Carbon::now()->format('Y-m-d H:i:s'),
                'elastic' => null,
            ]
        );

        $this->historyService->createHistory(
            guId: $claimObject->gu_id,
            status: (int)ClaimStatuses::TASK_STATUS_SENT_ANOTHER_ORG,
            type: LogType::TASK_HISTORY,
            date: null,
            comment: $comment
        );

        return true;
    }

    public function attachObject(AttachObject $request): bool
    {
        $claimObject = $this->getClaimById(id: $request['id'], role_id: null);

        if (!$claimObject)
            return false;

        $claimObject->update(
            [
                'object_id' => $request['object_id'],
                'elastic' => null,
            ]
        );

        $this->historyService->createHistory(
            guId: $claimObject->gu_id,
            status: ClaimStatuses::TASK_STATUS_ATTACH_OBJECT,
            type: LogType::TASK_HISTORY,
            date: null,
            comment: 'Obyekt biriktirildi! Obyekt ID raqami:' . $request['object_id']
        );

        return true;
    }

    public function attachBlockAndOrganization(AttachBLockAndOrganization $request): bool
    {
        DB::beginTransaction();
        try {
            $claimObject = $this->getClaimById(id: $request['id'], role_id: null);
            $blocks = $request['blocks'];
            sort($blocks);
            $blocksJson = json_encode($blocks);
            $oldMonitoring = ClaimMonitoring::query()
                ->where('object_id', $claimObject->object_id)
                ->whereRaw("blocks::jsonb = '$blocksJson'")
                ->orderBy('id', 'desc')
                ->first();


            if ($claimObject->monitoring != null)
                return true;

            if (!$claimObject)
                return false;

            if ($oldMonitoring) {
                $monitoring = $this->claimRepository->createMonitoring(
                    blocks: $blocks,
                    organizations: $request['organizations'],
                    id: $request['id'],
                    object_id: $claimObject->object_id);

                $reviews = ClaimOrganizationReview::query()->where('monitoring_id', $oldMonitoring->id)->get();
                $countReviewAnswers = 0;
                $addedOrganizations = [];

                foreach ($reviews as $review) {
                    $checkOrganizationId = match ($review->organization_id) {
                        15 => 45,
                        16 => 46,
                        default => $review->organization_id,
                    };
                    if ($review->status) {

                        if (in_array($checkOrganizationId, $request['organizations'])) {
                            $jsonTable = DB::table('claim_organization_reviews')->where('id', $review->id)->first();
                            $countReviewAnswers++;
                            $claimReview = ClaimOrganizationReview::query()->updateOrCreate(
                                [
                                    'claim_id' => $request['id'],
                                    'monitoring_id' => $monitoring->id,
                                    'organization_id' => $checkOrganizationId,
                                ],
                                [
                                    'expiry_date' => $review->expiry_date,
                                    'pkcs7' => $review->pkcs7,
                                    'expired' => $review->expired,
                                    'answer' => $jsonTable->answer,
                                    'status' => $review->status,
                                    'created_at' => $review->created_at,
                                    'updated_at' => $review->updated_at,
                                    'answered_at' => $review->answered_at,
                                ]
                            );

                            if ($checkOrganizationId == UserRoleEnum::NOGIRONLAR_JAM->value) {

                                $oldReview = ClaimOrganizationReview::query()->find($jsonTable->id);
                                $oldImages = $oldReview->images;

                                if ($oldImages?->isNotEmpty()) {
                                    foreach ($oldImages as $image) {
                                        $claimReview->images()->create([
                                            'url' => $image->url,
                                        ]);
                                    }
                                }
                            }

                            $jsonData = json_decode(gzuncompress(base64_decode($jsonTable->answer)), true);

                            $apiType = match ($checkOrganizationId) {
                                45 => 'mchs',
                                46 => 'ses',
                                17 => 'nogiron',
                                18 => 'nogiron2',
                                19 => 'kvartira',
                                34 => 'ekologiya'
                            };
                            $apiUrl = "update/id/" . $claimObject->gu_id . "/action/conclusion-" . $apiType;

                            $dataArray['Conclusion' . ucfirst($apiType) . 'V2FormCompletedBuildingsRegistrationCadastral'] = $jsonData;
                            $response = $this->PostRequest($apiUrl, $dataArray);

//                        if ($response->status() != 200) {
//                            return false;
//                        }

                            $addedOrganizations[] = $checkOrganizationId;
                        }
                    } else {
                        if (in_array($checkOrganizationId, $request['organizations'])) {
                            $this->claimRepository->createOrganizationReview(
                                claim_id: $request['id'],
                                monitoring_id: $monitoring->id,
                                organization_id: $checkOrganizationId,
                                expiry_date: $this->getExpirationDate(Carbon::now(), 3)
                            );

                            $addedOrganizations[] = $checkOrganizationId;
                        }
                    }
                }
                if ($countReviewAnswers == count($request['organizations'])) {
                    $claimObject->update(
                        [
                            'status' => ClaimStatuses::TASK_STATUS_INSPECTOR,
                            'elastic' => null,
                        ]
                    );

                    $this->historyService->createHistory(
                        guId: $claimObject->gu_id,
                        status: ClaimStatuses::TASK_STATUS_INSPECTOR,
                        type: LogType::TASK_HISTORY,
                        date: null
                    );
                } else {
                    foreach ($request['organizations'] as $organization) {
                        if (!in_array($organization, $addedOrganizations))
                            $this->claimRepository->createOrganizationReview(
                                claim_id: $request['id'],
                                monitoring_id: $monitoring->id,
                                organization_id: $organization,
                                expiry_date: $this->getExpirationDate(Carbon::now(), 3)
                            );
                    }

                    $claimObject->update(
                        [
                            'status' => ClaimStatuses::TASK_STATUS_SENT_ORGANIZATION,
                            'elastic' => null,
                        ]
                    );

                    $this->historyService->createHistory(
                        guId: $claimObject->gu_id,
                        status: ClaimStatuses::TASK_STATUS_SENT_ORGANIZATION,
                        type: LogType::TASK_HISTORY,
                        date: null
                    );
                }
            } else {
                $monitoring = $this->claimRepository->createMonitoring(
                    blocks: $blocks,
                    organizations: $request['organizations'],
                    id: $request['id'],
                    object_id: $claimObject->object_id);

                foreach ($request['organizations'] as $organization) {
                    $this->claimRepository->createOrganizationReview(
                        claim_id: $request['id'],
                        monitoring_id: $monitoring->id,
                        organization_id: $organization,
                        expiry_date: $this->getExpirationDate(Carbon::now(), 3)
                    );
                }

                $claimObject->update(
                    [
                        'status' => ClaimStatuses::TASK_STATUS_SENT_ORGANIZATION,
                        'elastic' => null,
                    ]
                );

                $this->historyService->createHistory(
                    guId: $claimObject->gu_id,
                    status: ClaimStatuses::TASK_STATUS_SENT_ORGANIZATION,
                    type: LogType::TASK_HISTORY,
                    date: null
                );
            }
            DB::commit();
            return true;
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }

    }

    public function checkBlockInProgress(array $blocks, $guid)
    {
        $errors = [];
        foreach ($blocks as $block) {
            $monitoring = ClaimMonitoring::query()
                ->with('claim')
                ->join('claims as c', 'c.id', '=', 'claim_monitoring.claim_id')
                ->whereJsonContains('blocks', $block)
                ->whereNotIn('c.status', [ClaimStatuses::TASK_STATUS_CONFIRMED, ClaimStatuses::TASK_STATUS_ORGANIZATION_REJECTED, ClaimStatuses::TASK_STATUS_REJECTED, ClaimStatuses::TASK_STATUS_CANCELLED])
                ->groupBy('c.id', 'claim_monitoring.id')
                ->select(['c.guid as guid'])
                ->pluck('c.guid')
                ->toArray();

            if (!$monitoring) {
                continue;
            }

            if (count($monitoring) > 1) {
                $errors[] = $block . " raqamli blokning jarayonda arizalari mavjud, Ariza raqamlari - " . implode(",", $monitoring);
                continue;
            }

            if ($monitoring[0] != $guid) {
                $errors[] = $block . " raqamli blokning jarayonda arizasi mavjud, Ariza raqami - " . implode(",", $monitoring);
            }
        }
        return $errors;
    }

    public function checkBlockStatus(int $claim_id)
    {
        $errors = [];
        $blocks = json_decode(ClaimMonitoring::query()->where('claim_id', $claim_id)->first()->blocks);
        $block = Block::find($blocks[0]);
        $article = Article::query()->where('id', $block->article_id)->first();
        if ($article->cost > 0)
            $errors[] = "Obyekt summasi to'liq to'lanmagan";

        foreach ($blocks as $blockId) {
            $block = Block::find($blockId);

            if ($block->status) {
                $this->updateBlock($blockId, $article);
            }

            $monitorings = Monitoring::query()->where('block_id', $blockId)->get();

            foreach ($monitorings as $monitoring) {
                $reguluations = Regulation::query()->where('monitoring_id', $monitoring->id)->whereNotIn('regulation_status_id', [RegulationStatusEnum::LATE_EXECUTION, RegulationStatusEnum::CANCELLED, RegulationStatusEnum::ELIMINATED])->count();
                if ($reguluations > 0)
                    $errors[] = "Blok ($blockId)da $reguluations ta yopilmagan yozma ko'rsatma mavjud.";
            }

            if (!$block) {
                $errors[] = "Blok ($blockId) topilmadi.";
            } elseif ($block->status) {
                $errors[] = "Blok ($blockId) qurilish ishlari yakunlanmagan.";
            }
        }

        return $errors;
    }


    private function updateBlock($blockId, $object)
    {
        $workTypes = $this->questionService->getQuestionList($blockId);
        $block = Block::query()->find($blockId);
        $count = 0;
        foreach ($workTypes as $workType) {
            if ($workType['questions'][0]['work_type_status'] == WorkTypeStatusEnum::CONFIRMED) {
                $count += 1;
            }
        }
        if ($count == count($workTypes)) {
            if ($object->sphere_id == 57) {
                $this->articleService->sendApartment($object);
            }
            $block->update([
                'status' => false
            ]);
        }
    }

    public function conclusionOrganization($user, $roleId, ConclusionOrganization $request)
    {
//        $data = $this->imzoService->signTimestamp($request->pkcs7);
//
//        if (empty($data)) throw new \Exception('E-imzo malumot olishda xatolik yuz berdi');
//
//        $director = $this->imzoService->checkDirector($data, false);
//
//        if ($director['inn'] != $user->pinfl) {
//            throw new \Exception('E-imzo egasi mos kelmadi');
//        }

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

        $reviewObject = ClaimOrganizationReview::with('monitoring')->where('id', $request['review_id'])->first();

        if (!$reviewObject || $reviewObject->answered_at != null)
            return false;


        $apiType = match ($reviewObject->organization_id) {
            45 => 'mchs',
            46 => 'ses',
            17 => 'nogiron',
            18 => 'nogiron2',
            19 => 'kvartira',
            34 => 'ekologiya'
        };

        $apiUrl = "update/id/" . $reviewObject->monitoring->claim->guid . "/action/conclusion-" . $apiType;

        $requestData = [
            $apiType . "_match" => $request['answer_type'],
            $apiType . "_territory" => (in_array($reviewObject->organization_id, [15, 16])) ? Auth::user()->district->name_uz : Auth::user()->region->name_uz,
            $apiType . "_name" => Auth::user()->name . ' ' . Auth::user()->surname,
            $apiType . "_conclusion_act" => $request['comment'],
            $apiType . "_datetime" => Carbon::now()->format('Y-m-d H:i:s')
        ];

        $statusReview = $request['answer_type'] == 1;

        $dataArray['Conclusion' . ucfirst($apiType) . 'V2FormCompletedBuildingsRegistrationCadastral'] = $requestData;
        $response = $this->PostRequest($apiUrl, $dataArray);

        if ($response->status() != 200) {
            return false;
        }

        $this->claimRepository->updateConclusionOrganization(data: $requestData, id: $reviewObject->id, status: $statusReview, pkcs7: $document->uuid, images: $request['images']);
        $role = Role::find($reviewObject->organization_id);

        $this->historyService->createHistory(
            guId: $reviewObject->monitoring->claim->guid,
            status: ClaimStatuses::TASK_STATUS_SENT_ORGANIZATION,
            type: LogType::TASK_HISTORY,
            date: null,
            comment: $role->name . ' xulosa berdi! Xulosa shakllantirgan shaxs: ' . $requestData[$apiType . '_name'],
            additionalInfo: $meta
        );

        $reviews = ClaimOrganizationReview::where('claim_id', $reviewObject->claim_id)->get();
        list($isFinished, $allSuccess) = $this->checkReviewCount($reviews);

        if ($isFinished) {
            if ($allSuccess) {
                $this->claimRepository->updateClaim(
                    $reviewObject->monitoring->claim->guid,
                    [
                        'status' => ClaimStatuses::TASK_STATUS_INSPECTOR,
                        'expiry_date' => deadline(4, Carbon::now()),
                        'elastic' => null,
                    ]
                );

                $this->historyService->createHistory(
                    guId: $reviewObject->monitoring->claim->guid,
                    status: ClaimStatuses::TASK_STATUS_INSPECTOR,
                    type: LogType::TASK_HISTORY,
                    date: null,
                    comment: 'Inspektorga Yuborildi!'
                );
            } else {
                $autoReject = $this->autoRejectByOrganization($reviewObject);

                if (!$autoReject)
                    return false;

                $this->historyService->createHistory(
                    guId: $reviewObject->monitoring->claim->guid,
                    status: ClaimStatuses::TASK_STATUS_ORGANIZATION_REJECTED,
                    type: LogType::TASK_HISTORY,
                    date: null,
                    comment: 'Ariza tashkilotlar tomonidan ijobiy xulosa taqdim etilmaganligi sababli rad etildi.'
                );
            }
        }

        return $this->claimRepository->getClaimById($reviewObject->monitoring->claim->id, Auth::user()->getRoleFromToken());
    }

    public function resend($user, $id, $comment)
    {
        try {
            $claim = $this->claimRepository->getClaimById($id, null);

            $dataArray['SendChangeFormCompletedBuildingsRegistrationCadastral'] = [
                'info_about_change' => $comment,
            ];

            $response = $this->PostRequest("update/id/" . $claim->gu_id . "/action/sending-change", $dataArray);

            if ($response->status() != 200) return new \Exception('Mygovda xatolik');

            $this->claimRepository->updateClaim(
                guId: $claim->gu_id,
                data: ['status' => ClaimStatuses::RESEND, 'resend' => true],
            );

            $this->historyService->createHistory(
                guId: $claim->gu_id,
                status: ClaimStatuses::RESEND,
                type: LogType::CLAIM_RESEND,
                date: null,
                comment: $comment
            );
            return $claim;
        } catch (\Exception $exception) {
            throw $exception;
        }
    }

    public function conclusionCompliance($user, $roleId, ConclusionComplianceRequest $request)
    {
        try {
            $claim = $this->claimRepository->getClaimById($request->id, null);
            if (!$claim) throw new \Exception('Ariza topilmadi!');

            if ($claim->monitoring == null) throw new \Exception('Arizada xatolik!');


            $data = [
                'compliance_status' => $request->status,
                'compliance_comment' => $request->comment,
                'questions' => $request->questions,
                'compliance_id' => $user->id,
                'compliance_answer_at' => now(),
            ];


            $claim->monitoring->update($data);


            $this->historyService->createHistory(
                guId: $claim->gu_id,
                status: $claim->status,
                type: LogType::COMPLIANCE_HISTORY,
                date: null,
                comment: 'Komplayns xulosa berdi.',
                additionalInfo: $data
            );

            return $claim;

        } catch (\Exception $exception) {
            throw $exception;
        }
    }

    private function autoRejectByOrganization($reviewObject): bool
    {
        if ($reviewObject->monitoring->claim->current_node == 'answer-other-institutions') {
            $dataArray['SendToStepConclusionGasnV2FormCompletedBuildingsRegistrationCadastral'] = [
                'comment_gasn' => 'Ariza tashkilotlar tomonidan ijobiy xulosa taqdim etilmaganligi sababli rad etildi.',
            ];

            $response = $this->PostRequest("update/id/" . $reviewObject->monitoring->claim->guid . "/action/send-to-step-conclusion-gasn", $dataArray);

            telegramNotification('Foydalanishga topshirish ariza auto rad qilindi mygov javob', $reviewObject->monitoring->claim->guid, $response->json());


            if ($response->status() != 200) {
                return false;
            }
        }

        $dataArray['IssuanceExtractRejectGasnV2FormCompletedBuildingsRegistrationCadastral'] = [
            "gasn_name_reject" => 'Shaffof qurilish milliy axborot tizimi',
            "gasn_match" => 2,
            "gasn_cause_reject" => 'Ariza tashkilotlar tomonidan ijobiy xulosa taqdim etilmaganligi sababli rad etildi.',
            "gasn_territory_reject" => $reviewObject->monitoring->claim->region()->first()->name_uz
        ];

        $response = $this->PostRequest("update/id/" . $reviewObject->monitoring->claim->guid . "/action/issuance-extract-reject-gasn", $dataArray);

        if ($response->status() != 200) {
            return false;
        }

        $reviewObject->monitoring->claim->update(
            [
                'status' => ClaimStatuses::TASK_STATUS_ORGANIZATION_REJECTED,
                'end_date' => Carbon::now()->format('Y-m-d H:i:s'),
                'elastic' => null,
            ]
        );

        return true;
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

    public function acceptTask(AcceptTask $request): bool
    {
        $dataArray['SendObjectToGasnV2FormCompletedBuildingsRegistrationCadastral'] = [
            'comment_to_send_gasn' => $request['comment'],
        ];

        $claimObject = $this->getClaimByGUID(guid: $request['guid']);

        $response = $this->PostRequest("update/id/" . $claimObject->gu_id . "/action/send-object-to-gasn", $dataArray);
        if ($response->status() != 200) {
            return false;
        }

        $claimObject->update(
            [
                'status' => ClaimStatuses::TASK_STATUS_ATTACH_OBJECT,
                'elastic' => null,
            ]
        );

        $this->historyService->createHistory(
            guId: $claimObject->gu_id,
            status: ClaimStatuses::TASK_STATUS_ATTACH_OBJECT,
            type: LogType::TASK_HISTORY,
            date: null,
            comment: $request['comment']
        );

        return true;
    }

    public function manualConfirmByDirector(ManualConfirmDirector $request, $user, $roleId)
    {
        $data = $this->imzoService->signTimestamp($request['pkcs7']);

        if (empty($data)) throw new \Exception('E-imzo malumot olishda xatolik yuz berdi');

        $director = $this->imzoService->checkDirector($data, false);

        if ($director['inn'] != $user->pinfl) {
            throw new \Exception('E-imzo egasi mos kelmadi');
        }

        $objectModel = $this->articleRepository->findById($request['id']);
        $previousStatus = $objectModel->object_status_id;
        if ($objectModel->object_status_id != ObjectStatusEnum::PROGRESS)
            return false;

        $blocks = $objectModel->blocks()->get();
//        $blockError = 0;
//        foreach ($blocks as $val) {
//            $block = Block::find($val->id);
//
//            if ($block->status) {
//                $blockError++;
//            }
//        }
//
//        if ($blockError > 0)
//            return false;

        $path = $request->file->store('documents/object', 'public');

        $this->claimRepository->manualConfirmByDirector(object_id: $request['id'], comment: $request['comment'], file: $path);

        $objectModel->update(
            [
                'object_status_id' => ObjectStatusEnum::SUBMITTED,
                'closed_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'send_operator' => null,
                'send_tax' => null,
                'send_dshk' => null,

            ]
        );

        $this->articleService->storeElasticSearch($objectModel);

        foreach ($blocks as $val) {
            $block = Block::find($val->id);

            $block->update(
                [
                    'accepted' => true
                ]
            );
        }

        if ($objectModel->sphere_id == 57) {
            $this->articleService->sendApartment($objectModel);
        }

        (new HistoryService('article_histories'))->createHistory(
            guId: $request['id'],
            status: ObjectStatusEnum::SUBMITTED->value,
            type: LogType::ARTICLE_HISTORY,
            date: null,
            comment: $request['comment'],
            additionalInfo: [
                'user_id' => $user->id ?? null,
                'role_id' => $roleId ?? null,
                'previous_status' => $previousStatus ?? null,
                'new_status' => $objectModel->object_status_id ?? null,
                'pkcs7' => $data['pkcs7b64']
            ]
        );

        return true;
    }

    public function sendToDirector(SendToDirector $request)
    {
        $claimObject = $this->getClaimById(id: $request['id'], role_id: null);

        $claimObject->monitoring->update(
            [
                'operator_answer' => base64_encode(gzcompress(json_encode($request['buildings']), 9))
            ]
        );

        ClaimOperator::query()->updateOrCreate(
            ['claim_id' => $claimObject->id],
            ['operator_answer' => json_encode($request['buildings'])]
        );

        if ($claimObject->status != ClaimStatuses::TASK_STATUS_CONFIRMED) {
            $claimObject->update(
                [
                    'status' => ClaimStatuses::TASK_STATUS_DIRECTOR,
                    'elastic' => null,
                ]
            );
        }

        $this->historyService->createHistory(
            guId: $claimObject->gu_id,
            status: ClaimStatuses::TASK_STATUS_DIRECTOR,
            type: LogType::TASK_HISTORY,
            date: null,
            comment: 'Operator boshliqga yubordi!'
        );

        return true;
    }

    public function rejectFromDirector($user, $roleId, RejectFromDirector $request)
    {
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

        $claimObject = $this->getClaimById(id: $request['id'], role_id: null);

        if ($claimObject->status != ClaimStatuses::TASK_STATUS_DIRECTOR)
            return false;

        $claimObject->update(
            [
                'status' => $request['to_role'],
                'elastic' => null,
            ]
        );

        $this->historyService->createHistory(
            guId: $claimObject->gu_id,
            status: $request['to_role'],
            type: LogType::TASK_HISTORY,
            date: null,
            comment: $request['comment'],
            additionalInfo:$meta
        );

        return true;
    }

    public function rejectClaimByOperator(RejectClaimByOperator $request)
    {
        $claimObject = $this->getClaimById(id: $request['id'], role_id: null);

        $dataArray['SendToStepConclusionGasnV2FormCompletedBuildingsRegistrationCadastral'] = [
            'comment_gasn' => 'Keyingi qadam',
        ];

        $response = $this->PostRequest("update/id/" . $claimObject->gu_id . "/action/send-to-step-conclusion-gasn", $dataArray);

        if ($response->status() != 200) {
            return false;
        }

        $dataArray['IssuanceExtractRejectGasnV2FormCompletedBuildingsRegistrationCadastral'] = [
            "gasn_name_reject" => Auth::user()->name . ' ' . Auth::user()->surname,
            "gasn_match" => 2,
            "gasn_cause_reject" => $request['comment'],
            "gasn_territory_reject" => Auth::user()->region->name_uz
        ];

        $response = $this->PostRequest("update/id/" . $claimObject->gu_id . "/action/issuance-extract-reject-gasn", $dataArray);

        if ($response->status() != 200) {
            return false;
        }


        $claimObject->update(
            [
                'status' => ClaimStatuses::TASK_STATUS_REJECTED,
                'end_date' => Carbon::now()->format('Y-m-d H:i:s'),
                'elastic' => null,
            ]
        );

        $this->historyService->createHistory(
            guId: $claimObject->gu_id,
            status: ClaimStatuses::TASK_STATUS_REJECTED,
            type: LogType::TASK_HISTORY,
            date: null,
            comment: $request['comment']
        );

        return true;
    }

    public function  conclusionByDirector(ConclusionClaimByDirector $request, $user, $roleId)
    {
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

            $claimObject = $this->getClaimById(id: $request['id'], role_id: null);

            if ($claimObject->status != ClaimStatuses::TASK_STATUS_DIRECTOR)
                return false;


            $histories = $this->historyService->getFilteredList(guId: $claimObject->gu_id, jsonColumn: 'role', needle: 3);
            $historiesNew = $this->historyService->getFilteredList(guId: $claimObject->gu_id, jsonColumn: 'status', needle: 12);
            if (isset($histories[0]))
                $lastInspectorConclusion = json_decode($histories[0]->content, true);
            elseif (isset($historiesNew[0]))
                $lastInspectorConclusion = json_decode($historiesNew[0]->content, true);
            else
                $lastInspectorConclusion['comment'] = $request['comment'];

            $dataArray['SendToStepConclusionGasnV2FormCompletedBuildingsRegistrationCadastral'] = [
                'comment_gasn' => $lastInspectorConclusion['comment'],
            ];

            if ($claimObject->current_node != 'conclusion-gasn') {
                $response = $this->PostRequest("update/id/" . $claimObject->gu_id . "/action/send-to-step-conclusion-gasn", $dataArray);

                if ($response->status() != 200) {
                    return false;
                }
            }
            $object = $this->articleRepository->findById($claimObject->object_id);

            if ($request['type'] == 15) {

                $dataArray['IssuanceExtractRejectGasnV2FormCompletedBuildingsRegistrationCadastral'] = [
                    "gasn_name_reject" => Auth::user()->name . ' ' . Auth::user()->surname,
                    "gasn_match" => 2,
                    "gasn_cause_reject" => $request['comment'],
                    "gasn_territory_reject" => Auth::user()->region->name_uz
                ];


                $response = $this->PostRequest("update/id/" . $claimObject->gu_id . "/action/issuance-extract-reject-gasn", $dataArray);

                if ($response->status() != 200) {
                    return false;
                }

                $claimObject->update(
                    [
                        'status' => ClaimStatuses::TASK_STATUS_REJECTED,
                        'end_date' => Carbon::now()->format('Y-m-d H:i:s'),
                        'elastic' => null,
                    ]
                );

                $this->historyService->createHistory(
                    guId: $claimObject->gu_id,
                    status: ClaimStatuses::TASK_STATUS_REJECTED,
                    type: LogType::TASK_HISTORY,
                    date: null,
                    comment: $request['comment'],
                    additionalInfo: $meta
                );
            } else {
                $tableHtml = $this->generateTable($claimObject);

                if ($request['type'] == 100) {
                    $file = $request->file('file');

                    $fileBase64 = null;
                    $filePath = null;

                    if ($file) {
                        $filePath = $file->store('claim_files', 'public');

                        $fileBase64 = base64_encode(file_get_contents($file->getRealPath()));
                    }
                    $dataArray['ConclusionGasnV2StopFormCompletedBuildingsRegistrationCadastral'] = [
                        "inform_stopped" => "O‘zbekiston Respublikasi Bosh vazirining o‘rinbosari A.Ramatovning 2026-yil 6-yanvardagi 05/1-584-son topshirig‘iga asosan",
                        "gasn_match" => 1,
                        "date_issue_act_gasn" => Carbon::now()->format('Y-m-d H:i:s'),
                        "object_project_gasn" => [
                            "target" => "file",
                            "ext" => "jpg",
                            "file" => "iVBORw0KGgoAAAANSUhEUgAAAPgAAADLCAMAAAB04a46AAAAKlBMVEX///8AAABMTEw/Pz+cnJz19fU6OjpDQ0NLS0tISEhycnIMDAyampqsrKz4G7DdAAABLUlEQVR4nO3UyRHCQAwAQbABL1f+6QIPKEfAPqYnAnVJpcNBtZ7bGEur6+0DPx17jSp8+cLXUNt9B5/3YSa07uDn2cP8sws4eCNw8Ejg4JHAwSOBg0cCB48EDh4JHDwSOHgkcPBI4OCRwMEjgYNHAgePBA4eCRw8Ejh4JHDwSODgkcDBI4GDRwIHjwQOHgkcPBI4eCRw8Ejg4JHAwSOBg0cCB48EDh4JHDwSOHgkcPBI4OCRwMEjgYNHAgePBA4eCRw8Ejh4JHDwSODgkcDBI4GDRwIHjwQOHgkcPBI4eCRw8Ejg4JHAwSOBg0cCB48EDh4JHDwSOHgkcPBI4OCRwMEjgYNHAgePBF6FP97w2bP8tXW38TXUdt/Ba/1Ovdb4wJ/bMpZW19u8D6NZvQAHUx5B5LstjAAAAABJRU5ErkJggg=="
                        ],
                        "address_object_gasn" => $claimObject->object->region->name_uz . ', ' . $claimObject->object->district->name_uz . ', ' . $claimObject->building_address,
                        "buildings_title_documents_gasn" => $tableHtml,
                        "gasn_cause" => $request['comment'],
                        "gasn_name" => Auth::user()->name . ' ' . Auth::user()->surname,
                        "gasn_territory" => Auth::user()->region->name_uz,
                        "guarantee_letters" => [
                            "target" => "file",
                            "ext" => $file ? $file?->getClientOriginalExtension() : null,
                            "file" => $fileBase64,
                        ],
                    ];

                    $response = $this->PostRequest("update/id/" . $claimObject->gu_id . "/action/conclusion-gasn-stop", $dataArray);

                    telegramNotification('Foydalanishga topshirish ariza kadastrga yuborilmadi mygov javobi', $claimObject->gu_id, $response->json());


                    if ($response->status() != 200) {
                        return false;
                    }
                } else {
                    $dataArray['ConclusionGasnV2FormCompletedBuildingsRegistrationCadastral'] = [
                        "gasn_name" => Auth::user()->name . ' ' . Auth::user()->surname,
                        "gasn_match" => 1,
                        "gasn_cause" => $request['comment'],
                        "gasn_territory" => Auth::user()->region->name_uz,
                        "date_issue_act_gasn" => Carbon::now()->format('Y-m-d H:i:s'),
                        "object_project_gasn" => [
                            "target" => "file",
                            "ext" => "jpg",
                            "file" => "iVBORw0KGgoAAAANSUhEUgAAAPgAAADLCAMAAAB04a46AAAAKlBMVEX///8AAABMTEw/Pz+cnJz19fU6OjpDQ0NLS0tISEhycnIMDAyampqsrKz4G7DdAAABLUlEQVR4nO3UyRHCQAwAQbABL1f+6QIPKEfAPqYnAnVJpcNBtZ7bGEur6+0DPx17jSp8+cLXUNt9B5/3YSa07uDn2cP8sws4eCNw8Ejg4JHAwSOBg0cCB48EDh4JHDwSOHgkcPBI4OCRwMEjgYNHAgePBA4eCRw8Ejh4JHDwSODgkcDBI4GDRwIHjwQOHgkcPBI4eCRw8Ejg4JHAwSOBg0cCB48EDh4JHDwSOHgkcPBI4OCRwMEjgYNHAgePBA4eCRw8Ejh4JHDwSODgkcDBI4GDRwIHjwQOHgkcPBI4eCRw8Ejg4JHAwSOBg0cCB48EDh4JHDwSOHgkcPBI4OCRwMEjgYNHAgePBF6FP97w2bP8tXW38TXUdt/Ba/1Ovdb4wJ/bMpZW19u8D6NZvQAHUx5B5LstjAAAAABJRU5ErkJggg=="
                        ],
                        "address_object_gasn" => $claimObject->object->region->name_uz . ', ' . $claimObject->object->district->name_uz . ', ' . $claimObject->building_address,
                        "buildings_title_documents_gasn" => $tableHtml
                    ];

                    $response = $this->PostRequest("update/id/" . $claimObject->gu_id . "/action/conclusion-gasn", $dataArray);

                    telegramNotification('Foydalanishga topshirish ariza yakunlandi mygov javobi', $claimObject->gu_id, $response->json());


                    if ($response->status() != 200) {
                        return false;
                    }
                }


                $claimObject->update(
                    [
                        'status' => ClaimStatuses::TASK_STATUS_CONFIRMED,
                        'end_date' => Carbon::now()->format('Y-m-d H:i:s'),
                        'cadastr_stop_file' => $filePath ?? null,
                        'elastic' => null,
                    ]
                );

                foreach ($claimObject->blocks as $block) {
                    $blockId = $block['id'];
                    $blockModel = Block::find($blockId);

                    $blockModel->update(
                        [
                            'accepted' => true
                        ]
                    );
                }

                $checkBLocks = Block::query()->where('article_id', $claimObject->object_id)->get();
                $countBlocks = 0;
                foreach ($checkBLocks as $checkBLock) {
                    if ($checkBLock->accepted)
                        $countBlocks++;
                }

                $previousStatus = $object->object_status_id;


                if ($countBlocks == $checkBLocks->count()) {
                    $object->update(
                        [
                            'object_status_id' => ObjectStatusEnum::SUBMITTED,
                            'closed_at' => Carbon::now()->format('Y-m-d H:i:s'),
                            'send_operator' => null,
                            'send_tax' => null,
                            'send_dshk' => null,
                        ]
                    );

                    $this->articleService->storeElasticSearch($object);
                }

                if ($object->sphere_id == 57) {
                    $this->articleService->sendApartment($object);
                }

                (new HistoryService('article_histories'))->createHistory(
                    guId: $claimObject->object_id,
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

                $this->historyService->createHistory(
                    guId: $claimObject->gu_id,
                    status: ClaimStatuses::TASK_STATUS_CONFIRMED,
                    type: LogType::TASK_HISTORY,
                    date: null,
                    comment: $request['comment'],
                    additionalInfo: $meta

                );
            }

            if ($claimObject->monitoring != null)
                $claimObject->monitoring->update(
                    [
                        'director_answer' => $request['type']
                    ]
                );

            return true;
        } catch (\Exception $exception) {
            dd($exception->getMessage());
        }

    }

    public function generateTable($claim)
    {
        $operatorBlocks = $claim->monitoring->operator_field;
        if (!$operatorBlocks) return false;

        $tableHtml = '';

        if ($claim->source_type == 'MKD') {

            $type1 = collect($operatorBlocks)
                ->first(fn($item) => ($item['type'] ?? null) == 1);
            $type11 = collect($operatorBlocks)
                ->first(fn($item) => ($item['type'] ?? null) == 11);

            $type12 = collect($operatorBlocks)
                ->first(fn($item) => ($item['type'] ?? null) == 12);
            $type13 = collect($operatorBlocks)
                ->first(fn($item) => ($item['type'] ?? null) == 13);
            $type14 = collect($operatorBlocks)
                ->first(fn($item) => ($item['type'] ?? null) == 14);
            $tableHtml .= '
                    <p style="text-align: center; font-weight: bold; margin: 8px 0;">1. Obyekt haqida ma\'lumotlar</p>
                    <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px;">
                        <tr>
                            <td style="border: 1px solid #000; padding: 4px 6px; width: 28%;">Yer uchastkasining kadastr raqami</td>
                            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($claim->building_cadastral ?? '') . '</td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid #000; padding: 4px 6px;">Obyekt nomi</td>
                            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($claim->object->name ?? '') . '</td>
                        </tr>
                    </table>
                    ';

            if (isset($type1)) {
                $tableHtml .= '
                    <p style="text-align: center; font-weight: bold; margin: 8px 0;">1.1. Manzil haqida ma\'lumotlar</p>

                    <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px;">
                        <tr>
                            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; width: 22%; text-align: center;">
                                Bino va inshootlar kadastr raqami
                            </td>
                            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; width: 22%; text-align: center;">
                                Bino va inshootlar nomi
                            </td>
                            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center;">
                                Bino va inshootlar manzili
                            </td>
                        </tr>

                        <tr>
                            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type1["cadaster"] ?? '') . '</td>
                            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type1["name"] ?? '') . '</td>
                            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($claim->building_address ?? '') . '</td>
                        </tr>
                    </table>
                    ';
            }


            if (isset($type11)) {
                $tableHtml .= '
                        <p style="text-align: center; font-weight: bold; margin: 8px 0;">
                            2. Yer uchastkasi ma\'lumotlari
                        </p>

                        <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px;">
                            <tr>
                                <td style="border: 1px solid #000; padding: 4px 6px;">
                                    Umumiy Yer maydoni, (kv.m)
                                </td>
                                <td style="border: 1px solid #000; padding: 4px 6px; width: 15%; text-align: center;">
                                    ' . ($type11["block1_total_area"] ?? '') . '
                                </td>
                            </tr>

                            <tr>
                                <td style="border: 1px solid #000; padding: 4px 6px;">
                                    Umumiy qurilish osti maydoni, (kv.m)
                                </td>
                                <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">
                                    ' . ($type11["block1_area"] ?? '') . '
                                </td>
                            </tr>
                        </table>
                    ';
            }

            if (isset($type1)) {
                $tableHtml .= '
                        <p style="text-align: center; font-weight: bold; margin: 8px 0;">
                            3. Bino va inshootlar ma\'lumotlari
                        </p>

                        <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px;">
                            <tr>
                                <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center; vertical-align: middle; width: 18%;" rowspan="2">
                                    Bino va inshootlar kadastr raqami
                                </td>
                                <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center; vertical-align: middle; width: 18%;" rowspan="2">
                                    Bino va inshootlar nomi
                                </td>
                                <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center; vertical-align: middle; width: 14%;" rowspan="2">
                                    Qurilish holati
                                </td>
                                <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center; vertical-align: middle; width: 8%;" rowspan="2">
                                    Qavatlar soni
                                </td>
                                <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center;" colspan="4">
                                    Maydoni (kv.m.)
                                </td>
                            </tr>

                            <tr>
                                <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; text-align: center;">
                                    Qurilish osti maydoni
                                </td>
                                <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; text-align: center;">
                                    Umumiy maydoni
                                </td>
                                <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; text-align: center;">
                                    Umumiy foydali maydoni
                                </td>
                                <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; text-align: center;">
                                    Shundan yashash maydoni
                                </td>
                            </tr>

                            <tr>
                                <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type1["cadaster"] ?? '') . '</td>
                                <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type1["name"] ?? '') . '</td>
                                <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">Topshirishga tayyor</td>
                                <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type1["building_number"] ?? '') . '</td>
                                <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type1["area"] ?? '') . '</td>
                                <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type1["total_area"] ?? '') . '</td>
                                <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type1["total_use_area"] ?? '') . '</td>
                                <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type1["living_area"] ?? '') . '</td>
                            </tr>

                            <tr>
                                <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; text-align: center;" colspan="4">
                                    Jami
                                </td>
                                <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type1["area"] ?? '') . '</td>
                                <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type1["total_area"] ?? '') . '</td>
                                <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type1["total_use_area"] ?? '') . '</td>
                                <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type1["living_area"] ?? '') . '</td>
                            </tr>
                        </table>
                    ';
            }

            if (isset($type12)) {
                $tableHtml .= '
                <p style="text-align: center; font-weight: bold; margin: 8px 0;">
                    4. Ko\'p kvartirali uydagi kvartiralar tavsifi
                </p>

                <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px;">
                    <tr>
                        <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center; vertical-align: middle; width: 16%;" rowspan="2">Bino va inshoonlar kadastr raqami</td>
                        <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center; vertical-align: middle; width: 16%;" rowspan="2">Bino va inshoonlar nomi</td>
                        <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center; vertical-align: middle; width: 10%;" rowspan="2">Umumiy foydali maydoni (kv.m.)</td>
                        <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center; vertical-align: middle; width: 10%;" rowspan="2">Yashash maydoni (kv.m.)</td>
                        <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center; vertical-align: middle; width: 10%;" rowspan="2">Jami kvartiralar soni</td>
                        <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center;" colspan="6">shundan</td>
                    </tr>
                    <tr>
                        <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center;">1 xonali</td>
                        <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center;">2 xonali</td>
                        <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center;">3 xonali</td>
                        <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center;">4 xonali</td>
                        <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center;">5 xonali</td>
                        <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center;">6 va undan ko\'p</td>
                    </tr>

                    <tr>
                        <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type12['cadastral_number'] ?? '-') . '</td>
                        <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type12['apartment_name'] ?? '-') . '</td>
                        <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type12['apartment_total_use_area'] ?? '0') . '</td>
                        <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type12['apartment_living_area'] ?? '0') . '</td>
                        <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type12['total_room_count'] ?? '0') . '</td>
                        <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type12['apartment_room_1'] ?? '0') . '</td>
                        <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type12['apartment_room_2'] ?? '0') . '</td>
                        <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type12['apartment_room_3'] ?? '0') . '</td>
                        <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type12['apartment_room_4'] ?? '0') . '</td>
                        <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type12['apartment_room_5'] ?? '0') . '</td>
                        <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type12['apartment_room_6_plus'] ?? '0') . '</td>
                    </tr>

                    <tr>
                        <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; text-align: center;" colspan="2">Jami</td>
                        <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type12['apartment_total_use_area'] ?? '0') . '</td>
                        <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type12['apartment_living_area'] ?? '0') . '</td>
                        <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type12['total_room_count'] ?? '0') . '</td>
                        <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type12['apartment_room_1'] ?? '0') . '</td>
                        <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type12['apartment_room_2'] ?? '0') . '</td>
                        <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type12['apartment_room_3'] ?? '0') . '</td>
                        <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type12['apartment_room_4'] ?? '0') . '</td>
                        <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type12['apartment_room_5'] ?? '0') . '</td>
                        <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type12['apartment_room_6_plus'] ?? '0') . '</td>
                    </tr>
                </table>';
            }

            if (isset($type13)) {
                $tableHtml .= '
                    <p style="text-align: center; font-weight: bold; margin: 8px 0;">
                        5. Noturar joylar tavsifi
                    </p>

                    <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px;">
                        <tr>
                            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center; vertical-align: middle; width: 18%;" rowspan="2">Bino va inshoonlar kadastr raqami</td>
                            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center; vertical-align: middle; width: 18%;" rowspan="2">Bino va inshoonlar nomi</td>
                            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center; vertical-align: middle; width: 9%;" rowspan="2">Qavatlar soni</td>
                            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center; vertical-align: middle; width: 9%;" rowspan="2">Joylashgan qavati</td>
                            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center;" colspan="3">Maydoni (kv.m.)</td>
                            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center; vertical-align: middle;" rowspan="2">Xonalar soni</td>
                        </tr>

                        <tr>
                            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center;">Qurilish osti maydoni</td>
                            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center;">Umumiy maydoni</td>
                            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center;">Umumiy foydali maydoni</td>
                        </tr>

                        <tr>
                            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type13['cadastral_number'] ?? '-') . '</td>
                            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type13['unliving_name'] ?? '-') . '</td>
                            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type13['unliving_floor_count'] ?? '0') . '</td>
                            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type13['unliving_current_floor'] ?? '0') . '</td>
                            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type13['unliving_area'] ?? '0') . '</td>
                            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type13['unliving_total_area'] ?? '0') . '</td>
                            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type13['unliving_use_area'] ?? '0') . '</td>
                            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type13['unliving_room_count'] ?? '0') . '</td>
                        </tr>

                        <tr>
                            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; text-align: center;" colspan="4">Jami</td>
                            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type13['unliving_area'] ?? '0') . '</td>
                            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type13['unliving_total_area'] ?? '0') . '</td>
                            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type13['unliving_use_area'] ?? '0') . '</td>
                            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type13['unliving_room_count'] ?? '0') . '</td>
                        </tr>
                    </table>';
            }

            if (isset($type14)) {
                $tableHtml .= '
                    <p style="text-align: center; font-weight: bold; margin: 8px 0;">
                        6. Umumiy mol-mulklar tavsifi
                    </p>

                    <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px;">
                        <tr>
                            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center; vertical-align: middle; width: 14%;" rowspan="3">Bino va inshoonlar kadastr raqami</td>
                            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center; vertical-align: middle; width: 12%;" rowspan="3">Bino va inshoonlar nomi</td>
                            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center; vertical-align: middle; width: 9%;" rowspan="3">Jami umumiy foydali maydoni (kv.m.)</td>
                            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center;" colspan="10">shundan</td>
                        </tr>

                        <tr>
                            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center;" colspan="4">Kirish yo\'laklari (podyezdlar)</td>
                            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center;" colspan="2">Yerto\'lalar</td>
                            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center;" colspan="2">Cherdaklar</td>
                            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center;" colspan="2">Boshqa umumiy mol-mulklar</td>
                        </tr>

                        <tr>
                            <td style="border: 1px solid #000; padding: 4px 3px; font-weight: bold; background-color: #d9d9d9; text-align: center;">yo\'laklar soni</td>
                            <td style="border: 1px solid #000; padding: 4px 3px; font-weight: bold; background-color: #d9d9d9; text-align: center;">xonalar soni</td>
                            <td style="border: 1px solid #000; padding: 4px 3px; font-weight: bold; background-color: #d9d9d9; text-align: center;">maydoni (kv.m.)</td>
                            <td style="border: 1px solid #000; padding: 4px 3px; font-weight: bold; background-color: #d9d9d9; text-align: center;">liftlar soni</td>
                            <td style="border: 1px solid #000; padding: 4px 3px; font-weight: bold; background-color: #d9d9d9; text-align: center;">xonalar soni</td>
                            <td style="border: 1px solid #000; padding: 4px 3px; font-weight: bold; background-color: #d9d9d9; text-align: center;">maydoni (kv.m.)</td>
                            <td style="border: 1px solid #000; padding: 4px 3px; font-weight: bold; background-color: #d9d9d9; text-align: center;">xonalar soni</td>
                            <td style="border: 1px solid #000; padding: 4px 3px; font-weight: bold; background-color: #d9d9d9; text-align: center;">maydoni (kv.m.)</td>
                            <td style="border: 1px solid #000; padding: 4px 3px; font-weight: bold; background-color: #d9d9d9; text-align: center;">xonalar soni</td>
                            <td style="border: 1px solid #000; padding: 4px 3px; font-weight: bold; background-color: #d9d9d9; text-align: center;">maydoni (kv.m.)</td>
                        </tr>

                        <tr>
                            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type14['cadastral_number'] ?? '-') . '</td>
                            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type14['common_name'] ?? '-') . '</td>
                            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type14['common_total_use_area'] ?? '0') . '</td>
                            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type14['common_entrance_number'] ?? '0') . '</td>
                            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type14['common_total_room_number'] ?? '0') . '</td>
                            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type14['common_area'] ?? '0') . '</td>
                            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type14['elevator_count'] ?? '0') . '</td>
                            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type14['underground_room_count'] ?? '0') . '</td>
                            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type14['underground_area'] ?? '0') . '</td>
                            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type14['cherdak_room'] ?? '0') . '</td>
                            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type14['cherdak_area'] ?? '0') . '</td>
                            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type14['extra_room'] ?? '0') . '</td>
                            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type14['extra_area'] ?? '0') . '</td>
                        </tr>

                        <tr>
                            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; text-align: center;" colspan="3">Jami</td>
                            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type14['common_entrance_number'] ?? '0') . '</td>
                            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type14['common_total_room_number'] ?? '0') . '</td>
                            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type14['common_area'] ?? '0') . '</td>
                            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type14['elevator_count'] ?? '0') . '</td>
                            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type14['underground_room_count'] ?? '0') . '</td>
                            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type14['underground_area'] ?? '0') . '</td>
                            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type14['cherdak_room'] ?? '0') . '</td>
                            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type14['cherdak_area'] ?? '0') . '</td>
                            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type14['extra_room'] ?? '0') . '</td>
                            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">' . ($type14['extra_area'] ?? '0') . '</td>
                        </tr>
                    </table>';
            }

        } else {
            $tableTr = '';
            $tableHtml = '<table style="border-collapse: collapse; width: 100%;">
                              <thead>
                                <tr>
                                  <th style="border: 1px solid black; padding: 5px; text-align: center;">Бино ва иншоотнинг номи</th>
                                  <th style="border: 1px solid black; padding: 5px; text-align: center;">Кадастр рақами</th>
                                  <th style="border: 1px solid black; padding: 5px; text-align: center;">Бинолар сони</th>
                                  <th style="border: 1px solid black; padding: 5px; text-align: center;">Умумий майдон (ташқи) (м²)</th>
                                  <th style="border: 1px solid black; padding: 5px; text-align: center;">Умумий фойдаланиш майдони (m²)</th>
                                  <th style="border: 1px solid black; padding: 5px; text-align: center;">Яшаш майдони (м²)</th>
                                  <th style="border: 1px solid black; padding: 5px; text-align: center;">Қурилиш ости майдони (м²)</th>
                                </tr>
                              </thead>
                              <tbody>
                                {blocks}
                              </tbody>
                              <tfoot>
                                {footer}
                              </tfoot>
                            </table>';
            $totalArr = [
                'building_number' => 0,
                'total_area' => 0,
                'total_use_area' => 0,
                'living_area' => 0,
                'area' => 0,
            ];
            foreach ($operatorBlocks as $operatorBlock) {
                $totalArr['building_number'] += $operatorBlock['building_number'];
                $totalArr['total_area'] += $operatorBlock['total_area'];
                $totalArr['total_use_area'] += $operatorBlock['total_use_area'];
                $totalArr['living_area'] += $operatorBlock['living_area'];
                $totalArr['area'] += $operatorBlock['area'];

                $tableTr .= '<tr>
                                  <td style="border: 1px solid black; padding: 5px; text-align: center;"> ' . $operatorBlock['name'] . '</td>
                                  <td style="border: 1px solid black; padding: 5px; text-align: center;">' . $operatorBlock['cadaster'] . '</td>
                                  <td style="border: 1px solid black; padding: 5px; text-align: center;">' . $operatorBlock['building_number'] . '</td>
                                  <td style="border: 1px solid black; padding: 5px; text-align: center;">' . $operatorBlock['total_area'] . '</td>
                                  <td style="border: 1px solid black; padding: 5px; text-align: center;">' . $operatorBlock['total_use_area'] . '</td>
                                  <td style="border: 1px solid black; padding: 5px; text-align: center;">' . $operatorBlock['living_area'] . '</td>
                                  <td style="border: 1px solid black; padding: 5px; text-align: center;">' . $operatorBlock['area'] . '</td>
                                </tr>';
            }

            $footer = '<tr>
                                  <th style="border: 1px solid black; padding: 5px;" colspan="2">Жами:</th>
                                  <th style="border: 1px solid black; padding: 5px; text-align: center;">' . $totalArr['building_number'] . '</th>
                                  <th style="border: 1px solid black; padding: 5px; text-align: center;">' . $totalArr['total_area'] . '</th>
                                  <th style="border: 1px solid black; padding: 5px; text-align: center;">' . $totalArr['total_use_area'] . '</th>
                                  <th style="border: 1px solid black; padding: 5px; text-align: center;">' . $totalArr['living_area'] . '</th>
                                  <th style="border: 1px solid black; padding: 5px; text-align: center;">' . $totalArr['area'] . '</th>
                                </tr>';
            $tableHtml = str_replace('{blocks}', $tableTr, $tableHtml);
            $tableHtml = str_replace('{footer}', $footer, $tableHtml);
        }
        return $tableHtml;
    }

    public function conclusionByInspector(ConclusionClaimByInspector $request)
    {
        $claimObject = $this->getClaimById(id: $request['id'], role_id: null);

        if ($claimObject->status != ClaimStatuses::TASK_STATUS_INSPECTOR)
            return false;

        $claimObject->monitoring->update(
            [
                'inspector_answer' => $request['type'],
            ]
        );

        $claimObject->update(
            [
                'status' => ClaimStatuses::TASK_STATUS_OPERATOR,
                'my_home_id' => $request['my_home_id'] ?? null,
                'elastic' => null,
            ]
        );

        $this->historyService->createHistory(
            guId: $claimObject->gu_id,
            status: ClaimStatuses::TASK_STATUS_OPERATOR,
            type: LogType::TASK_HISTORY,
            date: null,
            comment: $request['comment']
        );

        return true;
    }


    public function getClaimFromApi($guId)
    {
        if ($guId) {

            $consolidationDb = $this->getClaimByGUID($guId);
            $response = $this->GetRequest('get-task?id=' . $guId);
            $consolidationResp = Response::where(['task_id' => $guId]);
            if ($response->status() != 200) {
                return null;
            }

            $consolidationGov = $response->object();

            if (isset($consolidationGov->task->id)) {
                $createdDate = Carbon::today();

                if ($consolidationGov->task->current_node == "inactive")
                    $createdDate = $consolidationGov->task->created_date;

                $expiryDate = $this->getExpirationDate(startDate: $createdDate, duration: 3);
                if (!$consolidationDb || $consolidationDb->status == ClaimStatuses::RESEND) {
                    $insertedClaim = $this->claimRepository->createClaim($consolidationGov, $expiryDate);

                    if ((int)$consolidationGov->entities->CompletedBuildingsRegistrationCadastral->building_name_kartik->real_value != 1
                        && $insertedClaim->number_conclusion_project >= 94904641) {
                        $this->claimRepository->updateClaim(guId: $guId, data: [
                            'is_minstroy' => true
                        ]);
                    }
                } elseif ($consolidationGov->task->current_node != $consolidationDb->current_node || $consolidationGov->task->status != $consolidationDb->status_mygov) {
                    $status = ClaimStatuses::TASK_STATUS_ANOTHER;

                    if ($consolidationGov->task->current_node == "inactive" && $consolidationGov->task->status == "rejected") {
                        if ($consolidationGov->entities->CompletedBuildingsRegistrationCadastral->minstroy_territory->real_value != null || $consolidationGov->entities->CompletedBuildingsRegistrationCadastral->minstroy_territory->real_value != "")
                            $status = ClaimStatuses::TASK_STATUS_SENT_ANOTHER_ORG;
                        else {
                            if ($consolidationGov->entities->CompletedBuildingsRegistrationCadastral->gasn_name_reject->real_value == 'Shaffof qurilish milliy axborot tizimi')
                                $status = ClaimStatuses::TASK_STATUS_ORGANIZATION_REJECTED;
                            else
                                $status = ClaimStatuses::TASK_STATUS_REJECTED;
                        }
                    }
                    if ($consolidationGov->task->current_node == "inactive" && $consolidationGov->task->status == "processed") {
                        if ($consolidationGov->entities->CompletedBuildingsRegistrationCadastral->minstroy_territory->real_value != null || $consolidationGov->entities->CompletedBuildingsRegistrationCadastral->minstroy_territory->real_value != "")
                            $status = ClaimStatuses::TASK_STATUS_SENT_ANOTHER_ORG;
                        else
                            $status = ClaimStatuses::TASK_STATUS_CONFIRMED;
                    }
                    if ($consolidationGov->task->status == "process" && $consolidationGov->task->current_node == "statement-formation") {

                        if ($consolidationGov->entities->CompletedBuildingsRegistrationCadastral->minstroy_territory->real_value != null || $consolidationGov->entities->CompletedBuildingsRegistrationCadastral->minstroy_territory->real_value != "")
                            $status = ClaimStatuses::TASK_STATUS_SENT_ANOTHER_ORG;
                        else
                            $status = ClaimStatuses::TASK_STATUS_CONFIRMED;
                    }
                    if ($consolidationGov->task->status == "process" && $consolidationGov->task->current_node == "gasn-to-kad") {

                        if ($consolidationGov->entities->CompletedBuildingsRegistrationCadastral->minstroy_territory->real_value != null || $consolidationGov->entities->CompletedBuildingsRegistrationCadastral->minstroy_territory->real_value != "")
                            $status = ClaimStatuses::TASK_STATUS_SENT_ANOTHER_ORG;
                        else
                            $status = ClaimStatuses::TASK_STATUS_CONFIRMED;
                    }
                    if ($consolidationGov->task->current_node == "inactive" && $consolidationGov->task->status == "not_active")
                        $status = ClaimStatuses::TASK_STATUS_CANCELLED;
                    if ($consolidationGov->task->current_node == "direction-statement-object") {
                        $status = ClaimStatuses::TASK_STATUS_ACCEPTANCE;
                    }
                    if ($consolidationGov->task->current_node == "answer-other-institutions") {
                        if ($consolidationGov->task->status == "resend"){
                            $status = ClaimStatuses::TASK_STATUS_OPERATOR;
                        }else{
                            $status = ClaimStatuses::TASK_STATUS_SENT_ORGANIZATION;
                            $reviews = ClaimOrganizationReview::with('monitoring')->where('claim_id', $consolidationDb->id)->get();

                            if ($reviews->count() > 0) {
                                list($isFinished, $allSuccess) = $this->checkReviewCount($reviews);

                                if ($isFinished) {
                                    if ($allSuccess) {
                                        $status = ClaimStatuses::TASK_STATUS_INSPECTOR;
                                    } else {
                                        $this->autoRejectByOrganization($reviews->first());
                                        $status = ClaimStatuses::TASK_STATUS_ORGANIZATION_REJECTED;
                                    }
                                }

                            } else
                                $status = ClaimStatuses::TASK_STATUS_ATTACH_OBJECT;

                            if ($consolidationDb->object_id == null)
                                $status = ClaimStatuses::TASK_STATUS_ATTACH_OBJECT;
                        }

                    }
                    if ($consolidationGov->task->current_node == "conclusion-gasn") {
                        $status = ClaimStatuses::TASK_STATUS_DIRECTOR;
                        $reviews = ClaimOrganizationReview::with('monitoring')->where('claim_id', $consolidationDb->id)->get();

                        if ($reviews->count() > 0) {
                            list($isFinished, $allSuccess) = $this->checkReviewCount($reviews);

                            if ($isFinished) {
                                if (!$allSuccess) {
                                    $data = $this->autoRejectByOrganization($reviews->first());
                                    if ($data)
                                        $status = ClaimStatuses::TASK_STATUS_ORGANIZATION_REJECTED;
                                }
                            }

                        }
                    }
                    if ($consolidationGov->task->status == "waiting_sub_user" && $consolidationGov->task->current_node == "change-application")
                        $status = ClaimStatuses::RESEND;
                    if ($consolidationGov->task->current_node == "conclusion-minstroy")
                        $status = ClaimStatuses::TASK_STATUS_SENT_ANOTHER_ORG;

                    $this->claimRepository->updateClaim(guId: $guId, data: [
                        'current_node' => $consolidationGov->task->current_node,
                        'status_mygov' => $consolidationGov->task->status,
                        'status' => $status
                    ]);

                    if (($status != ClaimStatuses::TASK_STATUS_ACCEPTANCE || $status != ClaimStatuses::TASK_STATUS_ANOTHER) && $status != $consolidationDb->status) {
                        $this->historyService->createHistory(
                            guId: $guId,
                            status: $status,
                            type: LogType::TASK_HISTORY,
                            date: $consolidationGov->task->last_update
                        );
                    }
                }
                if ($consolidationGov->task->id > 0) {
                    $consolidationResp->update([
                        'status' => ClaimStatuses::RESPONSE_WATCHED
                    ]);

                    return $consolidationGov;
                } else {
                    return null;
                }
            }

            return null;
        }

        return null;
    }

    private
    function GetRequest($url)
    {
        $response = Http::withBasicAuth(config('app.mygov.login'), config('app.mygov.password'))->timeout(10)->connectTimeout(5)->get($this->url . $url);

        return $response;
    }

    private
    function PostRequest($url, $data)
    {
        $response = Http::withBasicAuth(config('app.mygov.login'), config('app.mygov.password'))->timeout(10)->connectTimeout(5)->post($this->url . $url, $data);

        return $response;
    }

}
