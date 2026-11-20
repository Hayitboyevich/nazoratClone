<?php

namespace App\Http\Controllers\Api;

use App\DTO\ObjectDto;
use App\Enums\LogType;
use App\Enums\ObjectCheckEnum;
use App\Enums\ObjectStatusEnum;
use App\Enums\RegulationStatusEnum;
use App\Enums\UserRoleEnum;
use App\Http\Requests\ArticleChangeStatusRequest;
use App\Http\Requests\ArticleLocationChangeRequest;
use App\Http\Requests\ObjectCreateRequest;
use App\Http\Requests\ObjectLabratoryRequest;
use App\Http\Requests\ObjectManualRequest;
use App\Http\Requests\ObjectPriceRequest;
use App\Http\Requests\ObjectRequest;
use App\Http\Requests\ObjectUserRequest;
use App\Http\Resources\ArticleListResource;
use App\Http\Resources\ArticleLocationListResource;
use App\Http\Resources\ArticleResource;
use App\Http\Resources\FundingSourceResource;
use App\Http\Resources\UserResource;
use App\Models\Article;
use App\Models\ArticleHistory;
use App\Models\ArticlePaymentLog;
use App\Models\ArticleUser;
use App\Models\Block;
use App\Models\District;
use App\Models\DocumentSignature;
use App\Models\DxaResponse;
use App\Models\Region;
use App\Models\User;
use App\Services\ArticleService;
use App\Services\CameraService;
use App\Services\DxaResponseService;
use App\Services\EimzoService;
use App\Services\HistoryService;
use App\Services\ShaffofIdService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Support\Js;
use Psy\Util\Json;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ObjectController extends BaseController
{


    public function __construct(
        protected ArticleService $service,
        protected EimzoService $imzoService,
        protected DxaResponseService $dxaResponseService,
        protected CameraService $cameraService,
        protected ShaffofIdService $shaffofIdService,
    )
    {
        parent::__construct();
    }

    public function index(): JsonResponse
    {
        try {
            $query = $this->service->getObjects($this->user, $this->roleId);
            $filters = request()->only(['status', 'sphere_id', 'manual', 'null_sphere', 'inn', 'start_date', 'end_date', 'customer_name', 'inspector_id', 'funding_source', 'object_type', 'task_id', 'region_id', 'district_id', 'user_search', 'lab_id']);

            $objects = $this->service->searchObjects($query, $filters)
                ->orderBy('created_at', request('sort_by_date', 'DESC'))
                ->paginate(request('per_page', 10));

            return $this->sendSuccess(ArticleListResource::collection($objects), 'Objects retrieved successfully.', pagination($objects));
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage());
        }
    }

    public function objectStream($id): JsonResponse
    {
        $camera = $this->cameraService->getByShaffofId($id);

        if (!$camera) {
            return $this->sendError('Kamera topilmadi');
        }

        return $this->sendSuccess($camera, 'Camera retrieved successfully.');
    }

    public function objectCamera($id)
    {
        $camera = $this->cameraService->getBodyCamera($id);

        if (!$camera) {
            return $this->sendError('Kamera topilmadi');
        }
        $url = 'https://nazorat.shq.uz/808gps/open/player/video.html?' . http_build_query([
                'devIdno' => $camera['device_no'],
                'jsession' => $camera['jsession'],
            ]);

        $data = [
            ...$camera,
            'url' => $url,
        ];

        return $this->sendSuccess($data, 'Camera retrieved successfully.');
    }


    public function deadlineLog()
    {
        try {
            $paginator = $this->service->deadlineLog();

            return $this->sendSuccess(
                $paginator->items(),
                'Objects retrieved successfully.',
                pagination($paginator)
            );
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage());
        }
    }


    public function locationList()
    {
        $objects = $this->service->getObjects($this->user, $this->roleId)->get();
        return $this->sendSuccess(ArticleLocationListResource::collection($objects), 'Object Location');
    }

    public function objectLocationList()
    {

        $filters = request()->only(['sphere_id', 'status', 'district_soato', 'region_id', 'district_id', 'funding_source', 'difficulty']);
        $objects = $this->service->objectList($this->user, $this->roleId, $filters)->get();

        return $this->sendSuccess($objects, 'Object Location');
    }

    public function objectCountList(Request $request)
    {
        $regionId = $request->get('region');
        $region = Region::query()->where('soato', $regionId)->first();
        $group = $regionId ? 'district_id' : 'region_id';
        $regions = $region
            ? District::query()->where('region_id', $region->id)->get(['id', 'name_uz', 'soato'])
            : Region::all(['id', 'name_uz', 'soato']);

        $articleCounts = $this->getGroupedCounts(Article::query(), $group . ', object_status_id', [$group, 'object_status_id'])->groupBy($group);

        $data = $regions->map(function ($region) use (
            $articleCounts
        ) {
            $regionId = $region->id;
            $regionArticles = $articleCounts->get($regionId, collect());

            return [
                'id' => $region->id,
                'name' => $region->name_uz,
                'soato' => $region->soato,
                'object_count' => $regionArticles->sum('count'),
                'suspended' => $regionArticles->where('object_status_id', ObjectStatusEnum::SUSPENDED)->sum('count'),
                'submitted' => $regionArticles->where('object_status_id', ObjectStatusEnum::SUBMITTED)->sum('count'),
                'frozen' => $regionArticles->where('object_status_id', ObjectStatusEnum::FROZEN)->sum('count'),
                'progress' => $regionArticles->where('object_status_id', ObjectStatusEnum::PROGRESS)->sum('count'),
                'canceled' => $regionArticles->where('object_status_id', ObjectStatusEnum::CANCELED)->sum('count'),
            ];
        });

        return $this->sendSuccess($data->values(), 'Object Count List');
    }

    private function getGroupedCounts($query, $selectRaw, $groupBy)
    {
        return $query
            ->selectRaw("$selectRaw, COUNT(*) as count")
            ->groupBy(...$groupBy)
            ->get();
    }


    public function getHistory($id): JsonResponse
    {
        try {
            return $this->sendSuccess($this->service->getObjectHistory($id), 'Object History');
        } catch (\Exception $exception) {
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function updateSphere()
    {
        try {
            $object = Article::query()->where('id', request()->get('id'))->first();

            if (!$object) {
                return $this->sendError("Bunday obyekt mavjud emas!", "message");

            } else {
                $object->update(
                    [
                        'sphere_id' => request()->get('sphere_id')
                    ]
                );
                $this->service->storeElasticSearch($object);
                return $this->sendSuccess("Ma'lumot yangilandi!", 'Success');
            }
        } catch (\Exception $exception) {
            return $this->sendError("Ichki Xatolik!", "message");
        }
    }

    public function objectChangeFunding(): JsonResponse
    {
        try {
            $data = [
                'id' => request('id'),
                'funding_source' => request('funding_source'),
                'gnk_id' => request('gnk_id') ?? null,
                'docs' => request('docs') ?? null,
                'comment' => request('comment') ?? null,
                'guid' => request('guid') ?? null,
                'uuid' => request('uuid') ?? null,
            ];

            $object = $this->service->changeFundingSource($this->user, $this->roleId, $data);

            return $this->sendSuccess(null, 'Ma\'lumot yangilandi!');

        }catch (\Exception $exception){
            return $this->sendError('Xatolik aniqlandi!', $exception->getMessage());
        }
    }

    public function getObjectImages($id): JsonResponse
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

            return $this->sendSuccess(!empty($imagesByDate) ? $imagesByDate : null, 'Images retrieved successfully.');
        } catch (\Exception $exception) {
            return $this->sendError('Xatolik yuz berdi!', $exception->getMessage());
        }
    }


    public function rotation(): JsonResponse
    {
        try {
            $this->service->rotateUsers($this->user, $this->roleId, request('user_id'), request('rotation_user_id'));

            return $this->sendSuccess([], 'Rotation completed successfully.');

        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function objectChangePrice(ObjectPriceRequest $request): JsonResponse
    {
        try {
            $this->service->changePrice($request, $this->user, $this->roleId);
            return $this->sendSuccess([], 'Object price changed successfully.');
        } catch (\Exception $exception) {
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function objectLaboratoryPrice(ObjectLabratoryRequest $request): JsonResponse
    {
        try {
            $this->service->changeLaboratoryPrice($request, $this->user, $this->roleId);
            return $this->sendSuccess([], 'Object price changed successfully.');
        } catch (\Exception $exception) {
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function deleteObjectPayment(): JsonResponse
    {
        try {
            $id = request('id');
            $comment = request('comment');
            $this->service->deletePaymentLog($id, $comment, $this->user, $this->roleId);
            return $this->sendSuccess([], 'Object payment deleted successfully.');
        } catch (\Exception $exception) {
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function deadlineChange(): JsonResponse
    {
        try {
            if ($this->roleId != UserRoleEnum::ADMIN->value)   return $this->sendError('Sizda bunday huquq mavjud emas', code: 403);

            $object = $this->service->deadlineChange($this->user, $this->roleId);

            return $this->sendSuccess(ArticleResource::make($object), 'Object deadline changed successfully.');
        } catch (\Exception $exception) {
            return $this->sendError('Xatolik yuz berdi!', $exception->getMessage());
        }
    }

    public function objectByParams(): JsonResponse
    {
        try {
            if (!request('task_id') && !request('gnk_id') && !request('expertize_number')) throw new NotFoundHttpException('Object not found');

            $params = request()->only(['task_id', 'gnk_id', 'expertize_number']);
            $object = $this->service->findArticleByParams($params);

            return $this->sendSuccess(ArticleResource::make($object), 'Object retrieved successfully.');

        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function userObjects(): JsonResponse
    {
        try {
            $user = $this->service->getUserByInnAndRole(request('inn'), UserRoleEnum::QURILISH->value);

            if (!$user) throw new NotFoundHttpException('User not found');

            $objects = $user->objects()->paginate(request('per_page', 10));

            return $this->sendSuccess(ArticleListResource::collection($objects), 'Objects retrieved successfully.', pagination($objects));

        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function accountObjects(): JsonResponse
    {
        $filters = request()->only(['status', 'customer_name', 'funding_source', 'object_type', 'inn', 'task_id', 'region_id', 'district_id', 'user_search']);

        $query = $this->service->getObjects($this->user, $this->roleId);
        $query = $this->service->getAccountObjectsQuery($query, request('status'));
        $query = $this->service->searchObjects($query, $filters);

        $objects = $query->orderBy('created_at', request('sort_by_date', 'DESC'))
            ->paginate(\request('per_page', 10));

        return $this->sendSuccess(ArticleListResource::collection($objects), 'Objects retrieved successfully.', pagination($objects));
    }

    public function accountReport(): JsonResponse
    {
        try {
            $articles = Article::with(['region', 'district', 'paymentLogs', 'objectStatus'])
                ->whereIn('object_status_id', [ObjectStatusEnum::PROGRESS, ObjectStatusEnum::FROZEN, ObjectStatusEnum::SUSPENDED])
                ->get()
                ->map(function ($article) {
                    return [
                        'region' => optional($article->region)->name_uz,
                        'district' => optional($article->district)->name_uz,
                        'status' => $article->objectStatus->name,
                        'task_id' => $article->task_id,
                        'name' => $article->name,
                        'created_at' => $article->created_at,
                        'cost' => $article->price_supervision_service,
                        'paid' => $article->paymentLogs->sum(fn($p) => (float)data_get($p->content, 'additionalInfo->amount', 0))
                    ];
                });
            return $this->sendSuccess($articles, 'Objects retrieved successfully.');
        } catch (\Exception $exception) {
            return $this->sendError('Xatolik yuz berdi', $exception->getMessage());
        }
    }

    public function totalPayment(): JsonResponse
    {
        try {
            $result = $this->service->calculateTotalPayment(request('region_id'));
            return $this->sendSuccess($result, 'All Payments');

        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function paymentStatistics(): JsonResponse
    {
        try {
            $statistics = $this->service->calculatePaymentStatistics(request('region_id'));
            return $this->sendSuccess($statistics, 'Payment Statistics');
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function getObject($id): JsonResponse
    {
        try {
            $object = $this->service->getObjectById($this->user, $this->roleId, $id);

            if ($object) $this->service->setObjectUsers($this->user, $this->roleId, $id);

            return $this->sendSuccess(ArticleResource::make($object), 'Object retrieved successfully.');
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }


    public function getObjectById($id): JsonResponse
    {
        try {
            $object = $this->service->findById($id);
            if (!$object) return $this->sendError('Obyekt topilmadi');
            return $this->sendSuccess(ArticleResource::make($object), 'Object retrieved successfully.');
        }catch (\Exception $exception){
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function getObjectByTaskId($taskId): JsonResponse
    {
        try {
            $object = $this->service->findByTaskId($taskId);
            return $this->sendSuccess(ArticleResource::make($object), 'Object retrieved successfully.');
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function fundingSource(): JsonResponse
    {
        return $this->sendSuccess(FundingSourceResource::collection($this->service->getAllFundingSources()), 'Funding sources');
    }

    public function create(ObjectRequest $request): JsonResponse
    {
        try {
            $dto = new ObjectDto();
            $dto->setResponseId($request->response_id);
            $dto->setUuid($request->uuid);
            $dto->setGuid($request->guid);
            $dto->setTableName($request->table);


            $this->service->setObjectDto($dto);

            $object = $this->service->createObject($this->user, $this->roleId);
            $dxa = DxaResponse::query()->find($request->response_id);
            $this->dxaResponseService->saveHistory($dxa, $this->user, $this->roleId, LogType::DXA_ACCEPTED,  'Qabul qilindi');

            return $this->sendSuccess(ArticleResource::make($object), 'Object created');
        } catch (\Exception $exception) {
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function objectCount(): JsonResponse
    {
        try {
            $filters = request()->only(['sphere_id', 'region_id', 'district_id', 'funding_source', 'difficulty']);

            $data = $this->service->getObjectCount($this->user, $this->roleId, $filters);

            return $this->sendSuccess($data, 'Object count retrieved successfully.');
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function payment(): JsonResponse
    {
        try {
            $this->service->getObjectById($this->user, $this->roleId, request('object_id'));
            $this->service->createPayment($this->user, $this->roleId, request('object_id'));

            return $this->sendSuccess([], 'Article retrieved successfully.');

        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function paymentLaboratory(): JsonResponse
    {
        try {
            $this->service->createPaymentLaboratory($this->user, $this->roleId, request('object_id'));
            $article = $this->service->getObjectById($this->user, $this->roleId, request('object_id'));
//            $this->service->storeElasticSearch($article);
            return $this->sendSuccess([], 'Article retrieved successfully.');

        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function checkObject()
    {
        try {
            $object = Article::findOrFail(request()->get('id'));

            $missingRoles = $this->checkUsers($object);

            if (!empty($missingRoles)) {
                return $this->sendError('Obyekt qatnashchilari yetarli emas ' . implode(', ', $missingRoles));
            }

            return $this->sendSuccess(true, 'Article retrieved successfully.');

        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function manualCreate(): JsonResponse
    {
        try {
            $this->service->createObjectManual(request('task_id'));
            $article = $this->service->findByTaskId(request('task_id'));
            $this->service->storeElasticSearch($article);

            return $this->sendSuccess([], 'success');
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function objectCreate(ObjectCreateRequest $request): JsonResponse
    {
        try {
            $article = $this->service->createObjectRegister($request, $this->user, $this->roleId);
            $this->service->storeElasticSearch($article);
            return $this->sendSuccess(ArticleResource::make($article), 'success');
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function objectElastic()
    {
        $article = $this->service->findByTaskId(request('task_id'));
        if ($article) {
            $this->service->storeElasticSearch($article);
            return $this->sendSuccess([], 'success');
        }
        return $this->sendError('obyekt topilmadi');
    }

    public function manualUpdate(): JsonResponse
    {
        try {
            $this->service->updateObjectManual(request('task_id'));
            $article = $this->service->findByTaskId(request('task_id'));
            $this->service->storeElasticSearch($article);
            return $this->sendSuccess([], 'success');
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    private function checkUsers($object): array
    {
        $users = $object->users;
        $missingRoles = [];
        foreach (ObjectCheckEnum::cases() as $role) {
            $method = $role->value;
            $hasRole = $users->contains(function ($user) use ($method) {
                return $user->{$method}();
            });
            if (!$hasRole) {
                $missingRoles[] = $role->name;
            }
        }
        return $missingRoles;
    }

    public function objectCreateUser(ObjectUserRequest $request): JsonResponse
    {
        try {
            $user = $this->service->createUser($request);
            $article = $this->service->getObjectById($this->user, $this->roleId, $request['object_id']);
            $this->service->storeElasticSearch($article);

            return $this->sendSuccess(UserResource::make($user), 'Success');
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    private function checkBlocks($object): array
    {
        $inactiveBlocks = [];
        foreach ($object->blocks as $block) {
            if (!$block->status) {
                $inactiveBlocks[] = $block->name;
            }
        }
        return $inactiveBlocks;
    }


    public function changeObjectStatus(ArticleChangeStatusRequest $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $meta = null;
            if ($this->roleId != UserRoleEnum::INSPECTOR->value){
                $document = DocumentSignature::query()
                    ->where('guid', request('guid'))
                    ->where('uuid', request('uuid'))
                    ->where('user_id', $this->user->id)
                    ->where('role_id', $this->roleId)
                    ->first();

                if (!$document) {
                    throw new \Exception('Tasdiqlash uchun hujjat topilmadi.');
                }

                if ($document->status !== 'signed') {
                    $signed = $this->shaffofIdService->checkDocumentSign(
                        $this->user,
                        $this->roleId,
                        $request->guid,
                        $request->uuid,
                    );

                    if (!$signed) {
                        throw new \Exception('Hujjat imzolanmagan yoki tasdiqlashda xatolik yuz berdi.');
                    }
                }

                $meta = ['guid' => $document->guid, 'uuid' => $document->uuid];
            }

            $service = new HistoryService('article_histories');

            $object = $this->service->getObjectById($this->user, $this->roleId, $request->object_id);

            if (
                $request->status == ObjectStatusEnum::SUBMITTED->value
                && $object->funding_source_id == 1
                && $object->govClaims()->whereNotIn('status', [9, 15])->exists()
            ) {
                return $this->sendError('Obyektda davlat dasturlarini foydalanishga topshirish uchun arizasi mavjud');
            }

            $previousStatus = $object->object_status_id;

            if ($previousStatus == ObjectStatusEnum::SUBMITTED) {

                if (empty($request->blocks)) return $this->sendError('Blok tanlanishi kerak');

                Block::query()->whereIn('id', $request->blocks)->update(['accepted' => false]);
            }


            $object->update([
                'object_status_id' => $request->status,
                'send_operator' => null,
                'send_tax' => null,
                'send_dshk' => null,
            ]);

            if ($object->object_status_id == ObjectStatusEnum::CANCELED) {
                $object->regulations()
                    ->whereNotIn('regulation_status_id', [
                        RegulationStatusEnum::LATE_EXECUTION,
                        RegulationStatusEnum::ELIMINATED,
                    ])
                    ->update([
                        'regulation_status_id' => RegulationStatusEnum::CANCELLED
                    ]);

            }

            if ($object->object_status_id === ObjectStatusEnum::SUBMITTED) {
                $object->update([
                    'closed_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'send_operator' => null,
                    'send_tax' => null,
                    'send_dshk' => null,
                ]);

                $object->blocks()
                    ->where('accepted', false)
                    ->update(['accepted' => true]);

                $object->regulations()
                    ->whereNotIn('regulation_status_id', [RegulationStatusEnum::LATE_EXECUTION, RegulationStatusEnum::ELIMINATED])
                    ->update(['regulation_status_id' => RegulationStatusEnum::ELIMINATED]);
            }



            $tableId = $service->createHistory(
                guId: $request->object_id,
                status: $request->status,
                type: LogType::ARTICLE_HISTORY,
                date: null,
                comment: $request->comment ?? '',
                additionalInfo: [
                    'user_id' => $this->user->id,
                    'role_id' => $this->roleId,
                    'previous_status' => $previousStatus,
                    'new_status' => $request->status,
                    'meta' => $meta
                ]
            );

            $history = ArticleHistory::query()->findOrFail($tableId);

            if ($request->hasFile('file')) {
                $path = $request->file('file')->store('object/files', 'public');
                $history->documents()->create(['url' => $path]);
            }

            $this->service->sendApartment($object);
            $this->service->storeElasticSearch($object);

            DB::commit();
            return $this->sendSuccess(null, 'Object status updated');
        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function changeObjectLocation(ArticleLocationChangeRequest $request): JsonResponse
    {
        try {
            $document = DocumentSignature::query()
                ->where('guid', request('guid'))
                ->where('uuid', request('uuid'))
                ->where('user_id', $this->user->id)
                ->where('role_id', $this->roleId)
                ->first();

            if (!$document) {
                throw new \Exception('Tasdiqlash uchun hujjat topilmadi.');
            }

            if ($document->status !== 'signed') {
                $signed = $this->shaffofIdService->checkDocumentSign(
                    $this->user,
                    $this->roleId,
                    $request->guid,
                    $request->uuid,
                );

                if (!$signed) {
                    throw new \Exception('Hujjat imzolanmagan yoki tasdiqlashda xatolik yuz berdi.');
                }
            }
            $meta = ['guid' => $document->guid, 'uuid' => $document->uuid];


            $service = new HistoryService('article_histories');
            $object = Article::query()->findOrFail($request->object_id);

            $previousLocation = [
                'lat' => $object->lat,
                'long' => $object->long
            ];
            $newLocation = [
                'lat' => $request->lat,
                'long' => $request->long,
            ];

            $object->update([
                'lat' => $request->lat,
                'long' => $request->long,
                'send_operator' => null,
                'send_tax' => null,
                'send_dshk' => null,
            ]);

            $tableId = $service->createHistory(
                guId: $request->object_id,
                status: $object->object_status_id->value,
                type: LogType::CHANGE_OBJECT_LOCATION,
                date: null,
                comment: $request->comment ?? '',
                additionalInfo: [
                    'user_id' => $this->user->id,
                    'role_id' => $this->roleId,
                    'previous_location' => $previousLocation,
                    'newLocation' => $newLocation,
                    'meta' => $meta
                ]
            );

            $this->service->storeElasticSearch($object);

            return $this->sendSuccess(null, 'Object location updated');
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function inspectorAttachmentObject(): JsonResponse
    {
        try {
            $object = Article::query()->where('task_id', request('task_id'))->first();

            $user = User::query()->findOrFail(request('user_id'));
            if (!$object) throw new NotFoundHttpException('Object not found');

            $articleUser = new ArticleUser();
            $articleUser->article_id = $object->id;
            $articleUser->user_id = request('user_id');
            $articleUser->role_id = UserRoleEnum::INSPECTOR->value;
            $articleUser->save();

            $this->service->storeElasticSearch($object);

            return $this->sendSuccess(UserResource::make($user), 'data saved');

        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function inspectorChangeObject(): JsonResponse
    {
        try {
            $this->service->attachInspectorObject($this->user, $this->roleId, request('task_ids'), request('inspector_id'));

            return $this->sendSuccess([], 'success');
        } catch (\Exception $exception) {
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }
}
