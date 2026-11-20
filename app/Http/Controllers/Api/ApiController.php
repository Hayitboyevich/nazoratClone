<?php

namespace App\Http\Controllers\Api;

use App\Enums\DxaResponseStatusEnum;
use App\Enums\ObjectStatusEnum;
use App\Enums\RegulationStatusEnum;
use App\Enums\UserRoleEnum;
use App\Helpers\ClaimStatuses;
use App\Helpers\OrganizationNames;
use App\Helpers\PresentationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateDeadline;
use App\Http\Resources\ActStatusResource;
use App\Http\Resources\ArticleLocationListResource;
use App\Http\Resources\ArticleLocationResource;
use App\Http\Resources\ArticleOpenDataResource;
use App\Http\Resources\ArticleResource;
use App\Http\Resources\ArticleUrbanResource;
use App\Http\Resources\DistrictResource;
use App\Http\Resources\DxaResponseResource;
use App\Http\Resources\LawyerStatusResource;
use App\Http\Resources\ObjectParamBlockResource;
use App\Http\Resources\ObjectParamResource;
use App\Http\Resources\ObjectProcuracyResource;
use App\Http\Resources\ObjectStatusResource;
use App\Http\Resources\RegionResource;
use App\Http\Resources\RegulationEventResource;
use App\Http\Resources\RegulationFineResource;
use App\Http\Resources\RegulationStatusResource;
use App\Http\Resources\RegulationTypeResource;
use App\Http\Resources\ResponseBlockResource;
use App\Http\Resources\RoleResource;
use App\Http\Resources\UserResource;
use App\Models\AiControl;
use App\Models\Article;
use App\Models\Block;
use App\Models\Claim;
use App\Models\ClaimGov;
use App\Models\ClaimMonitoring;
use App\Models\ClaimOperator;
use App\Models\DxaResponse;
use App\Models\Inspection;
use App\Models\LabClaim;
use App\Models\LabContract;
use App\Models\Presentation;
use App\Models\Region;
use App\Models\Regulation;
use App\Models\Rekvizit;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Interfaces\ArticleRepositoryInterface;
use App\Repositories\Interfaces\ClaimRepositoryInterface;
use App\Scopes\LabClaimUserScope;
use App\Services\CameraService;
use App\Services\ClaimService;
use App\Services\DxaResponseService;
use App\Services\InformationService;
use App\Services\LabService;
use App\Services\MyGovService;
use App\Services\QuestionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Geometry\Factories\RectangleFactory;
use Intervention\Image\ImageManager;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


class ApiController extends Controller
{
    private MyGovService $myGovService;
    private ClaimService $claimService;
    private ArticleRepositoryInterface $articleRepository;

    private DxaResponseService $dxaResponseService;
    private ClaimRepositoryInterface $claimRepository;
    private  QuestionService $questionService;
    private LabService $labService;

    public function __construct(MyGovService               $myGovService,
                                ClaimService               $claimService,
                                ArticleRepositoryInterface $articleRepository,
                                ClaimRepositoryInterface   $claimRepository,
                                LabService                 $labService,
                                DxaResponseService         $dxaResponseService,
                                QuestionService             $questionService,
                                protected CameraService $cameraService,
    )
    {
        $this->myGovService = $myGovService;
        $this->articleRepository = $articleRepository;
        $this->claimRepository = $claimRepository;
        $this->claimService = $claimService;
        $this->labService = $labService;
        $this->dxaResponseService = $dxaResponseService;
        $this->questionService = $questionService;
    }

    public function getClaimObjects()
    {
        $filters = request()->only(['region_id', 'date_from', 'date_to', 'per_page', 'regions', 'page']);

        $baseQuery = Claim::query()
            ->leftJoin('articles as a', 'a.id', '=', 'claims.object_id')
            ->leftJoin('regions as r', 'r.soato', '=', 'claims.region')
            ->leftJoin('districts as d', 'd.soato', '=', 'claims.district')
            ->when(isset($filters['regions']), fn($q) => $q->where('claims.region', $filters['regions']))
            ->when(isset($filters['date_from']) || isset($filters['date_to']), function ($query) use ($filters) {
                $startDate = isset($filters['date_from']) ? $filters['date_from'] . ' 00:00:00' : null;
                $endDate = isset($filters['date_to']) ? $filters['date_to'] . ' 23:59:59' : null;

                $startDate = $startDate ? Carbon::parse($startDate)->format('Y-m-d') : null;
                $endDate = $endDate ? Carbon::parse($endDate)->format('Y-m-d') : null;

                if ($startDate && $endDate) {
                    $query->whereBetween('claims.created_at', [$startDate, $endDate]);
                } elseif ($startDate) {
                    $query->where('claims.created_at', '>=', $startDate);
                } elseif ($endDate) {
                    $query->where('claims.created_at', '<=', $endDate);
                }
            })
            ->where('claims.status', '<>', ClaimStatuses::TASK_STATUS_ANOTHER);
        $total_count = (clone $baseQuery)->count('claims.id');
        $claims = $baseQuery->select([
            'claims.guid as ariza_raqami',
            DB::raw("(CASE WHEN claims.user_type = 'J' THEN claims.legal_name ELSE claims.ind_name END) as customer"),
            DB::raw("(CASE WHEN claims.user_type = 'J' THEN claims.legal_tin ELSE claims.ind_pinfl END) as customer_inn"),
            'a.task_id as obyekt_raqami',
            'a.created_at as object_created_at',
            'a.id as article_id',
            'claims.region as region',
            'claims.district as district',
            'r.name_uz as region_name',
            'd.name_uz as district_name',
            'claims.building_name as obyekt_nomi',
            'claims.end_date as end_date',
            'claims.status',
            'claims.region',
            'claims.user_type',
            'claims.building_address',
            'claims.created_at',
            'claims.id',
        ])
            ->with(['monitoring', 'reviews'])
            ->paginate($filters['per_page'] ?? 50, ['*'], 'page', $filters['page'] ?? 1);

        $claimHistories = DB::table('claim_histories')
            ->whereIn('gu_id', collect($claims->items())->pluck('ariza_raqami'))
            ->get()
            ->groupBy('gu_id');

        $array = [];

        foreach ($claims as $claim) {
            $history = $claimHistories[$claim->ariza_raqami] ?? collect();

            $inspectorConcDate = '-';
            $directorConcDate = '-';
            $operatorConcDate = '-';

            $historySentOrg = '-';
            $historyAssignObjectDate = '-';

            $orgArr = [];
            if (!in_array($claim->status, [ClaimStatuses::TASK_STATUS_ANOTHER])) {
                $historyConc = $history->first(fn($item) => data_get(json_decode($item->content, true), 'role') == 3);
                $historyDirectorConc = $history->first(fn($item) => data_get(json_decode($item->content, true), 'role') == 12);

                $historySentOrganization = $history->first(fn($item) => data_get(json_decode($item->content, true), 'status') == 4);
                $historyAssignObject = $history->first(fn($item) => data_get(json_decode($item->content, true), 'status') == 3);
                $historyOperator = $history->first(fn($item) => data_get(json_decode($item->content, true), 'status') == 13);

                if ($historySentOrganization)
                    $historySentOrg = $historySentOrganization->created_at;

                if ($historyAssignObject)
                    $historyAssignObjectDate = $historyAssignObject->created_at;

                if ($historyConc)
                    $inspectorConcDate = $historyConc->created_at;

                if ($historyDirectorConc)
                    $directorConcDate = $historyDirectorConc->created_at;

                if ($historyOperator)
                    $operatorConcDate = $historyOperator->created_at;

                foreach ($claim->reviews as $review) {
                    $region = (in_array($review->organization_id, [15, 16])) ? $review->monitoring->claim->district()->first()->name_uz : $review->monitoring->claim->region()->first()->name_uz;
                    $orgArr[] = [
                        'org_name' => str_replace('{region}', $region, OrganizationNames::NAMES[$review->organization_id]),
                        'org_date' => $review->answered_at,
                    ];
                }
            }

            $tmpArray = [
                'doc_id' => $claim->id,
                'task_id' => $claim->ariza_raqami,
                'object_name' => $claim->obyekt_nomi,
                'object_id' => $claim->obyekt_raqami,

                'customer_name' => $claim->customer,
                'customer_stir' => $claim->customer_inn,

                'object_assign_date' => $historyAssignObjectDate,
                'org_send_date' => $historySentOrg,
                'inspect_date' => $inspectorConcDate,
                'oper_date' => $operatorConcDate,
                'chief_date' => $directorConcDate,
                'org_conclusions' => $orgArr,
                'created_at' => $claim->created_at,
                'district_id' => $claim->district,
                'district_name' => $claim->district_name,
                'region_id' => $claim->region,
                'region_name' => $claim->region_name,
                'minstroy_date' => ($claim->status == 11) ? $claim->end_date : '',
                'conclusion_date' => ($claim->status == 20) ? $claim->end_date : '',
                'rejected_date' => ($claim->status == 15) ? $claim->end_date : '',
                'org_rejected_date' => ($claim->status == 9) ? $claim->end_date : '',
            ];

            $array[] = $tmpArray;
        }

        $response = [
            "success" => true,
            "status" => 200,
            "msg" => "Сўровга асосан маълумотлар тўлиқ шакллантирилди",
            "title" => "Объектни фойдаланишга топшириш бўйича келиб тушган аризалар тўғрисида маълумот олиш",
            "total_counts" => $total_count,
            "description" => [
                "doc_id" => "Маълумотнинг уникал рақами",
                "task_id" => "Ариза рақами",
                "customer_name" => "Аризачи номи",
                "customer_stir" => "Аризачи СТИР / ЖШШИР",
                "object_name" => "Объект номи",
                "object_assign_date" => "Аризанинг объектга бириктирилган санаси",
                "object_id" => "Бириктирилган объект рақами",
                "org_send_date" => "Аризанинг ташкилотга юборилган санаси",
                "org_conclusions" => "Аризага ҳар бир ташкилот томонидан берилган жавоблар",
                "inspect_date" => "Аризанинг инспектор томонидан кўриб чиқилган санаси",
                "oper_date" => "Аризанинг оператор томонидан кўриб чиқилган санаси",
                "chief_date" => "Аризанинг бошлиқ томонидан кўриб чиқилган санаси",
                "conclusion_date" => "Аризага рухсат берилган сана",
                "minstroy_date" => "Аризанинг бошқармага юборилган санаси",
                "rejected_date" => "Аризанинг инспекция томониан рад этилган санаси",
                "org_rejected_date " => "Аризанинг ташкилот томониан рад этилган санаси"
            ],
            "data" => $array
        ];


        return $response;
    }

    public function showTask($id)
    {
        $data = $this->myGovService->getObjectTaskById(task_id: $id);

        if (!$data) {
            return ['success' => false, 'message' => "Ma'lumot topilmadi"];
        }

        return $data;
    }

    public function updatePresentation()
    {
        $presentation = Presentation::query()->find(request('uuid'));
        if (!$presentation) {
            return ['success' => false, 'message' => "Ma'lumot topilmadi"];
        }

        $presentation->update([
            'gost_doc_id' => request('doc_id'),
            'gost_doc' => request('doc'),
            'gost_status' => request()->input('status'),
            'status' => PresentationStatus::REPRISAL,
            'conclusion_at' => Carbon::now()
        ]);

        return ['success' => true];
    }

    public function updateDeadline(UpdateDeadline $request)
    {
        $objectModel = $this->articleRepository->findByTaskId($request['task_id']);

        if (!$objectModel)
            return ['success' => false, 'message' => "Obyekt Topilmadi!"];


        if ($objectModel->object_status_id->value === ObjectStatusEnum::SUBMITTED->value)
            return ['success' => false, 'message' => "Bu obyekt yakunlangan!"];

        if ($objectModel->object_status_id->value === ObjectStatusEnum::CANCELED->value)
            return ['success' => false, 'message' => "Bu obyekt bekor qilingan!"];

        if (Carbon::parse($objectModel->deadline)->greaterThanOrEqualTo(Carbon::parse($request['end_term_work']))) {
            return ['success' => false, 'message' => "Sanada xatolik!"];
        }
//        $files = [];
//        foreach ($request->files as $document) {
//            $path = $document->store('document/object', 'public');
//            $files[] =$path;
//        }


        DB::table('article_deadline_log')->insertGetId(
            [
                'object_id' => $objectModel->id,
                'old_deadline' => $objectModel->deadline,
                'new_deadline' => $request['end_term_work'],
                'files' => json_encode($request['files']),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]
        );

        $objectModel->update(
            [
                'deadline' => $request['end_term_work'],
                'gnk_tender' => $request['gnk_id'],
                'send_tax' => null,
                'send_operator' => null,
            ]
        );

        return ['message' => "Obyekt muddati muvaffaqiyatli uzaytirildi"];
    }

    public function getObjectImages($id)
    {
        try {
            $object = Article::with('monitorings.images')->findOrFail($id);

            $imagesByDate = $object->monitorings
                ->flatMap(fn($monitoring) => $monitoring->images)
                ->sortByDesc('created_at')
                ->map(fn($image) => [
                    'id' => $image->id,
                    'url' => Storage::disk('public')->url($image->url),
                    'created_at' => $image->created_at
                ])
                ->groupBy(fn($image) => Carbon::parse($image['created_at'])->toDateString())
                ->toArray();

            if (empty($imagesByDate)) {
                return ['success' => false, 'message' => "Ma'lumot topilmadi"];
            }

            return response()->json($imagesByDate);
        } catch (\Exception $exception) {
            return ['success' => false, 'message' => "Ma'lumot topilmadi"];
        }
    }

    public function getByTaskId($taskId)
    {
        $objectModel = $this->articleRepository->findByTaskId($taskId);
        $data = ArticleOpenDataResource::make($objectModel);
        if (!$data) {
            return ['success' => false, 'message' => "Ma'lumot topilmadi"];
        }
        return $data;

    }

    public function getObjectByGuid($guid)
    {
        $claim = $this->claimRepository->getClaimByGUID($guid);
        $blocks_accepted = [];
        if ($claim) {
            $object = Article::query()->where('articles.id', $claim->object_id)
                ->with(['region', 'district', 'blocks'])->first();
            $claim_monitorings = DB::table('claims as c')
                ->leftJoin('claim_monitoring as cm', 'cm.claim_id', '=', 'c.id')
//                ->where('c.status', ClaimStatuses::TASK_STATUS_CONFIRMED)
                ->where('c.object_id', $object->id)
                ->select(
                    DB::raw('cm.blocks as blocks'),
                    'cm.operator_answer',
                    'cm.inspector_answer',
                    'cm.director_answer'
                )
                ->get();

            foreach ($claim_monitorings as $claim_monitoring) {
                $blocks = $claim_monitoring->blocks;
                $decoded_answer = null;
                if (!is_null($claim_monitoring->operator_answer) and !isset($blocks_accepted[$blocks])
                    and $claim_monitoring->inspector_answer === 20 and $claim_monitoring->director_answer === 20) {
                    $decoded_answer = json_decode(gzuncompress(base64_decode($claim_monitoring->operator_answer)), true);
                    $blocks_accepted[$blocks] = [
                        'blocks' => $blocks,
                        'operator_answer' => []
                    ];
                }

                if (is_array($decoded_answer)) {
                    $blocks_accepted[$blocks]['operator_answer'] = array_merge(
                        $blocks_accepted[$blocks]['operator_answer'],
                        $decoded_answer
                    );
                }
            }
            $result = array_values($blocks_accepted);
            if ($object) {
                return response()->json([
                    'success' => true,
                    'result' => [
                        'data' => [
                            'task_id' => $object->task_id,
                            'region' => $object->region->soato,
                            'name_uz' => $object->region->name_uz,
                            'district' => $object->district->soato,
                            'district_name_uz' => $object->district->name_uz,
                            'blocks' => ResponseBlockResource::collection($object->blocks),
                            'blocks_accepted' => $result
                        ],
                    ]
                ]);
            }
        }
        return response()->json([
            'success' => false,
            'message' => 'not found.'
        ], 404);
    }

    public function getObjectList()
    {
        $filters = request()->only(['per_page', 'page']);
        $per_page = (int)($filters['per_page'] ?? 10);
        $page = (int)($filters['page'] ?? 1);
        $articles = Article::query()
            ->where('sphere_id', 57)
            ->whereIn('object_status_id', [2, 5])
            ->orderBy('id', 'desc')
            ->paginate($per_page, ['*'], 'page', $page);
        return response()->json([
            'success' => true,
            'message' => 'success',
            'result' => [
                'data' => ArticleOpenDataResource::collection($articles)
            ],
            'meta' => [
                'lastPage' => $articles->lastPage(),
                'total' => $articles->total(),
                'perPage' => $articles->perPage(),
                'current_page' => $articles->currentPage()
            ]
        ]);
    }

    public function getObjectParams()
    {
        try {
            $taskId = request('task_id');
            $taskIds = [];
            $currentTaskId = $taskId;
            $objectModel = null;

            while (true) {
                if (in_array($currentTaskId, $taskIds)) {
                    break;
                }

                $taskIds[] = $currentTaskId;

                $objectModel = $this->articleRepository->findByTaskId($currentTaskId);
                if ($objectModel) {
                    break;
                }

                $dxa = DxaResponse::query()
                    ->where('old_task_id', $currentTaskId)
                    ->where('dxa_response_status_id', 4)
                    ->first();

                if (!$dxa) {
                    break;
                }

                $currentTaskId = $dxa->task_id;
            }


            if (!$objectModel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Obyekt topilmadi'
                ], 404);
            }


            $articles = Article::query()
                ->with(['claims'])
                ->when(empty($taskId), function ($query) {
                    return $query->where('sphere_id', 57);
                })
                ->when(!empty($taskId), function ($query) use ($currentTaskId) {
                    return $query->where('task_id', $currentTaskId);
                })
                ->paginate(request('per_page') ?? 15);

            return response()->json([
                'data' => ObjectParamResource::collection($articles),
                'meta' => pagination($articles),
            ], 200);

        } catch (\Exception $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage()
            ], 404);
        }
    }

    public function objectGetBlocks()
    {
        try {
            $articles = Article::query()
                ->where('sphere_id', 57)
                ->where('object_status_id', 5)
                ->paginate(request('per_page') ?? 15);

            return response()->json([
                'data' => ObjectParamBlockResource::collection($articles),
                'meta' => pagination($articles),
            ], 200);

        } catch (\Exception $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage()
            ], 404);
        }
    }

    public function objectGetLocation()
    {
        try {
            $articles = \request('id')
                ? Article::query()->findOrFail(\request('id'))
                : Article::query()
                    ->with(['objectStatus', 'region', 'district', 'blocks'])
                    ->when(request('region_soato'), function ($query) {
                        $query->whereHas('region', fn($q) => $q->where('soato', request('region_soato')));
                    })
                    ->when(request('district_soato'), function ($query) {
                        $query->whereHas('district', fn($q) => $q->where('soato', request('district_soato')));
                    })
                    ->when(request('region_id'), function ($query) {
                        $query->where('region_id', request('region_id'));
                    })->when(request('district_id'), function ($query) {
                        $query->where('district_id', request('district_id'));
                    })->get();

            $resource = \request('id')
                ? ArticleLocationResource::make($articles)
                : ArticleLocationListResource::collection($articles);

            return response()->json([
                'data' => $resource,
            ], 200);

        } catch (\Exception $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage()
            ], 404);
        }
    }

//    public function objectGetLocation()
//    {
//        try {
//            $query = Article::query()
//                ->select([
//                    'articles.id',
//                    'articles.name',
//                    'articles.task_id',
//                    'articles.manual_task_id',
//                    'articles.sphere_id',
//                    'articles.location_building',
//                    'articles.difficulty_id',
//                    'articles.organization_name',
//                    'articles.object_status_id',
//                    'articles.closed_at',
//                    'region.soato as region_soato',
//                    'district.soato as district_soato',
//                    'articles.deadline',
//                    'articles.lat',
//                    'articles.long',
//                    'articles.number_protocol',
//                    'articles.reestr_number',
//
//                    DB::raw('(SELECT COUNT(*)
//                           FROM blocks
//                           WHERE blocks.article_id = articles.id) AS block_count'),
//
//                    DB::raw('(SELECT COALESCE(SUM(count_apartments), 0)
//                           FROM blocks
//                           WHERE blocks.article_id = articles.id) AS apartment_count'),
//                ])
//                ->leftJoin('regions as region', 'region.id', '=', 'articles.region_id')
//                ->leftJoin('districts as district', 'district.id', '=', 'articles.district_id');
//
//            if (request('id')) {
//                $query->where('articles.id', request('id'));
//            }
//
//            if (request('region_soato')) {
//                $query->where('region.soato', request('region_soato'));
//            }
//
//            if (request('district_soato')) {
//                $query->where('district.soato', request('district_soato'));
//            }
//
//            if (request('region_id')) {
//                $query->where('articles.region_id', request('region_id'));
//            }
//
//            if (request('district_id')) {
//                $query->where('articles.district_id', request('district_id'));
//            }
//
//            $articles = $query->get();
//
//            return response()->json([
//                'data' => $articles
//            ]);
//
//        } catch (\Exception $exception) {
//            return response()->json([
//                'success' => false,
//                'message' => $exception->getMessage()
//            ], 404);
//        }
//    }


    public function objectGetPdf()
    {
        try {
            $informationService = new InformationService();
            $data = $informationService->getConclusionPDF(request('id'));
            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage()
            ], 404);
        }
    }

    public function getOrgObjectsList($inn)
    {
        try {
            $filters = request()->only(['per_page', 'page', 'status', 'sphere', 'funding_source', 'role']);
            $per_page = (int)($filters['per_page'] ?? 10);
            $page = (int)($filters['page'] ?? 1);
            $server_time = now();
            $org_objects = DB::table('users as u')
                ->join('user_roles as ur', function ($join) use ($filters) {
                    $join->on('ur.user_id', '=', 'u.id')
                        ->where('ur.role_id', $filters['role'] ?? 10);
                })
                ->where('u.pinfl', $inn)
                ->when(isset($filters['status']), function ($query) use ($filters) {
                    $query->whereIn('a.object_status_id', $filters['status']);
                })
                ->when(isset($filters['sphere']), function ($query) use ($filters) {
                    $query->where('a.sphere_id', $filters['sphere']);
                })
                ->leftJoin('article_users as au', function ($join) {
                    $join->on('au.user_id', '=', 'u.id');
                })
                ->where('au.role_id', $filters['role'] ?? 10)
//                ->join('articles as a', 'a.id', '=', 'au.article_id')
                ->join('articles as a', function ($join) {
                    $join->on('a.id', '=', 'au.article_id');
                })
                ->where('a.funding_source_id', $filters['funding_source'] ?? 1)
                ->leftJoin('regions as r', 'r.id', '=', 'a.region_id')
                ->leftJoin('districts as d', 'd.id', '=', 'a.district_id')
                ->leftJoin('object_statuses as os', 'os.id', '=', 'a.object_status_id')
                ->select([
                    'a.task_id as request_number',
                    'a.name as name',
                    'a.lat as lat',
                    'a.long as long',
                    'r.name_uz as region_name',
                    'd.name_uz as district_name',
                    'r.soato as region_soato',
                    'd.soato as district_soato',
                    'a.location_building as object_address',
                    'a.deadline as deadline',
                    'u.pinfl as inn',
                    'u.organization_name as full_name',
                    'os.name as status',
                    'a.object_status_id as status_id',
                    'a.gnk_id as gnk_id',
                    'a.cadastral_number as cadastr',
                    'a.closed_at as closed_at',
                    'a.created_at as created_at'
                ])
                ->orderBy('a.id', 'desc')
                ->paginate($per_page, ['*'], 'page', $page);
            if ($org_objects->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'description' => 'Obyektlar topilmadi.',
                    'data' => []
                ]);
            }
            $objects = $org_objects->map(function ($object) use ($server_time) {
                if (!in_array($object->status_id, [5, 6])) {
                    try {
                        $deadline = Carbon::parse($object->deadline);
                    } catch (\Throwable $e) {
                        $deadline = null;
                    }
//                    $deadline = $object->deadline ? Carbon::parse($object->deadline) : null;
                    $object->expire = $deadline ? $server_time->diffInDays($deadline, false) : null;
                } else {
                    $object->expire = null;
                }
                return $object;
            });
            return response()->json([
                'success' => true,
                'status' => 'Ok',
                'description' => 'The request has succeeded.',
                'data' => [
                    'count' => $org_objects->total(),
                    'objects' => $objects
                ],
                'meta' => [
                    'lastPage' => $org_objects->lastPage(),
                    'total' => $org_objects->total(),
                    'perPage' => $org_objects->perPage(),
                    'current_page' => $org_objects->currentPage()
                ]
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage()
            ], 500);
        }
    }

    public function getObjectInfoByTaskId($task_id)
    {
        $article = Article::with(['objectType', 'blocks', 'region:id,name_uz', 'district:id,name_uz'])
            ->where('task_id', $task_id)
            ->orWhere('manual_task_id', $task_id)
            ->select(['id', 'name', 'task_id', 'number_protocol', 'region_id', 'district_id'])
            ->first();
        if ($article) {
            $claim_monitorings = ClaimMonitoring::where(['object_id' => $article->id, 'inspector_answer' => 20, 'director_answer' => 20])->get();
            $article->blocks_total_infos = $claim_monitorings;
            $article->makeHidden(['region_id', 'district_id']);
            $article->setAppends([]);
            return response()->json(['success' => true, 'result' => ['data' => $article]]);
        }
        return response()->json(['message' => 'Object not found'], 404);
    }

    public function objectGetCount()
    {
        try {
            $articles = Article::query()
                ->selectRaw('region_id, object_status_id, COUNT(*) as total')
                ->groupBy('region_id', 'object_status_id')
                ->get();

            $regions = Region::all();

            $result = [];

            foreach ($regions as $region) {
                $regionArticles = $articles->where('region_id', $region->id);

                $result[] = [
                    'region_id' => $region->id,
                    'region_name' => $region->name_uz,
                    'all' => $regionArticles->sum('total'),
                    'progress' => $regionArticles->where('object_status_id', ObjectStatusEnum::PROGRESS)->sum('total'),
                    'frozen' => $regionArticles->where('object_status_id', ObjectStatusEnum::FROZEN)->sum('total'),
                    'suspended' => $regionArticles->where('object_status_id', ObjectStatusEnum::SUSPENDED)->sum('total'),
                    'submitted' => $regionArticles->where('object_status_id', ObjectStatusEnum::SUBMITTED)->sum('total'),
                    'canceled' => $regionArticles->where('object_status_id', ObjectStatusEnum::CANCELED)->sum('total'),
                ];
            }

            return response()->json(['success' => true, 'result' => ['data' => $result]]);

        } catch (\Exception $exception) {
            return response()->json(['message' => 'Xatolik mavjud'], 404);

        }
    }

    public function getObjectSert()
    {
        try {
            $inn = request('inn');
            $user = User::query()->where('pinfl', $inn)->first();
            $articles = $user->objects()
                ->where('difficulty_category_id', 4)
                ->get()
                ->map(function ($article) {
                    return [
                        'object_id' => $article->id,
                        'task_id' => $article->task_id ?? $article->manual_task_id,
                        'name' => $article->name,
                        'lat' => $article->lat,
                        'long' => $article->long,
                        'region' => $article->region ? $article->region->name_uz : null,
                        'district' => $article->district ? $article->district->name_uz : null,
                        'address' => $article->location_building,
                        'difficulty_category_id' => $article->difficulty_category_id,
                    ];
                });
            return response()->json($articles);

        } catch (\Exception $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        }
    }

    public function getConclusionPDF()
    {
        $object = $this->claimService->getClaimByGUID(request()->get('task_id'));
        if (!$object)
            return response()->json(['message' => 'Bunday ma\'lumot mavjud emas'], 404);

        $result = $this->claimService->getConclusionPDF(request()->get('task_id'));
        if (isset($result['status']))
            return response()->json(['message' => 'Xatolik mavjud'], 404);

        return response()->json(['data' => $result], 200);
    }

    public function getLabCont()
    {
        $lab_con = LabContract::query()
            ->where('lab_contracts.status', LabClaim::STATUS_COMPLETED)
            ->where('lab_contracts.contract_number', request()->get('con_num'))
            ->leftJoin('lab_claims as lc', 'lc.id', '=', 'lab_contracts.lab_claim_id')
            ->leftJoin('lab_claim_users as lcu', function ($join) {
                $join->on('lcu.lab_claim_id', '=', 'lc.id');
                $join->where('lcu.role_id', UserRoleEnum::BUYURTMACHI);
            })
            ->leftJoin('users as u', 'u.id', '=', 'lcu.user_id')
            ->select('lc.*', 'lc.id as lab_claim_id', 'lab_contracts.customer_confirmed_at',
                'u.name', 'u.surname', 'u.middle_name', 'u.pinfl', 'u.organization_name'
            )
            ->firstOrFail();
        $inspeksiya = Inspection::query()->where(['region_id' => $lab_con->region_id])->first();
        $result = [
            'buyurtmachi' => $lab_con->claim_type === 'J' ? "$lab_con->name " . "$lab_con->surname " . "$lab_con->middle_name" : $lab_con->organization_name,
            'inn' => $lab_con->pinfl,
            'shartnoma_sanasi' => $lab_con->customer_confirmed_at,
            'shartnoma_bergan_tashkilot' => "$inspeksiya->name",
            'shartnoma_link' => config('app.url') . '/api/lab-contract?con_num=' . request()->get('con_num'),
        ];
        return response()->json([
            'data' => $result
        ]);
    }

    public function getLabContract()
    {
        $con_num = request()->only('con_num');
        $months_uz = [
            'January' => 'yanvar',
            'February' => 'fevral',
            'March' => 'mart',
            'April' => 'aprel',
            'May' => 'may',
            'June' => 'iyun',
            'July' => 'iyul',
            'August' => 'avgust',
            'September' => 'sentyabr',
            'October' => 'oktyabr',
            'November' => 'noyabr',
            'December' => 'dekabr',
        ];
        $contract = LabContract::query()
            ->where('lab_contracts.contract_number', $con_num)
            ->where('lab_contracts.status', LabClaim::STATUS_COMPLETED)
            ->with(['contractInfo.testCategory.parent',
                'contractInfo.testCategory.price'])->firstOrFail();
        $lab_claim = LabClaim::query()->withoutGlobalScope(LabClaimUserScope::class)->with([
            'region',
            'district',
            'labClaimUsers' => function ($q) {
                $q->where('role_id', UserRoleEnum::BUYURTMACHI->value)
                    ->with(['user.region', 'user.district']);
            }
        ])
            ->findOrFail($contract->lab_claim_id);
        if ($lab_claim->type === 1) {
            $contract = LabContract::query()->where('lab_claim_id', $lab_claim->id)
                ->whereIn('status', [LabClaim::STATUS_COMPLETED, LabClaim::STATUS_IN_LAB_CONTRACT_ACTION])
                ->with(['contractInfo.testCategory.parent',
                    'contractInfo.testCategory.price'])->first();
        } else {
            $contract = LabContract::query()->where('lab_claim_id', $lab_claim->id)
                ->whereIn('status', [LabClaim::STATUS_COMPLETED, LabClaim::STATUS_IN_LAB_CONTRACT_ACTION])
                ->with(['contractInfo.testCategory.parent',
                    'contractInfo.testCategory.price', 'contractInfo.workType', 'contractInfo.question', 'contractInfo.labBlock'])->first();
        }
        $details = $contract->contractInfo;
        $inspeksiya_confirmed_at_par = $contract->inspeksiya_confirmed_at ? Carbon::parse($contract->inspeksiya_confirmed_at) : null;
        $customer_confirmed_at_par = $contract->customer_confirmed_at ? Carbon::parse($contract->customer_confirmed_at) : null;
        $month_name_ins = $inspeksiya_confirmed_at_par ? $months_uz[$inspeksiya_confirmed_at_par->format('F')] : null;
        $month_name_cus = $customer_confirmed_at_par ? $months_uz[$customer_confirmed_at_par->format('F')] : null;
        $inspeksiya_confirmed_at = $inspeksiya_confirmed_at_par->format('Y') . ' yil ' . $inspeksiya_confirmed_at_par->format('d') . ' ' . $month_name_ins;
        $customer_confirmed_at = $customer_confirmed_at_par->format('Y') . ' yil ' . $customer_confirmed_at_par->format('d') . ' ' . $month_name_cus;
        $inspection_director = User::withTrashed()->find($contract->inspection_director_id);
        $inspeksiya = Inspection::query()->where(['region_id' => $lab_claim->region_id])->first();
        $rekvizit = Rekvizit::query()->where('district_id', $lab_claim->district_id)->first();
        $contract_total_price = $contract->con_total_price_with_nds ?? 0;
        $complication_total_price = $contract?->complication->total_with_qqs_price ?? 0;
        $contract_full_price = $contract_total_price + $complication_total_price;
        $pdf = Pdf::loadView('lab', compact('lab_claim', 'contract', 'customer_confirmed_at',
            'inspeksiya_confirmed_at', 'inspeksiya', 'details', 'rekvizit', 'inspection_director', 'contract_full_price'));
        return $pdf->stream('lab.pdf');
    }

    public function getDataOperator(Request $request)
    {
        $type = (int)$request->get('type');
        $taskId = $request->get('task_id');

        try {
            if (!$type || !$taskId) {
                return response()->json([
                    'success' => false,
                    'message' => 'type yoki task_id kiritilmagan'
                ], 422);
            }

            switch ($type) {
                case 1:
                case 2:
                    $response = DxaResponse::query()
                        ->where('notification_type', $type)
                        ->where(function ($q) use ($taskId) {
                            $q->where('task_id', $taskId)
                                ->orWhere('manual_task_id', $taskId);
                        })
                        ->first();

                    if (!$response) {
                        return response()->json([
                            'success' => false,
                            'message' => 'DXA maʼlumot topilmadi',
                        ], 404);
                    }

                    return response()->json([
                        'success' => true,
                        'data' => DxaResponseResource::make($response),
                    ]);

                case 3:
                    $article = $this->articleRepository->findById($taskId);

                    if (!$article) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Article topilmadi',
                        ], 404);
                    }

                    return response()->json([
                        'success' => true,
                        'data' => ArticleResource::make($article),
                    ]);

                case 4:
                    $claim = $this->claimService->getClaimByGUID($taskId);

                    if (!$claim) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Claim topilmadi',
                        ], 404);
                    }

                    return response()->json([
                        'success' => true,
                        'data' => $claim,
                    ]);

                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Noto‘g‘ri type kiritildi',
                    ], 400);
            }

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Server xatosi: ' . $e->getMessage(),
            ], 500);
        }
    }


    public function getObjectUrban(Request $request)
    {
        $type = (int)$request->get('type');
        $taskId = $request->get('task_id');

        try {
            if (!$type || !$taskId) {
                return response()->json([
                    'success' => false,
                    'message' => 'type yoki task_id kiritilmagan'
                ], 422);
            }

            switch ($type) {
                case 1:
                case 2:
                    $response = DxaResponse::query()
                        ->where('notification_type', $type)
                        ->where(function ($q) use ($taskId) {
                            $q->where('task_id', $taskId)
                                ->orWhere('manual_task_id', $taskId);
                        })
                        ->first();

                    if (!$response) {
                        return response()->json([
                            'success' => false,
                            'message' => 'DXA maʼlumot topilmadi',
                        ], 404);
                    }

                    return response()->json([
                        'success' => true,
                        'data' => DxaResponseResource::make($response),
                    ]);

                case 3:
                    $object = Article::query()
                        ->with([
                            'region:id,soato,name_uz',
                            'district:id,soato,name_uz',
                            'sphere:id,name_uz',
                            'program',
                            'objectType',
                            'objectStatus',
                            'difficulty',
                            'fundingSource',
                            'objectSector',

                            'blocks',

                            'users',

                            'paymentLogs',
                            'paymentLaboratories',
                            'labContract',

                            'monitorings.role:id,name',
                            'monitorings.user:id,name,surname,middle_name,phone',
                            'monitorings.images',

                            'histories.documents',
                        ])
                        ->find($taskId);
                    if (!$object) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Article topilmadi',
                        ], 404);
                    }
                    $data = ArticleUrbanResource::make($object)->resolve();

                    $data['blocks'] = $this->getBlocks($object);

                    return response()->json([
                        'success' => true,
                        'data' => $data,
                    ]);

                case 4:
                    $model = Claim::query()->where('guid', $taskId)->first();

                    if (!$model) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Claim topilmadi',
                        ], 404);
                    }
                    $claim = $this->claimService->getClaimByTaskId($taskId);

                    return response()->json([
                        'success' => true,
                        'data' => $claim,
                    ]);

                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Noto‘g‘ri type kiritildi',
                    ], 400);
            }

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Server xatosi: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getInspectors()
    {
        try {

            $inspectors = User::query()->with(['region', 'district', 'roles'])
                ->when(request('region_soato'), function ($query) {
                    $query->whereHas('region', function ($q) {
                        $q->where('region_soato', request('region_soato'));
                    });
                })
                ->when(request('district_soato'), function ($query) {
                    $query->whereHas('district', function ($q) {
                        $q->where('district_soato', request('district_soato'));
                    });
                })
                ->whereHas('roles', function ($query) {
                    $query->where('roles.id', UserRoleEnum::INSPECTOR->value);
                })
                ->when(request('name'), function ($query) {
                    $query->searchByFullName(request('name'));
                })
                ->paginate(request('per_page', 10));


            return response()->json([
                'data' => UserResource::collection($inspectors),
                'meta' => [
                    'lastPage' => $inspectors->lastPage(),
                    'total' => $inspectors->total(),
                    'perPage' => $inspectors->perPage(),
                    'current_page' => $inspectors->currentPage()
                ]
            ]);

        } catch (\Exception $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        }
    }

    public function objectGetProcuracy()
    {

        $filters = array_filter([
            'region_soato' => request('region_soato'),
            'limit' => request('limit'),
            'vendor_inn' => request('vendor_inn'),
            'customer_inn' => request('customer_inn'),
        ], fn($v) => $v !== null);

        if (!$filters) {
            return response()->json(['message' => 'Ma\'lumot topilmadi'], 404);
        }

        $query = Article::query()
            ->select('id', 'object_status_id');

        if (isset($filters['limit'])) {
            $query->whereRaw("CAST(construction_cost AS DECIMAL) > ?", [$filters['limit']]);
        }

        if (isset($filters['region_soato'])) {
            $query->whereHas('region', function ($q) use ($filters) {
                $q->where('soato', $filters['region_soato']);
            });
        }


        if (isset($filters['vendor_inn'])) {
            $query->whereHas('users', function ($q) use ($filters) {
                $q->where('users.pinfl', $filters['vendor_inn'])
                    ->where('article_users.role_id', UserRoleEnum::QURILISH->value);
            });
        }

        if (isset($filters['customer_inn'])) {
            $query->whereHas('users', function ($q) use ($filters) {
                $q->where('users.pinfl', $filters['customer_inn'])
                    ->where('article_users.role_id', UserRoleEnum::BUYURTMACHI->value);
            });
        }


        $articles = $query->get()->groupBy('object_status_id');

        $data = [
            'all' => $articles->flatten()->count(),
            'in_process' => [
                'count' => $articles->get(ObjectStatusEnum::PROGRESS->value, collect())->count(),
                'ids' => $articles->get(ObjectStatusEnum::PROGRESS->value, collect())->pluck('id')->toArray(),
            ],
            'frozen' => [
                'count' => $articles->get(ObjectStatusEnum::FROZEN->value, collect())->count(),
                'ids' => $articles->get(ObjectStatusEnum::FROZEN->value, collect())->pluck('id')->toArray(),
            ],
            'suspended' => [
                'count' => $articles->get(ObjectStatusEnum::SUSPENDED->value, collect())->count(),
                'ids' => $articles->get(ObjectStatusEnum::SUSPENDED->value, collect())->pluck('id')->toArray(),
            ],
            'canceled' => [
                'count' => $articles->get(ObjectStatusEnum::CANCELED->value, collect())->count(),
                'ids' => $articles->get(ObjectStatusEnum::CANCELED->value, collect())->pluck('id')->toArray(),
            ],
            'submitted' => [
                'count' => $articles->get(ObjectStatusEnum::SUBMITTED->value, collect())->count(),
                'ids' => $articles->get(ObjectStatusEnum::SUBMITTED->value, collect())->pluck('id')->toArray(),
            ],
        ];


        return response()->json($data);
    }


    public function objectDetail()
    {
        try {
            $id = request('id');

            if (!$id) {
                return response()->json(['message' => "Ma'lumot olishda xatolik"], 404);
            }

            $object = Article::with([
                'region',
                'district',
                'sphere',
                'program',
                'objectType',
                'objectStatus',
                'difficulty',
                'fundingSource',
                'objectSector',
                'blocks',
                'blocks.mode',
                'blocks.type',
                'blocks.appearanceType',
                'users.roles',
                'paymentLogs',
                'paymentLaboratories',
                'monitorings.images',
                'monitorings.regulations',
                'monitorings.regulations.regulationViolations.violation.images',
            ])->find($id);


            if (!$object) {
                return response()->json(['message' => "Ma'lumot topilmadi"], 404);
            }
            $data = ObjectProcuracyResource::make($object);

            return response()->json($data);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }


    public function objectConclusion($id)
    {
        try {
            $object = Article::find($id);

            if (!$object) {
                return response()->json([
                    'message' => "Ma'lumot topilmadi"
                ], 404);
            }

            $conclusionFile = Cache::remember(
                "object_conclusion:{$object->reestr_number}",
                now()->addMinutes(10),
                function () use ($object) {

                    $data = getData(
                        config('app.gasn.reestr'),
                        $object->reestr_number
                    );

                    return $data['data']['conclusion_file'] ?? null;
                }
            );

            if (!$conclusionFile) {
                return response()->json([
                    'message' => "Ma'lumot topilmadi"
                ], 404);
            }

            return response()->json([
                'url' => $conclusionFile
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Server xatoligi'
            ], 500);
        }
    }

    public function mygovFiles($id)
    {
        try {

            $object = Article::query()->find($id);

            if (!$object) {
                return response()->json(['message' => "Ma'lumot topilmadi"], 404);
            }

            try {
                $objectPdf = (new InformationService())
                    ->getConclusionPDF($object->task_id);
            } catch (\Throwable $e) {
                $objectPdf = null;
            }

            try {
                if ($object->object_type_id == 1) {
                    $response = Http::withBasicAuth(
                        config('app.mygov.login'),
                        config('app.mygov.password'),
                    )->get(config('app.mygov.linear') . '/get-pdf?id=' . $object->task_id);
                    $arizaPdf = $response->json();
                } else {
                    $response = Http::withBasicAuth(
                        config('app.mygov.login'),
                        config('app.mygov.password'),
                    )->get(config('app.mygov.url') . '/get-pdf?id=' . $object->task_id);
                    $arizaPdf = $response->json();
                }
            } catch (\Throwable $e) {
                $arizaPdf = null;
            }
            return response()->json([
                'arizaPdf' => $arizaPdf,
                'objectPdf' => $objectPdf,
                'closed_file' => $object->closed_file
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Server xatoligi'], 500);
        }
    }

    public function blockInfo()
    {
        try {
            $filters = request()->only(['region', 'type', 'year', 'district']);

            $blockCount = Block::query()
                ->with(['object'])
                ->when(!empty($filters['region']), function ($query) use ($filters) {
                    $query->whereHas('object', function ($q) use ($filters) {
                        $q->whereHas('region', function ($q2) use ($filters) {
                            $q2->where('soato', $filters['region']);
                        });
                    });
                })
                ->when(!empty($filters['district']), function ($query) use ($filters) {
                    $query->whereHas('object', function ($q) use ($filters) {
                        $q->whereHas('district', function ($q2) use ($filters) {
                            $q2->where('soato', $filters['district']);
                        });
                    });
                })
                ->when(!empty($filters['type']), function ($query) use ($filters) {
                    $query->where('block_type_id', $filters['type']);
                }, function ($query) {
                    $query->whereIn('block_type_id', [1, 25]);
                })
                ->when(!empty($filters['year']), function ($query) use ($filters) {
                    $start = Carbon::create($filters['year'])->startOfYear();
                    $end = Carbon::create($filters['year'])->endOfYear();
                    $query->whereHas('object', function ($q) use ($start, $end) {
                        $q->whereBetween('closed_at', [$start, $end]);
                    });
                })
                ->where('accepted', true)
                ->count();


            $operators = ClaimOperator::query()
                ->with(['claim:id,region,status,end_date'])
                ->select(['id', 'operator_answer', 'claim_id'])
                ->when(!empty($filters['region']), function ($query) use ($filters) {
                    $query->whereHas('claim', function ($q) use ($filters) {
                        $q->where('region', $filters['region']);
                    });
                })
                ->when(!empty($filters['district']), function ($query) use ($filters) {
                    $query->whereHas('claim', function ($q) use ($filters) {
                        $q->where('district', $filters['district']);
                    });
                })
                ->when(!empty($filters['year']), function ($query) use ($filters) {
                    $start = Carbon::create($filters['year'])->startOfYear();
                    $end = Carbon::create($filters['year'])->endOfYear();

                    $query->whereHas('claim', function ($q) use ($start, $end) {
                        $q->whereBetween('end_date', [$start, $end]);
                    });
                })
                ->whereHas('claim', function ($q) {
                    $q->where('status', ClaimStatuses::TASK_STATUS_CONFIRMED);
                })
                ->get();

            $totalArea = 0;
            $totalTotalArea = 0;
            $totalLivingArea = 0;
            $totalUseArea = 0;
            $apartmentCount = 0;

            foreach ($operators as $operator) {

                $answers = json_decode($operator->operator_answer, true);

                if (!is_array($answers)) {
                    continue;
                }

                $filtered = collect($answers)
                    ->when(
                        !empty($filters['type']),
                        function ($collection) use ($filters) {
                            return $collection->where('type', $filters['type']);
                        },
                        function ($collection) {
                            return $collection->whereIn('type', ['1', '25']);
                        }
                    );


                $totalArea += $filtered->sum(fn($item) => (float)$item['area']);
                $totalTotalArea += $filtered->sum(fn($item) => (float)$item['total_area']);
                $totalLivingArea += $filtered->sum(fn($item) => (float)$item['living_area']);
                $totalUseArea += $filtered->sum(fn($item) => (float)$item['total_use_area']);
                $apartmentCount += $filtered->sum(fn($item) => (float)($item['apartment_count'] ?? 0));
            }

            return [
                'area_sum' => $totalArea,
                'total_area_sum' => $totalTotalArea,
                'living_area_sum' => $totalLivingArea,
                'total_use_area_sum' => $totalUseArea,
                'block_sum' => $blockCount,
                'apartment_count' => $apartmentCount,
            ];

        } catch (\Throwable $e) {
            return response()->json(['message' => 'Serverda xatolik aniqlandi'], 500);
        }
    }


    public function objectInfo($id)
    {
        try {
            $excludedStatuses = [
                RegulationStatusEnum::CANCELLED->value,
                RegulationStatusEnum::ELIMINATED->value,
                RegulationStatusEnum::LATE_EXECUTION->value,
            ];

            $object = Article::query()
                ->with([
                    'region:id,soato',
                    'district:id,soato',

                    'blocks:id,article_id,name,construction_area,floor,accepted,count_apartments',

                    'objectStatus',

                    'users' => fn($q) => $q->wherePivot(
                        'role_id',
                        UserRoleEnum::BUYURTMACHI->value
                    ),

                    'vendor' => fn($q) => $q->wherePivot(
                        'role_id',
                        UserRoleEnum::QURILISH->value
                    ),

                    'claims' => fn($q) => $q
                        ->where('status', ClaimStatuses::TASK_STATUS_CONFIRMED)
                        ->with('monitoring'),
                ])
                ->withCount([
                    'monitorings',

                    'regulations',

                    'regulations as active_regulations_count' => fn($q) => $q
                        ->whereNotIn('regulation_status_id', $excludedStatuses),
                ])
                ->find($id);

            if (!$object) {
                return response()->json([
                    'message' => 'Ma\'lumot topilmadi',
                ], 404);
            }

            $customer = $object->users->first();
            $vendor = $object->vendor->first();

            return response()->json([
                'id' => $object->id,

                'lat' => $object->lat,
                'long' => $object->long,
                'name' => $object->name,

                'status' => $object->objectStatus
                    ? ObjectStatusResource::make($object->objectStatus)
                    : null,

                'task_id' => $object->task_id ?? $object->manual_task_id,

                'deadline' => $object->deadline,
                'closed_at' => $object->closed_at,

                'sphere_id' => $object->sphere_id,
                'difficulty' => $object->difficulty_category_id,

                'block_count' => $object->blocks->count(),

                'apartment_count' => $object->blocks->sum(
                    fn($block) => (int) $block->count_apartments
                ),

                'region_soato' => $object->region?->soato,
                'district_soato' => $object->district?->soato,

                'reestr_number' => $object->reestr_number,
                'number_protocol' => $object->number_protocol,
                'location_building' => $object->location_building,

                'monitoring_count' => $object->monitorings_count,

                'regulation_count' => $object->regulations_count,

                'active_regulation_count' => $object->active_regulations_count,

                'block_info' => $object->claims
                    ->pluck('monitoring.operator_field')
                    ->flatten(1)
                    ->values(),

                'blocks' => $object->blocks
                    ->map(fn($block) => [
                        'id' => $block->id,
                        'name' => $block->name,
                        'area' => $block->construction_area,
                        'floor' => $block->floor,
                        'accepted' => $block->accepted,
                        'apartment_count' => $block->count_apartments,
                    ])
                    ->values(),

                'organization_name' => $customer?->organization_name,
                'organization_inn' => $customer?->pinfl,

                'vendor_name' => $vendor?->organization_name,
                'vendor_inn' => $vendor?->pinfl,

                'cameras' => $this->cameraService->getByShaffofId($object->id),
            ]);

        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Serverda xatolik yuz berdi',
            ], 500);
        }
    }

    public function objectTender()
    {
        try {
            $articles = Article::query()
                ->select([
                    'id',
                    'gnk_id',
                    'name',
                    'task_id',
                    'manual_task_id',
                    'closed_at',
                    'created_at',
                    'deadline',
                    'object_status_id'
                ])
                ->with([
                    'organization',
                    'vendor',
                    'objectStatus',
                ])
                ->where('gnk_id', request('gnk_id'))
                ->whereNot('object_status_id', ObjectStatusEnum::CANCELED)
                ->get()
                ->map(function ($article) {
                    return [
                        "gnk_id" => (int)$article->gnk_id,
                        "obyekt_nomi" => $article->name,
                        'customer_name' => optional($article->organization->first())->organization_name,
                        'customer_inn' => optional($article->organization->first())->pinfl,
                        'pudratchi_name' => optional($article->vendor->first())->organization_name,
                        'pudratchi_inn' => optional($article->vendor->first())->pinfl,
                        "task_id" => $article->task_id ?? $article->manual_task_id,
                        "dalolatnoma_file_link" => $article->closed_file,
                        "tasdiqlangan_sana" => $article->closed_at,
                        "deadline" => $article->deadline,
                        'objectStatus' => [
                            'id' => $article->object_status_id,
                            'name' => $article->objectStatus->name,
                        ],
                        "created_date" => optional($article->created_at)->format('Y-m-d H:i:s'),
                    ];
                });

            return response()->json($articles);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }


    public function extendedInfo()
    {
        try {
            $filters = request()->only(['region', 'type', 'year', 'district']);

            $blockCount = Block::query()
                ->with(['object'])
                ->when(!empty($filters['region']), function ($query) use ($filters) {
                    $query->whereHas('object', function ($q) use ($filters) {
                        $q->whereHas('region', function ($q2) use ($filters) {
                            $q2->where('soato', $filters['region']);
                        });
                    });
                })
                ->when(!empty($filters['district']), function ($query) use ($filters) {
                    $query->whereHas('object', function ($q) use ($filters) {
                        $q->whereHas('district', function ($q2) use ($filters) {
                            $q2->where('soato', $filters['district']);
                        });
                    });
                })
                ->when(!empty($filters['type']), function ($query) use ($filters) {
                    $query->where('block_type_id', $filters['type']);
                }, function ($query) {
                    $query->whereIn('block_type_id', [1, 25]);
                })
                ->when(!empty($filters['year']), function ($query) use ($filters) {
                    $start = Carbon::create($filters['year'])->startOfYear();
                    $end = Carbon::create($filters['year'])->endOfYear();
                    $query->whereHas('object', function ($q) use ($start, $end) {
                        $q->whereBetween('closed_at', [$start, $end]);
                    });
                })
                ->where('accepted', true)
                ->count();


            $operators = ClaimOperator::query()
                ->with(['claim:id,region,status,end_date'])
                ->select(['id', 'operator_answer', 'claim_id'])
                ->when(!empty($filters['region']), function ($query) use ($filters) {
                    $query->whereHas('claim', function ($q) use ($filters) {
                        $q->where('region', $filters['region']);
                    });
                })
                ->when(!empty($filters['district']), function ($query) use ($filters) {
                    $query->whereHas('claim', function ($q) use ($filters) {
                        $q->where('district', $filters['district']);
                    });
                })
                ->when(!empty($filters['year']), function ($query) use ($filters) {
                    $start = Carbon::create($filters['year'])->startOfYear();
                    $end = Carbon::create($filters['year'])->endOfYear();

                    $query->whereHas('claim', function ($q) use ($start, $end) {
                        $q->whereBetween('end_date', [$start, $end]);
                    });
                })
                ->whereHas('claim', function ($q) {
                    $q->where('status', ClaimStatuses::TASK_STATUS_CONFIRMED);
                })
                ->get();

            $totalArea = 0;
            $totalTotalArea = 0;
            $totalLivingArea = 0;
            $totalUseArea = 0;
            $apartmentCount = 0;

            foreach ($operators as $operator) {

                $answers = json_decode($operator->operator_answer, true);

                if (!is_array($answers)) {
                    continue;
                }

                $filtered = collect($answers)
                    ->when(
                        !empty($filters['type']),
                        function ($collection) use ($filters) {
                            return $collection->where('type', $filters['type']);
                        },
                        function ($collection) {
                            return $collection->whereIn('type', ['1', '25']);
                        }
                    );


                $totalArea += $filtered->sum(fn($item) => (float)$item['area']);
                $totalTotalArea += $filtered->sum(fn($item) => (float)$item['total_area']);
                $totalLivingArea += $filtered->sum(fn($item) => (float)$item['living_area']);
                $totalUseArea += $filtered->sum(fn($item) => (float)$item['total_use_area']);
                $apartmentCount += $filtered->sum(fn($item) => (float)($item['apartment_count'] ?? 0));
            }

            $total_objects = Article::query();
//                ->when(isset($filters['year']), function ($query) use ($filters) {
//                    $start = Carbon::create($filters['year'])->startOfYear();
//                    $end = Carbon::create($filters['year'])->endOfYear();
//                    $query->whereBetween('created_at', [$start, $end]);
//                });
            $result = $total_objects->selectRaw("
                      COUNT(*) as total,
                      COUNT(*) FILTER (WHERE object_status_id = ?) as new_count,
                      COUNT(*) FILTER (WHERE object_status_id = ?) as in_process_count,
                      COUNT(*) FILTER (WHERE object_status_id = ?) as frozen_count,
                      COUNT(*) FILTER (WHERE object_status_id = ?) as stopped_count,
                      COUNT(*) FILTER (WHERE object_status_id = ?) as submitted_count,
                      COUNT(*) FILTER (WHERE object_status_id = ?) as canceled_count
                      ", [ObjectStatusEnum::NEW->value,
                ObjectStatusEnum::PROGRESS->value,
                ObjectStatusEnum::FROZEN->value,
                ObjectStatusEnum::SUSPENDED->value,
                ObjectStatusEnum::SUBMITTED->value,
                ObjectStatusEnum::CANCELED->value,
            ])->first();

            $total_objects_count = $result->total;
            $new_objects_count = $result->new_count;
            $in_process_objects_count = $result->in_process_count;
            $frozen_objects_count = $result->frozen_count;
            $stopped_objects_count = $result->stopped_count;
            $submitted_objects_count = $result->submitted_count;
            $canceled_objects_count = $result->canceled_count;

            $total_claims = Claim::query()
                ->when(isset($filters['year']), function ($query) use ($filters) {
                    $start = Carbon::create($filters['year'])->startOfYear();
                    $end = Carbon::create($filters['year'])->endOfYear();
                    $query->whereBetween('created_at', [$start, $end]);
                });


            $in_process_statuses = [
                ClaimStatuses::TASK_STATUS_ACCEPTANCE,
                ClaimStatuses::TASK_STATUS_ANOTHER,
                ClaimStatuses::TASK_STATUS_SENT_ORGANIZATION,
                ClaimStatuses::TASK_STATUS_INSPECTOR,
//                ClaimStatuses::TASK_STATUS_SENT_ANOTHER_ORG,
                ClaimStatuses::TASK_STATUS_OPERATOR,
                ClaimStatuses::TASK_STATUS_DIRECTOR,
                ClaimStatuses::PAY_PROGRESS

            ];
            $rejected_statuses = [
                ClaimStatuses::TASK_STATUS_REJECTED,
                ClaimStatuses::TASK_STATUS_ORGANIZATION_REJECTED
            ];
            $result_2 = $total_claims->selectRaw("
            COUNT(*) as total,
            COUNT(*) FILTER (WHERE status = ?) as confirmed_count,
            COUNT(*) FILTER (WHERE status = ?) as new_count,
            COUNT(*) FILTER (WHERE status IN (" . implode(',', array_fill(0, count($in_process_statuses), '?')) . ")) as in_process_count,
            COUNT(*) FILTER (WHERE status IN (" . implode(',', array_fill(0, count($rejected_statuses), '?')) . ")) as rejected_count,
            COUNT(*) FILTER (WHERE status = ?) as cancelled_count
            ", [
                ClaimStatuses::TASK_STATUS_CONFIRMED,
                ClaimStatuses::TASK_STATUS_ATTACH_OBJECT,
                ...$in_process_statuses,
                ...$rejected_statuses,
                ClaimStatuses::TASK_STATUS_CANCELLED
            ])->first();

            $total_claims_count = $result_2->total;
            $confirmed_claims_count = $result_2->confirmed_count;
            $new_claims_count = $result_2->new_count;
            $in_process_claims_count = $result_2->in_process_count;
            $rejected_claims_count = $result_2->rejected_count;
            $cancelled_claims_count = $result_2->cancelled_count;

            return [
                'area_sum' => $totalArea,
                'total_area_sum' => $totalTotalArea,
                'living_area_sum' => $totalLivingArea,
                'total_use_area_sum' => $totalUseArea,
                'block_sum' => $blockCount,
                'apartment_count' => $apartmentCount,
                'objects_info' => [
                    'total_objects_count' => $total_objects_count,
                    'new_objects_count' => $new_objects_count,
                    'in_process_objects_count' => $in_process_objects_count,
                    'frozen_objects_count' => $frozen_objects_count,
                    'stopped_objects_count' => $stopped_objects_count,
                    'submitted_objects_count' => $submitted_objects_count,
                    'canceled_objects_count' => $canceled_objects_count,
                ],
                'claims_info' => [
                    'total' => $total_claims_count,
                    'confirmed' => $confirmed_claims_count,
                    'new' => $new_claims_count,
                    'in_process' => $in_process_claims_count,
                    'rejected' => $rejected_claims_count,
                    'cancelled' => $cancelled_claims_count,
                ]
            ];

        } catch (\Throwable $e) {
            return response()->json(['message' => $e], 500);
        }
    }

    public function getClaimGovObjects()
    {
        $objects = Article::query()->where('object_status_id', ObjectStatusEnum::SUBMITTED->value)
            ->leftJoin('claim_govs as cg', 'cg.object_id', '=', 'articles.id')
            ->leftJoin('declarations as d', 'd.object_id', '=', 'articles.id')
            ->where('cg.status', ClaimStatuses::TASK_STATUS_CONFIRMED)
            ->select('articles.id as id', 'articles.closed_at as closed_at', 'articles.gnk_id as gnk_id', 'articles.name as obyekt_nomi',
                'articles.task_id as task_id', 'd.construction_price as construction_price',
                'd.instrument_price as instrument_price', 'd.fond_construction_price as fond_construction_price',
                'd.fond_instrument_price as fond_instrument_price')
            ->get()
            ->map(function ($object) {
                return [
                    'object_id' => $object->id,
                    'gnk_id' => $object->gnk_id,
                    'dalolatnoma_file_link' => config('app.url') . '/api/pdf-dalolatnoma/' . $object->id,
                    'tasdiqlangan_sana' => Carbon::parse($object->closed_at)->format('Y-m-d H:i:s'),
                    'created_date' => Carbon::parse($object->created_at)->format('Y-m-d H:i:s'),
                    'construction_price' => $object->construction_price,
                    'instrument_price' => $object->instrument_price,
                    'fond_construction_price' => $object->fond_construction_price,
                    'fond_instrument_price' => $object->fond_instrument_price
                ];
            });
        return response()->json($objects);
    }

    public function objectStatistic()
    {
        try {
            $filters = \request()->only(['soato']);
            $query = Article::query()
                ->with(['region'])
                ->when(!empty($filters['soato']), function ($query) use ($filters) {
                    $query->whereHas('region', function ($q) use ($filters) {
                        $q->where('soato', $filters['soato']);
                    });
                });

            $data = [
                'all' => $query->clone()->count(),
                'progress' => $query->clone()->where('object_status_id', ObjectStatusEnum::PROGRESS)->count(),
                'frozen' => $query->clone()->where('object_status_id', ObjectStatusEnum::FROZEN)->count(),
                'suspended' => $query->clone()->where('object_status_id', ObjectStatusEnum::SUSPENDED)->count(),
                'submitted' => $query->clone()->where('object_status_id', ObjectStatusEnum::SUBMITTED)->count(),
                'cancelled' => $query->clone()->where('object_status_id', ObjectStatusEnum::CANCELED)->count(),
            ];

            return response()->json($data);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Xatolik aniqlandi'], 404);
        }
    }

    public function regulations()
    {
        try {

            $regulations = Regulation::query()
                ->with([
                    'object:id,name,task_id,manual_task_id,region_id,district_id,location_building',
                    'object.region:id,name_uz,soato',
                    'object.district:id,name_uz,soato',
                    'regulationStatus:id,status',
                    'fine:id,regulation_id,decision_series,decision_number',
                ])
                ->select([
                    'id',
                    'object_id',
                    'regulation_status_id',
                    'regulation_number',
                    'deadline',
                ])
                ->paginate(30);

            $regulations->getCollection()->transform(function ($regulation) {

                return [
                    'regulation_number' => $regulation->regulation_number,

                    'object_name' => $regulation->object?->name,

                    'task_id' => $regulation->object?->task_id
                        ? (string)$regulation->object->task_id
                        : $regulation->object?->manual_task_id,

                    'region' => $regulation->object?->region ? [
                        'id' => $regulation->object->region_id,
                        'name' => $regulation->object->region->name_uz,
                        'soato' => $regulation->object->region->soato,
                    ] : null,

                    'district' => $regulation->object?->district ? [
                        'id' => $regulation->object->district_id,
                        'name' => $regulation->object->district->name_uz,
                        'soato' => $regulation->object->district->soato,
                    ] : null,

                    'address' => $regulation->object?->location_building,

                    'status' => $regulation->regulationStatus?->status,

                    'deadline' => $regulation->deadline,

                    'fine' => $regulation->fine ? [
                        'decision_series' => $regulation->fine->decision_series,
                        'decision_number' => $regulation->fine->decision_number,
                    ] : null,
                ];
            });

            return response()->json([
                'data' => $regulations->items(),
                'current_page' => $regulations->currentPage(),
                'last_page' => $regulations->lastPage(),
                'per_page' => $regulations->perPage(),
                'total' => $regulations->total(),
            ]);

        } catch (\Exception $exception) {

            return response()->json([
                'message' => $exception->getMessage()
            ], 404);
        }
    }

    public function userObjects()
    {
        try {
            $roleId = request('role_id');
            $pin = request('pin');

            $user = User::query()
                ->where('pinfl', $pin)
                ->first();

            if (!$user) {
                return response()->json([
                    'message' => 'Foydalanuvchi topilmadi'
                ], 404);
            }

            $objects = $user->objects()
                ->wherePivot('role_id', $roleId)
                ->with(['region', 'district', 'objectStatus', 'difficulty'])
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'task_id' => $item->task_id ?? $item->manual_task_id,
                        'name' => $item->name,
                        'region_soato' => $item->region?->soato,
                        'district_soato' => $item->district?->soato,
                        'status' => $item->objectStatus ? [
                            'id' => $item->objectStatus->id,
                            'name' => $item->objectStatus->name,
                        ] : null,
                        'toifa' => $item->difficulty?->id,
                    ];
                });
            $regulations = Regulation::query()
                ->where('role_id', $roleId)
                ->where('user_id', $user->id)
                ->where('created_at', '>=', Carbon::now()->subDays(365));


            return response()->json([
                'objects' => $objects,
                'regulations' => [
                    'all' => (clone $regulations)->count(),
                    'fine' => (clone $regulations)
                        ->whereNotNull('lawyer_status_id')
                        ->count(),
                ],
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function registerCount()
    {
        $registers = $this->dxaResponseService->getRegisters(null, UserRoleEnum::RESPUBLIKA_KUZATUVCHI->value, 1);
        $filters = request()->only(['district_soato', 'region_soato']);
        $query = $this->dxaResponseService->searchRegisters($registers, $filters);

        $data = [
            'all' => $query->clone()->count(),
            'new' => $query->clone()->where('dxa_response_status_id', DxaResponseStatusEnum::NEW)->count(),
            'in_inspector' => $query->clone()->where('dxa_response_status_id', DxaResponseStatusEnum::SEND_INSPECTOR)->count(),
            'in_register' => $query->clone()->where('dxa_response_status_id', DxaResponseStatusEnum::IN_REGISTER)->count(),
            'accepted' => $query->clone()->where('dxa_response_status_id', DxaResponseStatusEnum::ACCEPTED)->count(),
            'rejected' => $query->clone()->where('dxa_response_status_id', DxaResponseStatusEnum::REJECTED)->count(),
            'cancelled' => $query->clone()->where('dxa_response_status_id', DxaResponseStatusEnum::CANCELED)->count(),
            'inspection' => $query->clone()->where('dxa_response_status_id', DxaResponseStatusEnum::INSPECTION)->count(),
        ];

        return response()->json($data);

    }

    public function checkCorrectionApi()
    {
        $authCredentials = [
            'username' => config('app.mygov.login'),
            'password' => config('app.mygov.password'),
        ];

        $apiDetails = [
            'url' => config('app.mygov.url') . '/update/id/' . '312049716' . '/action/resend-to-corrections',
            'form' => 'ResendToCorrectionFormNoticeBeginningConstructionWorks',
        ];

        $response = Http::withBasicAuth($authCredentials['username'], $authCredentials['password'])
            ->post($apiDetails['url'], [
                $apiDetails['form'] => [
                    "info_text_correction" => "testing new api",
                ],
            ]);

        return response()->json($response->json());
    }

    public function createControl()
    {
        try {
            $data = \request()->all();
            $objectId = $data['facility']['shaffof_id'];


            $object = Article::query()->where('id', (int)$objectId)->whereIn('object_status_id', [ObjectStatusEnum::FROZEN,ObjectStatusEnum::PROGRESS, ObjectStatusEnum::SUSPENDED])->first();

            if(!$object) {
                return response()->json([
                    'message' => 'Obyekt topilmadi',
                ], 400);
            }

            if (AiControl::query()->where('control_id', $data['id'])->exists()) {
                    return response()->json([
                        'message' => 'Qoidabuzarlik mavjud',
                    ], 400);
            }

            $control = new AiControl();
            $control->object_id = $objectId;
            $control->control_id = $data['id'];
            $control->video = $data['video'] ?? null;
            $control->type = $data['type'];
            $control->image = $data['type'] == 'ai_violation' ? $this->drawViolationImage($data['image'], $data['violation_ai_data']) : null;
            $control->file = $data['files'] ?? null;
            $control->desc = $data['description'];
            $control->status = 1;
            $control->send_operator = 1;
            $control->integration = false;
            $control->response = json_encode($data);
            $control->save();

            return response()->json([
                'message' => 'success',
            ], 200);


        }catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }

    }
    public function drawViolationImage(string $imageUrl, array $violations): string
    {
        $response = Http::timeout(60)->get($imageUrl);

        if (!$response->successful()) {
            throw new \Exception('Rasmni yuklab bo‘lmadi');
        }

        $manager = new ImageManager(new Driver());

        $image = $manager->read($response->body());

        foreach ($violations as $violation) {

            $x = (float) ($violation['x'] ?? 0);
            $y = (float) ($violation['y'] ?? 0);

            $width = (float) ($violation['width'] ?? 0);
            $height = (float) ($violation['height'] ?? 0);

            if ($width <= 0 || $height <= 0) {
                continue;
            }



            $left = (int) round($x - ($width / 2));
            $top = (int) round($y - ($height / 2));

            $boxWidth = (int) round($width);
            $boxHeight = (int) round($height);

            $left = max(0, $left);
            $top = max(0, $top);
//label olib tashlash
            $boxWidth = min(
                $boxWidth,
                $image->width() - $left
            );

            $boxHeight = min(
                $boxHeight,
                $image->height() - $top
            );

            if ($boxWidth <= 0 || $boxHeight <= 0) {
                continue;
            }
//olib tashlandi

            $image->drawRectangle(
                $left,
                $top,
                function (RectangleFactory $rectangle) use (
                    $boxWidth,
                    $boxHeight
                ) {
                    $rectangle->size(
                        $boxWidth,
                        $boxHeight
                    );

                    $rectangle->border(
                        'ff0000',
                        4
                    );
                }
            );


//            $class = $violation['class'] ?? 'unknown';
//
//            $confidence = isset($violation['confidence'])
//                ? round((float) $violation['confidence'] * 100, 1)
//                : null;
//
//            $label = $confidence !== null
//                ? "{$class}  {$confidence}%"
//                : $class;
//
//            $fontSize = max(
//                18,
//                min(
//                    28,
//                    (int) round($image->width() / 60)
//                )
//            );

//            $labelWidth = strlen($label) * ($fontSize * 0.6) + 24;
//            $labelHeight = $fontSize + 20;
//
//
//            $labelX = $left;
//
//            $labelY = $top - $labelHeight - 4;
//
//            if ($labelY < 0) {
//                $labelY = $top + 4;
//            }
//
//
//            if ($labelX + $labelWidth > $image->width()) {
//                $labelX = $image->width() - $labelWidth - 4;
//            }
//
//            $labelX = max(0, $labelX);
//
//
//            $image->drawRectangle(
//                (int) $labelX,
//                (int) $labelY,
//                function (RectangleFactory $rectangle) use (
//                    $labelWidth,
//                    $labelHeight
//                ) {
//                    $rectangle->size(
//                        (int) $labelWidth,
//                        (int) $labelHeight
//                    );
//
//                    $rectangle->background('cc0000');
//
//                    $rectangle->border(
//                        'ff0000',
//                        1
//                    );
//                }
//            );
//
//            $textX = (int) $labelX + 10;
//            $textY = (int) $labelY + ($labelHeight / 2);
//
//            $image->text(
//                $label,
//                $textX,
//                $textY,
//                function (FontFactory $font) use ($fontSize) {
//                    $font->size($fontSize);
//                    $font->color('ffffff');
//                    $font->align('left');
//                    $font->valign('middle');
//                }
//            );
        }

        $path = 'violations/' . uniqid('violation_', true) . '.jpg';

        Storage::disk('public')->put(
            $path,
            $image->toPng()
        );
//        return Storage::disk('public')->url($path);
        return $path;
    }
    private function getBlocks($object): array
    {
        return $object->blocks()
            ->with('type')
            ->get()
            ->map(function ($block) {
                $works = $this->questionService->getQuestionList(
                    blockId: $block->id,
                    block_type: $block->block_mode_id
                );

                $workTypes = collect($works)
                    ->reject(fn ($item) => $item['work_type_id'] == 14)
                    ->flatMap(function ($item) {
                        return collect($item['questions'])
                            ->groupBy('floor')
                            ->filter(function ($questions) {
                                return $questions->contains(
                                    fn ($q) => $q['work_type_status']->value == 2
                                );
                            })
                            ->map(function ($questions, $floor) use ($item) {
                                return [
                                    'name' => $floor
                                        ? "{$floor} - {$item['name']}"
                                        : $item['name'],
                                ];
                            })
                            ->values();
                    })
                    ->values()
                    ->toArray();

                return [
                    'id' => $block->id,
                    'name' => $block->name,
                    'floor' => $block->floor,
                    'construction_area' => $block->construction_area,
                    'count_apartments' => $block->count_apartments,
                    'work_types' => $workTypes,
                ];
            })
            ->values()
            ->toArray();
    }

    public function getClaimBlocks($data)
    {
        if (empty($data)) {
            return [];
        }
        return collect($data)
            ->map(function ($item) {

                $works = $this->questionService->getQuestionList(
                    blockId: $block['id'],
                    block_type: $block['block_mode_id']
                );

                $workTypes = collect($works)
                    ->reject(fn ($item) => $item['work_type_id'] == 14)
                    ->flatMap(function ($item) {
                        return collect($item['questions'])
                            ->groupBy('floor')
                            ->filter(function ($questions) {
                                return $questions->contains(
                                    fn ($q) => $q['work_type_status']->value == 2
                                );
                            })
                            ->map(function ($questions, $floor) use ($item) {
                                return [
                                    'name' => $floor
                                        ? "{$floor} - {$item['name']}"
                                        : $item['name'],
                                ];
                            });
                    })
                    ->values()
                    ->toArray();
                $block['work_types'] = $workTypes;

                return $block;
            })
            ->values()
            ->toArray();
    }

    public function getObjectByRole($pin)
    {
        try {

            $allowedRoleIds = strlen($pin) === 9
                ? [8, 9, 10]
                : [5, 6, 7, 8];

            $statuses = [
                ObjectStatusEnum::PROGRESS,
                ObjectStatusEnum::FROZEN,
                ObjectStatusEnum::SUSPENDED,
                ObjectStatusEnum::SUBMITTED,
            ];

            $users = User::query()
                ->where('pinfl', $pin)
                ->whereHas('roles', fn($q) => $q->whereIn('roles.id', $allowedRoleIds))
                ->with([
                    'roles' => fn($q) => $q->whereIn('roles.id', $allowedRoleIds),
                    'objects' => fn($q) => $q
                        ->whereIn('object_status_id', $statuses)
                        ->wherePivotIn('role_id', $allowedRoleIds)
                        ->with([
                            'region',
                            'district',
                            'objectStatus',
                            'users',
                        ]),
                ])
                ->get();

            if ($users->isEmpty()) {
                throw new NotFoundHttpException('Foydalanuvchi topilmadi');
            }

            $roleNames = Role::query()
                ->pluck('name', 'id');

            $data = $users->flatMap(function ($user) use ($roleNames) {

                return $user->roles->map(function ($role) use ($user, $roleNames) {

                    $objects = $user->objects
                        ->filter(fn($object) => (int) $object->pivot->role_id === (int) $role->id)
                        ->values();

                    return [
                        'role_name' => $role->name,
                        'object_count' => $objects->count(),
                        'object_list' => $objects->map(function ($object) use ($roleNames) {

                            return [
                                'id' => $object->id,
                                'name' => $object->name,
                                'cadastr' => $object->cadastral_number,
                                'address' => $object->location_building,
                                'lat' => $object->lat,
                                'long' => $object->long,
                                'task_id' => $object->task_id,

                                'district' => $object->district
                                    ? (new DistrictResource($object->district))->toArray(request())
                                    : null,

                                'region' => $object->region
                                    ? (new RegionResource($object->region))->toArray(request())
                                    : null,

                                'status' => $object->objectStatus
                                    ? (new ObjectStatusResource($object->objectStatus))->toArray(request())
                                    : null,

                                'users' => $object->users->map(fn($user) => [
                                    'role_id' => $user->pivot->role_id,
                                    'role' => $roleNames[$user->pivot->role_id] ?? null,
                                    'full_name' => $user->full_name ?? $user->organization_name,
                                    'phone' => $user->phone,
                                    'pin' => $user->pinfl,
                                ])->values()->toArray(),
                            ];

                        })->toArray(),
                    ];

                });

            })->values();

            $responseArr['user_objects'] = $data->toArray();

            $responseArr['claims'] = Claim::query()
                ->where('legal_tin', $pin)
                ->get(['id', 'guid', 'object_id', 'created_at', 'legal_name', 'status'])
                ->map(fn($claim) => [
                    'id' => $claim->id,
                    'guid' => $claim->guid,
                    'object_id' => $claim->object_id,
                    'created_at' => $claim->created_at,
                    'legal_name' => $claim->legal_name,
                    'status' => [
                        'status' => $claim->status,
                        'status_name' => ClaimStatuses::getLabel($claim->status),
                    ],
                ]);
            $responseArr['claim_gov'] = ClaimGov::query()
                ->where('legal_tin', $pin)
                ->get(['id', 'task_id', 'object_id', 'created_at', 'legal_name', 'status'])
                ->map(fn($claim) => [
                    'id' => $claim->id,
                    'guid' => $claim->task_id,
                    'object_id' => $claim->object_id,
                    'created_at' => $claim->created_at,
                    'legal_name' => $claim->legal_name,
                    'status' => [
                        'status' => $claim->status,
                        'status_name' => ClaimStatuses::getLabel($claim->status),
                    ],
                ]);

            return response()->json($responseArr);


         }catch (\Exception $exception){
            return response()->json([
                'success' => false,
                'error' => 'Xatolik aniqlandi',
                'error_message' => $exception->getMessage()
            ], 404);
        }
    }

    public function getObjectBySphere()
    {
        try {
            $articles = DB::table('articles')
                ->selectRaw('sphere_id, ARRAY_AGG(id) as ids')
                ->groupBy('sphere_id')
                ->get();
            return response()->json($articles);
        }  catch (\Exception $exception){
            return response()->json([
                'success' => false,
                'error' => 'Xatolik aniqlandi',
                'error_message' => $exception->getMessage()
            ], 404);
        }
    }

    public function getObjectById(Request $request)
    {
        try {
            $filters = $request->validate([
                'id' => ['nullable', 'integer'],
                'sphere_id' => ['nullable', 'integer'],
                'cadastral_number' => ['nullable', 'string'],
                'region_soato' => ['nullable', 'string'],
                'task_id' => ['nullable', 'string'],
                'customer_tin' => ['nullable', 'string'],
                'builder_tin' => ['nullable', 'string'],
                'district_soato' => ['nullable', 'string'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
                'page' => ['nullable', 'integer', 'min:1'],
            ]);

            $query = Article::query()
                ->with([
                    'region:id,name_uz,soato',
                    'district:id,name_uz,soato',
                    'objectType:id,name',
                    'blocks:id,article_id,name,floor,construction_area,count_apartments',
                    'fundingSource:id,description',
                    'applicantUser',
                    'vendor',
                    'organization',
                    'designer',
                    'sphere',
                    'difficulty',
                    'objectStatus',
                ]);

            $query
                ->when(
                    !empty($filters['cadastral_number']),
                    fn ($q) => $q->where(
                        'cadastral_number',
                        $filters['cadastral_number']
                    )
                )
                ->when(
                    !empty($filters['region_soato']),
                    fn ($q) => $q->whereHas(
                        'region',
                        fn ($region) => $region->where(
                            'soato',
                            $filters['region_soato']
                        )
                    )
                )
                ->when(
                    !empty($filters['customer_tin']),
                    fn ($q) => $q->whereHas(
                        'organization',
                        fn ($organization) => $organization->where(
                            'pinfl',
                            $filters['customer_tin']
                        )
                    )
                )
                ->when(
                    !empty($filters['builder_tin']),
                    fn ($q) => $q->whereHas(
                        'vendor',
                        fn ($vendor) => $vendor->where(
                            'pinfl',
                            $filters['builder_tin']
                        )
                    )
                )
                ->when(
                    !empty($filters['sphere_id']),
                    fn ($q) => $q->where(
                        'sphere_id',
                        $filters['sphere_id']
                    )
                )
                ->when(
                    !empty($filters['district_soato']),
                    fn ($q) => $q->whereHas(
                        'district',
                        fn ($district) => $district->where(
                            'soato',
                            $filters['district_soato']
                        )
                    )
                );



            if (!empty($filters['id'])) {
                $object = $query->find($filters['id']);

                if (!$object) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Obyekt topilmadi',
                    ], 404);
                }

                return response()->json([
                    'success' => true,
                    'data' => $this->formatObjectData($object)
                ]
                );
            }

            if (!empty($filters['task_id'])) {
                $object = $query->where('task_id', $filters['task_id'])->orWhere('manual_task_id', $filters['task_id'])->first();

                if (!$object) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Obyekt topilmadi',
                    ], 404);
                }

                return response()->json([
                    'success' => true,
                    'data' => $this->formatObjectData($object)
                    ]
                );
            }

            $objects = $query
                ->orderByDesc('id')
                ->paginate($filters['per_page'] ?? 20);

            $objects->getCollection()->transform(
                fn ($object) => $this->formatObjectData($object)
            );

            return response()->json([
                'success' => true,
                'data' => $objects->items(),
                'pagination' => [
                    'current_page' => $objects->currentPage(),
                    'last_page' => $objects->lastPage(),
                    'per_page' => $objects->perPage(),
                    'total' => $objects->total(),
                ],
            ]);

        } catch (ValidationException $exception) {
            throw $exception;

        } catch (\Throwable $exception) {
            return response()->json([
                'success' => false,
                'error' => 'Xatolik aniqlandi',
                'error_message' => $exception->getMessage()
            ], 500);
        }
    }

    private function formatObjectData(Article $object): array
    {
        $applicant = $object->applicantUser->first();
        $vendor = $object->vendor->first();
        $organization = $object->organization->first();
        $designer = $object->designer->first();



        return [
            'object_id' => $object->id,
            'object_name' => $object->name,
            'task_id' => $object->task_id,
            'designer_tin' => $designer?->pinfl,
            'designer_name' => $designer?->full_name
                ?? $designer?->organization_name,
            'sphere_id' => $object->sphere?->id,
            'sphere_name' => $object->sphere?->name_uz,
            'created_at' => $object->created_at,
            'closed_at' => $object->closed_at,
            'customer_name' => $organization?->full_name
                ?? $organization?->organization_name,
            'applicant_name' => $applicant?->full_name
                ?? $applicant?->organization_name,
            'builder_name' => $vendor?->full_name
                ?? $vendor?->organization_name,
            'cadastral_number' => $object->cadastral_number,
            'deadline' => $object->deadline,
            'difficulty_category' => $object->difficulty ? [
                'id' => $object->difficulty->id,
                'name' => $object->difficulty->difficulty,
            ] : null,
            'construction_works' => $object->construction_works,
            'region' => $object->region,
            'district' => $object->district,
            'object_type' => $object->objectType,
            'address' => $object->address,
            'number_protocol' => $object->number_protocol,
            'status_id' => $object->objectStatus?->id,
            'status_name' => $object->objectStatus?->name,
            'reestr_number' => $object->reestr_number,
            'funding_source' => $object->fundingSource,
            'construction_cost' => $object->construction_cost,
            'tin_applicant' => $applicant?->pinfl,
            'tin_general_contractor' => $vendor?->pinfl,
            'organization' => $organization?->pinfl,
            'rating' =>  is_string($object->rating)
                ? json_decode($object->rating, true)
                : $object->rating,
            'conclusion_file' => $object->task_id ?  url('api/conclusion/' . $object->task_id . '/download') : null,
            'expertise_file' => $object->reestr_number ? url('api/expertise/' . $object->task_id . '/download') : null,
            'protocol_file' => $object->number_protocol ? 'https://api.dx.mc.uz/api/module2/v1/tasks/'.$object->number_protocol.'/download-conclusion-file' : null,
            'blocks' => $object->blocks->map(fn ($block) => [
                'id' => $block->id,
                'name' => $block->name,
                'floor' => $block->floor,
                'construction_area' => $block->construction_area,
                'count_apartments' => $block->count_apartments,
                'work_types' => [],
            ])->values()->all(),
        ];
    }

    public function getUserRegulations()
    {
        try {
            $request = request();

            $request->validate([
                'pinfl' => ['required', 'digits:14'],
            ]);

            $user = User::query()
                ->where('pinfl', $request->input('pinfl'))
                ->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Foydalanuvchi topilmadi',
                ], 404);
            }

            $regulations = Regulation::query()
                ->with([
                    'object',
                    'monitoring.block',
                    'regulationStatus',
                    'fine',
                    'regulationUser',
                ])
                ->withCount('violations')
                ->where('user_id', $user->id)
                ->get();


            $userIds = $regulations
                ->flatMap(fn ($regulation) => [
                    $regulation->regulationUser?->from_user_id,
                    $regulation->regulationUser?->to_user_id,
                ])
                ->filter()
                ->unique();

            $roleIds = $regulations
                ->flatMap(fn ($regulation) => [
                    $regulation->regulationUser?->from_role_id,
                    $regulation->regulationUser?->to_role_id,
                ])
                ->filter()
                ->unique();

            $users = User::withTrashed()
                ->whereIn('id', $userIds)
                ->get()
                ->keyBy('id');
            $roles = Role::query()
                ->whereIn('id', $roleIds)
                ->get()
                ->keyBy('id');

            $data = $regulations->map(function ($regulation) use ($users, $roles) {
                $regulationUser = $regulation->regulationUser;


                $fromUser = $users->get($regulationUser?->from_user_id);
                $fromRole = $roles->get($regulationUser?->from_role_id);

                $responsibleUser = $users->get($regulationUser?->to_user_id);
                $responsibleRole = $roles->get($regulationUser?->to_role_id);
                return [
                    'id' => $regulation->id,
                    'object_name' => $regulation->object?->name,
                    'organization_name' => $regulation->object?->organization_name,
                    'task_id' => $regulation->object?->task_id,
                    'block_name' => $regulation->monitoring?->block?->name,
                    'block_id' => $regulation->monitoring?->block?->id,
                    'regulation_number' => $regulation->regulation_number,
                    'deadline' => $regulation->deadline,
                    'regulation_status' => RegulationStatusResource::make(
                        $regulation->regulationStatus
                    ),
                    'violation_count' => $regulation->violations_count,
                    'created_at' => $regulation->created_at,
                    'from_user' => [
                        'role' => $fromRole ? RoleResource::make($fromRole) : null,
                        'phone' => $fromUser?->phone,
                        'fish' => $fromUser
                            ? trim("{$fromUser->surname} {$fromUser->name} {$fromUser->middle_name}")
                            : null,
                    ],
                    'responsible_user' => [
                        'role' => $responsibleRole
                            ? RoleResource::make($responsibleRole)
                            : null,
                        'phone' => $responsibleUser?->phone,
                        'fish' => $responsibleUser
                            ? trim("{$responsibleUser->surname} {$responsibleUser->name} {$responsibleUser->middle_name}")
                            : null,
                    ],
                    'fine' => $regulation->fine ? RegulationFineResource::make($regulation->fine) : null,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);

        } catch (\Illuminate\Validation\ValidationException $exception) {
            throw $exception;

        } catch (\Throwable $exception) {

            return response()->json([
                'success' => false,
                'error' => 'Xatolik aniqlandi',
                'error_message' => $exception->getMessage(),
            ], 500);
        }
    }
}


