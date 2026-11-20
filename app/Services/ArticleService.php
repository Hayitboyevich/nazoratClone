<?php

namespace App\Services;

use App\DTO\ObjectDto;
use App\Enums\ConstructionWork;
use App\Enums\DifficultyCategoryEnum;
use App\Enums\DxaResponseStatusEnum;
use App\Enums\LogType;
use App\Enums\ObjectStatusEnum;
use App\Enums\UserRoleEnum;
use App\Enums\UserStatusEnum;
use App\Exceptions\NotFoundException;
use App\Http\Requests\ObjectLabratoryRequest;
use App\Http\Requests\ObjectPriceRequest;
use App\Http\Resources\ArticleOpenDataResource;
use App\Models\Article;
use App\Models\ArticleHistory;
use App\Models\ArticleUser;
use App\Models\Block;
use App\Models\DocumentSignature;
use App\Models\DxaResponse;
use App\Models\DxaResponseSupervisor;
use App\Models\FundingSource;
use App\Models\ObjectStatus;
use App\Models\Regulation;
use App\Models\Rekvizit;
use App\Models\Role;
use App\Models\User;
use App\Models\UserEmployee;
use App\Models\UserRole;
use App\Repositories\Interfaces\ArticleRepositoryInterface;
use App\Repositories\Interfaces\BlockRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ArticleService
{
    protected ObjectDto $objectDto;

    private HistoryService $objectHistory;

    public function __construct(
        protected ArticleRepositoryInterface $articleRepository,
        protected UserRepositoryInterface    $userRepository,
        protected BlockRepositoryInterface   $blockRepository,
        protected DxaResponse                $dxaResponse,
        protected ImageService               $imageService,
        protected DocumentService            $documentService,
        protected ElasticSearchService       $esSearchService,
        protected EimzoService                $imzoService,
        protected ShaffofIdService            $shaffofIdService,

    )
    {
        $this->objectHistory = new HistoryService('article_histories');
    }

    public function setObjectDto(ObjectDto $objectDto): void
    {
        $this->objectDto = $objectDto;
    }

    public function getObjectById($user, $roleId, $id)
    {
        return $this->getObjects($user, $roleId)->where('articles.id', $id)->first();
    }

    public function findByTaskId($taskId)
    {
        return $this->articleRepository->findByTaskId($taskId);
    }

    public function setObjectUsers($user, $roleId, $id)
    {
        return $this->articleRepository->setObjectUsers($user, $roleId, $id);
    }


    public function getObjects($user, $roleId)
    {
        switch ($roleId) {
            case UserRoleEnum::INSPECTOR->value:
            case UserRoleEnum::ICHKI->value:
            case UserRoleEnum::MUALLIF->value:
            case UserRoleEnum::TEXNIK->value:
            case UserRoleEnum::LOYIHA->value:
            case UserRoleEnum::BUYURTMACHI->value:
            case UserRoleEnum::REG_LAB->value:
            case UserRoleEnum::QURILISH->value:
            case UserRoleEnum::LABORANT->value:
                return $this->getArticlesByUserRole($user, $roleId);
            case UserRoleEnum::REGISTRATOR->value:
            case UserRoleEnum::OPERATOR->value:
            case UserRoleEnum::INSPEKSIYA->value:
            case UserRoleEnum::HUDUDIY_KUZATUVCHI->value:
            case UserRoleEnum::QURILISH_MONTAJ->value:
            case UserRoleEnum::BUXGALTER->value:
            case UserRoleEnum::REGKADR->value:
            case UserRoleEnum::YURIST->value:
            case UserRoleEnum::KOMPLAYNS->value:
                return $this->getArticlesByRegion($user->region_id);
            case UserRoleEnum::RESPUBLIKA_KUZATUVCHI->value:
            case UserRoleEnum::RESPUBLIKA_KOMPLAYNS->value:
            case UserRoleEnum::RESPUBLIKA_YURIST->value:
            case UserRoleEnum::RESPUBLIKA_BUXGALTER->value:
            case UserRoleEnum::ADMIN->value:
            case UserRoleEnum::RES_LAB->value:
                return Article::query()
                    ->with(['difficulty', 'objectStatus', 'labContract.blocks.contractInfo', 'labContract.blocks.contractInfo.workType', 'labContract.blocks.contractInfo.question']);
            case UserRoleEnum::LOYIHA_XODIM->value:
                return $this->getByCompany($user);
            default:
                return Article::query()->whereRaw('1 = 0');
        }
    }

    public function objectList($user, $roleId, $filters)
    {
        switch ($roleId) {
            case UserRoleEnum::INSPECTOR->value:
            case UserRoleEnum::ICHKI->value:
            case UserRoleEnum::MUALLIF->value:
            case UserRoleEnum::TEXNIK->value:
            case UserRoleEnum::LOYIHA->value:
            case UserRoleEnum::BUYURTMACHI->value:
            case UserRoleEnum::QURILISH->value:
            case UserRoleEnum::LABORANT->value:
                return DB::table('article_users')
                    ->join('articles', 'articles.id', '=', 'article_users.article_id')
                    ->leftJoin('difficulty_categories', 'difficulty_categories.id', '=', 'articles.difficulty_category_id')
                    ->leftJoin('object_statuses', 'object_statuses.id', '=', 'articles.object_status_id')
                    ->leftJoin('spheres', 'spheres.id', '=', 'articles.sphere_id')
                    ->leftJoin('districts', 'districts.id', '=', 'articles.district_id')
                    ->where('article_users.user_id', $user->id)
                    ->where('article_users.role_id', $roleId)
                    ->when(!empty($filters['name']), function ($query) use ($filters) {
                        $query->where(function ($q) use ($filters) {
                            $q->where('articles.name', "ILIKE", "%{$filters['name']}%")
                                ->orWhere('articles.task_id', "ILIKE", "%{$filters['name']}%");
                        });
                    })
                    ->when(!empty($filters['sphere_id']), function ($query) use ($filters) {
                        $query->where('articles.sphere_id', $filters['sphere_id']);
                    })
                    ->when(!empty($filters['status']), function ($query) use ($filters) {
                        $query->whereIn('articles.object_status_id', $filters['status']);
                    })
                    ->when(!empty($filters['region_id']), function ($query) use ($filters) {
                        $query->where('articles.region_id', $filters['region_id']);
                    })
                    ->when(!empty($filters['district_id']), function ($query) use ($filters) {
                        $query->where('articles.district_id', $filters['district_id']);
                    })
                    ->when(!empty($filters['district_soato']), function ($query) use ($filters) {
                        $query->where('districts.soato', $filters['district_soato']);
                    })
                    ->when(!empty($filters['difficulty']), function ($query) use ($filters) {
                        $query->where('articles.difficulty_category_id', $filters['difficulty']);
                    })
                    ->select([
                        'articles.id',
                        'articles.name',
                        'articles.task_id',
                        'articles.location_building',
                        'articles.organization_name',
                        'articles.closed_at',
                        'articles.deadline',
                        'articles.lat',
                        'articles.long',
                        'spheres.name_uz as sphere_name',
                        'articles.number_protocol',
                        'articles.reestr_number',
                        'difficulty_categories.difficulty as difficulty',
                        'object_statuses.name as status_name',
                        'articles.object_status_id as statusId',
                    ]);

            case UserRoleEnum::REGISTRATOR->value:
            case UserRoleEnum::OPERATOR->value:
            case UserRoleEnum::INSPEKSIYA->value:
            case UserRoleEnum::HUDUDIY_KUZATUVCHI->value:
            case UserRoleEnum::QURILISH_MONTAJ->value:
            case UserRoleEnum::BUXGALTER->value:
            case UserRoleEnum::REGKADR->value:
            case UserRoleEnum::YURIST->value:
                return DB::table('articles')
                    ->leftJoin('difficulty_categories', 'difficulty_categories.id', '=', 'articles.difficulty_category_id')
                    ->leftJoin('object_statuses', 'object_statuses.id', '=', 'articles.object_status_id')
                    ->leftJoin('spheres', 'spheres.id', '=', 'articles.sphere_id')
                    ->leftJoin('districts', 'districts.id', '=', 'articles.district_id')
                    ->where('articles.region_id', $user->region_id)
                    ->when(!empty($filters['name']), function ($query) use ($filters) {
                        $query->where(function ($q) use ($filters) {
                            $q->where('articles.name', "ILIKE", "%{$filters['name']}%")
                                ->orWhere('articles.task_id', "ILIKE", "%{$filters['name']}%");
                        });
                    })
                    ->when(!empty($filters['district_soato']), function ($query) use ($filters) {
                        $query->where('districts.soato', $filters['district_soato']);
                    })
                    ->when(!empty($filters['status']), function ($query) use ($filters) {
                        $query->whereIn('articles.object_status_id', $filters['status']);
                    })
                    ->when(!empty($filters['sphere_id']), function ($query) use ($filters) {
                        $query->where('articles.sphere_id', $filters['sphere_id']);
                    })
                    ->when(!empty($filters['region_id']), function ($query) use ($filters) {
                        $query->where('articles.region_id', $filters['region_id']);
                    })
                    ->when(!empty($filters['district_id']), function ($query) use ($filters) {
                        $query->where('articles.district_id', $filters['district_id']);
                    })
                    ->when(!empty($filters['difficulty']), function ($query) use ($filters) {
                        $query->where('articles.difficulty_category_id', $filters['difficulty']);
                    })
                    ->select([
                        'articles.id',
                        'articles.name',
                        'articles.task_id',
                        'articles.location_building',
                        'articles.organization_name',
                        'articles.closed_at',
                        'articles.deadline',
                        'articles.lat',
                        'articles.long',
                        'spheres.name_uz as sphere_name',
                        'articles.number_protocol',
                        'articles.reestr_number',
                        'difficulty_categories.difficulty as difficulty',
                        'object_statuses.name as status_name',
                        'articles.object_status_id as statusId',
                    ]);

            case UserRoleEnum::REG_LAB->value:
                return DB::table('articles')
                    ->leftJoin('difficulty_categories', 'difficulty_categories.id', '=', 'articles.difficulty_category_id')
                    ->leftJoin('object_statuses', 'object_statuses.id', '=', 'articles.object_status_id')
                    ->leftJoin('districts', 'districts.id', '=', 'articles.district_id')
                    ->where('articles.region_id', $user->region_id)
                    ->whereNotNull('articles.lab_contract_id')
                    ->leftJoin('spheres', 'spheres.id', '=', 'articles.sphere_id')
                    ->when(!empty($filters['name']), function ($query) use ($filters) {
                        $query->where(function ($q) use ($filters) {
                            $q->where('articles.name', "ILIKE", "%{$filters['name']}%")
                                ->orWhere('articles.task_id', "ILIKE", "%{$filters['name']}%");
                        });
                    })
                    ->when(!empty($filters['status']), function ($query) use ($filters) {
                        $query->whereIn('articles.object_status_id', $filters['status']);
                    })
                    ->when(!empty($filters['district_soato']), function ($query) use ($filters) {
                        $query->where('districts.soato', $filters['district_soato']);
                    })
                    ->when(!empty($filters['sphere_id']), function ($query) use ($filters) {
                        $query->where('articles.sphere_id', $filters['sphere_id']);
                    })
                    ->when(!empty($filters['region_id']), function ($query) use ($filters) {
                        $query->where('articles.region_id', $filters['region_id']);
                    })
                    ->when(!empty($filters['district_id']), function ($query) use ($filters) {
                        $query->where('articles.district_id', $filters['district_id']);
                    })
                    ->when(!empty($filters['difficulty']), function ($query) use ($filters) {
                        $query->where('articles.difficulty_category_id', $filters['difficulty']);
                    })
                    ->select([
                        'articles.id',
                        'articles.name',
                        'articles.task_id',
                        'articles.location_building',
                        'articles.organization_name',
                        'articles.closed_at',
                        'articles.deadline',
                        'articles.lat',
                        'articles.long',
                        'spheres.name_uz as sphere_name',
                        'articles.number_protocol',
                        'articles.reestr_number',
                        'difficulty_categories.difficulty as difficulty',
                        'object_statuses.name as status_name',
                        'articles.object_status_id as statusId',
                    ]);

            case UserRoleEnum::RESPUBLIKA_KUZATUVCHI->value:
            case UserRoleEnum::RESPUBLIKA_BUXGALTER->value:
            case UserRoleEnum::ADMIN->value:

                return DB::table('articles')
                    ->leftJoin('districts', 'districts.id', '=', 'articles.district_id')
                    ->leftJoin('difficulty_categories', 'difficulty_categories.id', '=', 'articles.difficulty_category_id')
                    ->leftJoin('object_statuses', 'object_statuses.id', '=', 'articles.object_status_id')
                    ->leftJoin('spheres', 'spheres.id', '=', 'articles.sphere_id')
                    ->when(!empty($filters['name']), function ($query) use ($filters) {
                        $query->where(function ($q) use ($filters) {
                            $q->where('articles.name', "ILIKE", "%{$filters['name']}%")
                                ->orWhere('articles.task_id', "ILIKE", "%{$filters['name']}%");
                        });
                    })
                    ->when(!empty($filters['status']), function ($query) use ($filters) {
                        $query->whereIn('articles.object_status_id', $filters['status']);
                    })
                    ->when(!empty($filters['district_soato']), function ($query) use ($filters) {
                        $query->where('districts.soato', $filters['district_soato']);
                    })
                    ->when(!empty($filters['sphere_id']), function ($query) use ($filters) {
                        $query->where('articles.sphere_id', $filters['sphere_id']);
                    })
                    ->when(!empty($filters['region_id']), function ($query) use ($filters) {
                        $query->where('articles.region_id', $filters['region_id']);
                    })
                    ->when(!empty($filters['district_id']), function ($query) use ($filters) {
                        $query->where('articles.district_id', $filters['district_id']);
                    })
                    ->when(!empty($filters['difficulty']), function ($query) use ($filters) {
                        $query->where('articles.difficulty_category_id', $filters['difficulty']);
                    })
                    ->select([
                        'articles.id',
                        'articles.name',
                        'articles.task_id',
                        'articles.location_building',
                        'articles.organization_name',
                        'articles.closed_at',
                        'articles.deadline',
                        'articles.lat',
                        'articles.long',
                        'spheres.name_uz as sphere_name',
                        'articles.number_protocol',
                        'articles.reestr_number',
                        'difficulty_categories.difficulty as difficulty',
                        'object_statuses.name as status_name',
                        'articles.object_status_id as statusId',
                    ]);

            default:
                return DB::table('articles')->whereRaw('1 = 0');
        }
    }

    public function getObjectHistory($id)
    {
        $object = $this->articleRepository->findById($id);

        $histories = $object->histories
            ->map(function ($history) {
                return [
                    'id' => $history->id,

                    'user' => !empty($history->content->user)
                        ? User::query()->find(
                            $history->content->user,
                            ['id', 'name', 'surname', 'middle_name']
                        )
                        : null,

                    'role' => !empty($history->content->role)
                        ? Role::query()->find(
                            $history->content->role,
                            ['id', 'name', 'description']
                        )
                        : null,

                    'status' => !empty($history->content->status)
                        ? ObjectStatus::query()->find(
                            $history->content->status,
                            ['id', 'name']
                        )
                        : null,

                    'type' => $history->type,

                    'is_change' => $history->type
                        ? LogType::getLabel($history->type)
                        : null,

                    'file' => $history->documents->map(function ($document) {
                        return [
                            'id' => $document->id,
                            'path' => Storage::disk('public')->url($document->url),
                        ];
                    })->values(),

                    'created_at' => $history->created_at,
                ];
            })
            ->sortByDesc('created_at')
            ->values();

        return $histories;

    }

    public function findById($id)
    {
        return $this->articleRepository->findById($id);
    }

    public function changeFundingSource($user, $roleId, $data)
    {
        return DB::transaction(function () use ($data, $roleId, $user) {

            $document = DocumentSignature::query()
                ->where('guid', $data['guid'])
                ->where('uuid', $data['uuid'])
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
                    $data['guid'],
                    $data['uuid'],
                );

                if (!$signed) {
                    throw new \Exception('Hujjat imzolanmagan yoki tasdiqlashda xatolik yuz berdi.');
                }
            }
            $meta = ['guid' => $document->guid, 'uuid' => $document->uuid];

            $object = $this->articleRepository->findById($data['id']);
            if (!$object) {
                throw new NotFoundHttpException('Obyekt topilmadi');
            }

            $oldFundingSource = $object->funding_source_id;
            $oldGnk = $object->gnk_id;

            if ($data['funding_source'] == 1 && empty($data['gnk_id'])) {
                throw new NotFoundHttpException("Gnk Id to‘ldirilishi shart");
            }

            if ($oldFundingSource == 1) {
                if ($object->gnk_id === null) {
                    $object->gnk_id = $data['gnk_id'];
                    $object->funding_source_id = $data['funding_source'];
                    $object->send_operator = null;
                    $object->sent_to_tender = 0;
                    $object->save();

                    $this->saveFundingSourceHistory($object, $oldFundingSource, $oldGnk, $data, $meta);
                    return $object;
                }
            }

            $object->funding_source_id = $data['funding_source'];
            $object->gnk_id = $data['gnk_id'] ?? null;
            $object->sent_to_tender = 0;
            $object->save();

            $this->saveFundingSourceHistory($object, $oldFundingSource, $oldGnk, $data, $meta);

            return $object;
        });
    }

    private function saveFundingSourceHistory($object, $oldFundingSource, $oldGnk, $data, $addInfo)
    {
        $meta = [
            'old_funding_source_id' => $oldFundingSource,
            'funding_source_id'     => $data['funding_source'],
            'old_gnk_id'            => $oldGnk,
            'gnk_id'                => $data['gnk_id'] ?? null,
            'meta'                 => $addInfo,
        ];

        $historyId = $this->objectHistory->createHistory(
            guId: $object->id,
            status: $object->object_status_id->value,
            type: LogType::CHANGE_FUNDING_SOURCE,
            date: null,
            additionalInfo: $meta
        );

        $history =  ArticleHistory::query()->find($historyId);

        if (!empty($data['docs'])) {
            foreach ($data['docs'] as $doc) {
                $path = $doc->store('document/object-history', 'public');
                $history->documents()->create(['url' => $path]);
            }
        }


    }

    public function getArticlesByUserRole($user, $roleId)
    {
        return $this->articleRepository->getArticlesByUserRole($user, $roleId);
    }

    public function getArticlesByRegion($regionId)
    {
        return $this->articleRepository->getArticlesByRegion($regionId);
    }

    public function getByCompany($user)
    {
        return $this->articleRepository->getArticleByCompany($user);
    }

    public function searchObjects($query, $filters)
    {
        return $this->articleRepository->searchObjects($query, $filters);
    }

    public function rotateUsers($user, $roleId, $firstUserId, $secondUserId)
    {
        $this->articleRepository->rotateUsers($user, $roleId, $firstUserId, $secondUserId);
    }

    public function getAllFundingSources(): object
    {
        return FundingSource::all();
    }

    public function findArticleByParams($params)
    {
        return $this->articleRepository->findArticleByParams($params);
    }

    public function getUserByInnAndRole($inn, $role)
    {
        return $this->articleRepository->getUserByInnAndRole($inn, $role);
    }

    public function createUser($data)
    {
        $object = Article::query()->find($data->object_id);
        $user = new User();
        $user->name = $data->name;
        $user->middle_name = $data->middle_name;
        $user->surname = $data->surname;
        $user->phone = $data->phone;
        $user->pinfl = $data->pinfl;
        $user->organization_name = $data->organization_name;
        $user->region_id = $data->region_id;
        $user->district_id = $data->district_id;
        $user->identification_number = $data->inn;
        $user->login = $data->pinfl;
        $user->password = $data->pinfl;
        $user->user_status_id = $data->user_status_id;
        $user->image = $this->saveUserImage($data);
        $user->save();

        $this->saveUserFiles($user, $data);
        UserRole::query()->create([
            'user_id' => $user->id,
            'role_id' => $data->role_id
        ]);

        $object->users()->attach($user->id, ['role_id' => $data->role_id]);

        return $user;

    }

    private function saveUserImage($data)
    {
        if ($data->hasFile('image')) {
            return $data->file('image')->store('user', 'public');
        }

        return null;
    }

    private function saveUserFiles(User $user, $data)
    {
        if ($data->hasFile('files')) {
            $user->documents()->delete();
            foreach ($data->file('files') as $file) {
                $path = $file->store('user/docs', 'public');
                $user->documents()->create(['url' => $path]);
            }
        }
    }

    public function calculateTotalPayment($regionId)
    {
        $totalPaid = Article::with('paymentLogs')
            ->where('region_id', $regionId)
            ->where('articles.created_at', '>=', '2024-01-01')
            ->get()
            ->reduce(function ($carry, $article) {
                return $carry + $article->paymentLogs->sum(function ($log) {
                        return isset($log->content->additionalInfo->amount)
                            ? (float)$log->content->additionalInfo->amount
                            : 0;
                    });
            });

        $totalAmount = $this->getArticlesByRegion($regionId)->where('created_at', '>=', '2024-01-01')->get()->sum('price_supervision_service');

        return [
            'totalAmount' => $totalAmount,
            'totalPaid' => $totalPaid,
            'notPaid' => $totalAmount - $totalPaid,
        ];
    }


    public function calculatePaymentStatistics($regionId)
    {

        $articles = Article::with('paymentLogs')
            ->where('region_id', $regionId)
            ->get();

        $filteredArticles = $articles->filter(function ($article) {
            return (float)$article->price_supervision_service > 0;
        });

        $results = [
            'all' => $filteredArticles->count(),
            'paid' => 0,
            'partiallyPaid' => 0,
            'notPaid' => 0,
        ];

        foreach ($filteredArticles as $article) {
            $totalPaid = $article->paymentLogs->sum(function ($log) {
                return (float)data_get($log->content, 'additionalInfo.amount', 0);
            });

            $price = (float)$article->price_supervision_service;

            if ($totalPaid >= $price) {
                $results['paid']++;
            } elseif ($totalPaid > 0 && $totalPaid < $price) {
                $results['partiallyPaid']++;
            } else {
                $results['notPaid']++;
            }
        }

        return $results;

    }

    public function changePrice(ObjectPriceRequest $request, $user, $roleId)
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
                $request->guid,
                $request->uuid,
            );

            if (!$signed) {
                throw new \Exception('Hujjat imzolanmagan yoki tasdiqlashda xatolik yuz berdi.');
            }
        }
        $meta = ['guid' => $document->guid, 'uuid' => $document->uuid];

        $object = Article::query()->findOrFail($request->object_id);

        $oldPrice = $object->construction_cost;

        $object->update([
            'construction_cost' => $request->price,
            'price_supervision_service' => price_supervision($request->price)
        ]);

        $addInfo = [
            'user_id' => $user->id, 'role_id' => $roleId, 'price' => $request->price, 'old_price' => $oldPrice, 'meta' => $meta
        ];

        $this->objectHistory->createHistory(
            guId: $object->id,
            status: $object->object_status_id->value,
            type: LogType::ARTICLE_PRICE_HISTORY,
            date: null,
            comment: $request->comment ?? "",
            additionalInfo: $addInfo
        );


    }

    public function changeLaboratoryPrice(ObjectLabratoryRequest $request, $user, $roleId)
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
        $meta = ['guid' => $document->guid, 'uuid' => $document->uuid];

        $object = Article::query()->findOrFail($request->object_id);
        if ($object->laboratory_cost) throw new \Exception('Oldin to\'ldirilgan');


        $object->update([
            'laboratory_cost' => $request->laboratory_cost,
            'laboratory_type' => $request->laboratory_type
        ]);

        $addInfo = [
            'user_id' => $user->id, 'role_id' => $roleId, 'price' => $request->laboratory_cost, 'type' => $request->laboratory_type, 'meta' => $meta
        ];

        $this->objectHistory->createHistory(
            guId: $object->id,
            status: $object->object_status_id->value,
            type: LogType::ARTICLE_LABORATORY_PRICE,
            date: null,
            comment: $request->comment ?? "",
            additionalInfo: $addInfo
        );

    }

    public function attachInspectorObject($user, $roleId, $taskIds, $user_id)
    {
        DB::beginTransaction();
        try {
            $objects = Article::query()->whereIn('task_id', $taskIds)->get();

            foreach ($objects as $object) {
                $oldInspector = $object->users()->where('role_id', UserRoleEnum::INSPECTOR->value)->first();
                $meta = [
                    'user_id' => $user->id, 'role_id' => $roleId, 'old_user_id' => $oldInspector ? $oldInspector->id : null, 'new_user_id' => $user_id
                ];

                $this->objectHistory->createHistory(
                    guId: $object->id,
                    status: $object->object_status_id->value,
                    type: LogType::ARTICLE_INSPECTOR_HISTORY,
                    date: null,
                    comment: $item['comment'] ?? "",
                    additionalInfo: $meta
                );

                if ($oldInspector) {
                    ArticleUser::query()
                        ->where('user_id', $oldInspector->id)
                        ->where('article_id', $object->id)
                        ->where('role_id', UserRoleEnum::INSPECTOR->value)
                        ->update(['role_id' => UserRoleEnum::INSPECTOR->value, 'user_id' => $user_id]);
                } else {
                    $articleUser = new ArticleUser();
                    $articleUser->article_id = $object->id;
                    $articleUser->user_id = $user_id;
                    $articleUser->role_id = UserRoleEnum::INSPECTOR->value;
                    $articleUser->save();
                }

                $this->storeElasticSearch($object);
                DB::commit();
            }
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    public function deletePaymentLog($id, $comment, $user, $roleId)
    {


        DB::beginTransaction();
        try {
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
            $meta = ['guid' => $document->guid, 'uuid' => $document->uuid];

            $log = ArticleHistory::query()->findOrFail($id);
            $object = Article::query()->findOrFail($log->gu_id);
            $addInfo = ['user_id' => $user->id, 'role_id' => $roleId, 'content' => $log->content,  'meta' =>$meta];

            $this->objectHistory->createHistory(
                guId: $object->id,
                status: $object->object_status_id->value,
                type: LogType::ARTICLE_PRICE_DELETE,
                date: null,
                comment: $comment ?? "",
                additionalInfo: $addInfo
            );

            $log->delete();
            $this->storeElasticSearch($object);
            DB::commit();
        } catch (\Exception $exception) {
            DB::rollBack();
            throw  new \Exception('Xatolik');
        }
    }

    public function deadlineChange($user, $roleId)
    {
        DB::beginTransaction();

        try {
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
            $meta = ['guid' => $document->guid, 'uuid' => $document->uuid];

            $object = Article::query()->where('id', request('id'))->first();


            $vendor = $object->users()->where('role_id', UserRoleEnum::QURILISH->value)->first();


            if (in_array($object->funding_source_id, [1, 3])) {

                if ($object->funding_source_id == 3 && empty($object->gnk_id)) {
                    throw new \Exception('Obyektga GNKID biriktirilmagan');
                }

                $response = Http::withBasicAuth(
                    config('app.tender.login'),
                    config('app.tender.password')
                )->post(
                    config('app.tender.url'),
                    [
                        'gnk_id' => $object->gnk_id,
                        'vendor_tin' => $vendor->pinfl,
                    ]
                );

                if (!$response->successful()) {
                    throw new \Exception("Ushbu obyekt uchun to'g'ridan-to'g'ri shartnoma topilmadi.");
                }
            }

            $oldDeadline = $object->deadline;
            $newDeadline = request('deadline');

            $object->update([
                'deadline' => $newDeadline,
                'send_urban' => null,
                'send_tax' => null
            ]);
            $addInfo = ['user_id' => $user->id, 'role_id' => $roleId, 'content' => ['oldValue' => $oldDeadline, 'newValue' => $newDeadline], 'meta' => $meta];

            $historyId = $this->objectHistory->createHistory(
                guId: $object->id,
                status: $object->object_status_id->value,
                type: LogType::ARTICLE_DEADLINE_CHANGE,
                date: null,
                comment: request('comment') ?? "",
                additionalInfo: $addInfo
            );

            $history = ArticleHistory::query()->findOrFail($historyId);

            if (request()->hasFile('file')) {
                $path = request()->file('file')->store('object/files', 'public');
                $history->documents()->create(['url' => $path]);
            }
            DB::commit();
            return $object;

        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }


    public function createPayment($user, $roleId, $objectId)
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
        $meta = ['guid' => $document->guid, 'uuid' => $document->uuid];

        $object = $this->getObjectById($user, $roleId, $objectId);

        $paid = $object->paymentLogs()
            ->get()
            ->sum(function ($log) {
                return $log->content->additionalInfo->amount ?? 0;
            });

        $cost = (float)$object->price_supervision_service - (request('amount') + $paid);


        $addInfo = ['amount' => request('amount'), 'cost' => $cost, 'meta' => $meta];

        $tableId = $this->objectHistory->createHistory(
            guId: $object->id,
            status: $object->object_status_id->value,
            type: LogType::ARTICLE_PAYMENT_CREATE,
            date: null,
            comment: request('comment') ?? "",
            additionalInfo: $addInfo
        );

        $log = ArticleHistory::query()->findOrFail($tableId);

        if (request()->hasFile('file')) {
            $this->documentService->saveFile($log, 'payment-log', request()->file('file'));
        }

        if (request()->hasFile('image')) {
            $this->imageService->saveImage($log, 'payment-log', request()->file('image'));
        }
    }

    public function createPaymentLaboratory($user, $roleId, $objectId)
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
        $meta = ['guid' => $document->guid, 'uuid' => $document->uuid];

        $object = $this->getObjectById($user, $roleId, $objectId);

        $paid = $object->paymentLaboratories()
            ->get()
            ->sum(function ($log) {
                return $log->content->additionalInfo->amount ?? 0;
            });

        $cost = (float)$object->laboratory_cost - (request('amount') + $paid);


        $addInfo = ['amount' => request('amount'), 'cost' => $cost, 'meta' => $meta];

        $tableId = $this->objectHistory->createHistory(
            guId: $object->id,
            status: $object->object_status_id->value,
            type: LogType::ARTICLE_LABORATORY_PAYMENT,
            date: null,
            comment: request('comment') ?? "",
            additionalInfo: $addInfo
        );

        $log = ArticleHistory::query()->findOrFail($tableId);

        if (request()->hasFile('file')) {
            $this->documentService->saveFile($log, 'payment-laboratory-log', request()->file('file'));
        }

        if (request()->hasFile('image')) {
            $this->imageService->saveImage($log, 'payment-laboratory-log', request()->file('image'));
        }
    }

    public function getObjectCount($user, $roleId, $filters)
    {
        $data = $this->getObjects($user, $roleId);
        $query = $this->searchObjects($data, $filters);
        return [
            'all' => $query->clone()->count(),
            'progress' => $query->clone()->where('object_status_id', ObjectStatusEnum::PROGRESS)->count(),
            'frozen' => $query->clone()->where('object_status_id', ObjectStatusEnum::FROZEN)->count(),
            'suspended' => $query->clone()->where('object_status_id', ObjectStatusEnum::SUSPENDED)->count(),
            'submitted' => $query->clone()->where('object_status_id', ObjectStatusEnum::SUBMITTED)->count(),
            'canceled' => $query->clone()->where('object_status_id', ObjectStatusEnum::CANCELED)->count(),
            'lab_not_attached' => $query->clone()->whereNotNull('lab_contract_id')->where('laborant_attached', false)->count(),
        ];
    }

    public function getAccountObjectsQuery($query, $status)
    {
        return $this->articleRepository->getAccountObjectsQuery($query, $status);
    }

    public function createObject($user, $roleId)
    {
        DB::beginTransaction();
        try {

            $document = DocumentSignature::query()
                ->where('guid', $this->objectDto->guid)
                ->where('uuid', $this->objectDto->uuid)
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
                    $this->objectDto->guid,
                    $this->objectDto->uuid
                );

                if (!$signed) {
                    throw new \Exception('Hujjat imzolanmagan yoki tasdiqlashda xatolik yuz berdi.');
                }
            }

            $response = $this->dxaResponse->find($this->objectDto->responseId);
            $response->update([
                'is_accepted' => true,
                'confirmed_at' => now(),
                'send_operator' => null,
                'dxa_response_status_id' => DxaResponseStatusEnum::ACCEPTED,
                'inspection_id' => $user->id,
                'inspection_full_name' => $user->surname . ' ' . $user->name . ' ' . $user->middle_name,
            ]);


            if ($response->notification_type == 2) {
                $oldArticle = Article::query()
                    ->where('task_id', $response->old_task_id)
                    ->orWhere('manual_task_id', $response->old_task_id)
                    ->first();
                if(!$oldArticle) throw  new \Exception('Qayta rasmiylashtirish uchun obyekt mavjud emas. Obyektni tekshiring');

                $this->updateRating($oldArticle, $response);
                $article = $this->saveRepeat($response);
                $this->saveRepeatUser($response, $article);
                $this->saveEmployee($article, false);
                $this->saveHistory(article:$article, user:$user, roleId: $roleId, isUpdate: true,  uuid:$document->uuid);

            } else {
                $article = $this->saveResponse($response);
                $this->saveResponseUser($response, $article);
                $this->saveEmployee($article);
                $this->saveHistory(article: $article, user:  $user, roleId: $roleId, isUpdate: false, uuid: $document->uuid);
                $this->saveBlocks($response, $article);
            }

            if ($article->sphere_id == 57) {
                $this->sendApartment($article);
            }
            if ($article->task_id != 143352463){
                $this->acceptResponse($response);
            }

            $document->signable()->associate($article);
            $document->save();

            $this->storeElasticSearch($article);

            DB::commit();

            return $article;

        } catch (\Exception $exception) {
            DB::rollBack();
            telegramNotification('Obyekt yaratish xatolik', $response->task_id, $exception->getMessage());
            throw new NotFoundException($exception->getMessage(), $exception->getLine(),);
        }

    }

    public function storeElasticSearch($article)
    {
//        $elasticData = ObjectElasticResource::make($article);
//        $this->esSearchService->store('articles', $article->id, $elasticData);
    }

    private function saveHistory($article, $user, $roleId, $isUpdate, $pkcs7 = null, $uuid = null)
    {

        $this->objectHistory->createHistory(
            guId: $article->id,
            status: $article->object_status_id->value,
            type: $isUpdate ? LogType::ARTICLE_UPDATE_HISTORY : LogType::ARTICLE_CREATE_HISTORY,
            date: null,
            comment: $isUpdate ? 'Obyekt yangilandi' : 'Obyekt yaratildi',
            additionalInfo: [
                'user_id' => $user->id, 'role_id' => $roleId, 'pkcs7' => $pkcs7,
            ]
        );
    }

    private function saveRepeat($response)
    {
        $article = Article::query()->where('task_id', $response->old_task_id)->orWhere('manual_task_id', $response->old_task_id)->first();
        if (!$article) throw new NotFoundException('Obyekt topilmadi');
        $article->update([
            'name' => $response->object_name,
            'region_id' => $response->region_id,
            'district_id' => $response->district_id,
            'object_type_id' => $response->object_type_id,
            'organization_name' => $response->organization_name,
            'location_building' => $response->location_building,
            'deadline' => $response->funding_source_id == 1 ? $article->deadline :  ($response->end_term_work ?: $article->deadline),
            'address' => $response->address,
            'construction_works' => $response->construction_works,
            'cadastral_number' => $response->cadastral_number,
            'name_expertise' => $response->name_expertise,
            'difficulty_category_id' => DifficultyCategoryEnum::fromString($response->category_object_dictionary),
            'construction_cost' => $response->cost,
            'organization_projects' => $response->organization_projects,
            'specialists_certificates' => $response->specialists_certificates,
            'contract_file' => $response->contract_file,
            'confirming_laboratory' => $response->confirming_laboratory,
            'file_energy_efficiency' => $response->file_energy_efficiency,
            'legal_opf' => $response->legal_opf,
            'sphere_id' => $response->sphere_id ?? $article->sphere_id,
            'program_id' => $response->program_id ?? $article->program_id,
            'linear_type' => $response->linear_type,
            'dxa_response_id' => $response->id,
            'price_supervision_service' => price_supervision($response->cost),
            'task_id' => $response->task_id,
            'number_protocol' => $response->number_protocol,
            'positive_opinion_number' => $response->positive_opinion_number,
            'date_protocol' => $response->date_protocol,
            'funding_source_id' => $response->funding_source_id ?? $article->funding_source_id,
            'org_stir' => $response->application_stir_pinfl ?? $response->pinfl,
            'gnk_id' => $response->gnk_id,
            'reestr_number' => (int)$response->reestr_number,
        ]);
        return $article;
    }

    private function saveResponse($response)
    {
        try {
            $article = new Article();
            $article->name = $response->object_name;
            $article->region_id = $response->region_id;
            $article->district_id = $response->district_id;
            $article->object_status_id = ObjectStatusEnum::PROGRESS;
            $article->object_type_id = $response->object_type_id;
            $article->organization_name = $response->organization_name;
            $article->location_building = $response->location_building;
            $article->address = $response->address;
            $article->cadastral_number = $response->cadastral_number;
            $article->name_expertise = $response->name_expertise;
            $article->difficulty_category_id = DifficultyCategoryEnum::fromString($response->category_object_dictionary);
            $article->construction_cost = $response->cost;
            $article->sphere_id = $response->sphere_id;
            $article->program_id = $response->program_id;
            $article->construction_works = $response->construction_works;
            $article->linear_type = $response->linear_type;
            $article->appearance_type_id = 1;
            $article->is_accepted = true;
            $article->organization_projects = $response->organization_projects;
            $article->specialists_certificates = $response->specialists_certificates;
            $article->contract_file = $response->contract_file;
            $article->confirming_laboratory = $response->confirming_laboratory;
            $article->file_energy_efficiency = $response->file_energy_efficiency;
            $article->legal_opf = $response->legal_opf;
            $article->lat = $response->lat;
            $article->long = $response->long;
            $article->dxa_response_id = $response->id;
            $article->price_supervision_service = price_supervision($response->cost);
            $article->task_id = $response->task_id;
            $article->number_protocol = $response->number_protocol;
            $article->positive_opinion_number = $response->positive_opinion_number;
            $article->date_protocol = $response->date_protocol;
            $article->funding_source_id = $response->funding_source_id;
            $article->paid = 0;
            $article->payment_deadline = Carbon::now();
            $article->deadline = $response->end_term_work;
            $article->org_stir = $response->application_stir_pinfl ?? $response->pinfl;

            $article->gnk_id = $response->gnk_id;
            $article->reestr_number = (int)$response->reestr_number;

            $article->save();

            return $article;
        } catch (\Exception $exception) {
            throw new $exception;
        }
    }

    private function saveRepeatUser($response, $article)
    {
        foreach ($response->supervisors as $supervisor) {
            $fish = $this->generateFish($supervisor->fish);
            $articleUser = $article->users()->wherePivot('role_id', $supervisor->role_id)->first();
            $user = User::where('pinfl', $supervisor->stir_or_pinfl)->first();


            if ($user) {
                $user->update([
                    'name' => $fish ? $fish[1] : null,
                    'surname' => $fish ? $fish[0] : null,
                    'middle_name' => $fish ? implode(" ", array_slice($fish, 2)) : null,
                    'phone' => $supervisor->phone_number ?? $user->phone,
                    'login' => $supervisor->passport_number ?? $user->login,
                    'organization_name' => $supervisor->organization_name,
                    'password' => bcrypt($supervisor->stir_or_pinfl),
                    'user_status_id' => UserStatusEnum::ACTIVE,
                    'pinfl' => $supervisor->stir_or_pinfl,
                    'identification_number' => $supervisor->identification_number,
                ]);

                if ($articleUser) {
                    if ($articleUser->pinfl != $user->pinfl) {
                        ArticleUser::query()
                            ->where('article_id', $article->id)
                            ->where('role_id', $supervisor->role_id)
                            ->where('user_id', $articleUser->id)
                            ->delete();

                        $article->users()->attach($user->id, ['role_id' => $supervisor->role_id]);
                        $this->changeRegulations($article->id, $articleUser->id, $user->id, $supervisor->role_id);
                    }
                } else {
                    $article->users()->attach($user->id, ['role_id' => $supervisor->role_id]);
                }

                if (!$user->roles()->where('role_id', $supervisor->role_id)->exists())
                    UserRole::query()->create([
                        'user_id' => $user->id,
                        'role_id' => $supervisor->role_id,
                    ]);
            } else {
                $user = User::create([
                    'name' => $fish ? $fish[1] : null,
                    'surname' => $fish ? $fish[0] : null,
                    'middle_name' => $fish ? implode(" ", array_slice($fish, 2)) : null,
                    'phone' => $supervisor->phone_number,
                    'login' => $supervisor->passport_number ?? $supervisor->stir_or_pinfl,
                    'organization_name' => $supervisor->organization_name,
                    'password' => bcrypt($supervisor->stir_or_pinfl),
                    'user_status_id' => UserStatusEnum::ACTIVE,
                    'pinfl' => $supervisor->stir_or_pinfl,
                    'identification_number' => $supervisor->identification_number,
                ]);
                if ($articleUser) {
                    if ($articleUser->pinfl != $user->pinfl) {
                        ArticleUser::query()
                            ->where('article_id', $article->id)
                            ->where('role_id', $supervisor->role_id)
                            ->where('user_id', $articleUser->id)
                            ->delete();

                        $article->users()->attach($user->id, ['role_id' => $supervisor->role_id]);
                        $this->changeRegulations($article->id, $articleUser->id, $user->id, $supervisor->role_id);
                    }
                } else {
                    $article->users()->attach($user->id, ['role_id' => $supervisor->role_id]);
                }

                UserRole::query()->create([
                    'user_id' => $user->id,
                    'role_id' => $supervisor->role_id,
                ]);
            }
        }

        $articleInspector = $article->users()->wherePivot('role_id', UserRoleEnum::INSPECTOR->value)->first();
        $inspector = User::query()->find($response->inspector_id);
        if ($articleInspector) {
            if (isset($inspector->pinfl) && $articleInspector->pinfl != $inspector->pinfl) {
                ArticleUser::query()
                    ->where('article_id', $article->id)
                    ->where('role_id', UserRoleEnum::INSPECTOR->value)
                    ->where('user_id', $articleInspector->id)
                    ->delete();
                $article->users()->attach($inspector->id, ['role_id' => UserRoleEnum::INSPECTOR->value]);
            }
        } else {
            $article->users()->attach($inspector->id, ['role_id' => UserRoleEnum::INSPECTOR->value]);
        }
    }

    private function changeRegulations($objectId, $oldUserId, $newUserId, $roleId)
    {

        Regulation::query()
            ->where('object_id', $objectId)
            ->where('user_id', $oldUserId)
            ->where('role_id', $roleId)
            ->update([
                'user_id' => $newUserId,
                'role_id' => $roleId,
            ]);

        Regulation::query()
            ->where('object_id', $objectId)
            ->where('created_by_user_id', $oldUserId)
            ->where('created_by_role_id', $roleId)
            ->update([
                'created_by_user_id' => $newUserId,
                'created_by_role_id' => $roleId,
            ]);
    }

    private function saveResponseUser($response, $article)
    {
        foreach ($response->supervisors as $supervisor) {
            $fish = $this->generateFish($supervisor->fish);
            $user = User::where('pinfl', $supervisor->stir_or_pinfl)->first();
            if ($user) {
                $user->update([
                    'name' => $fish ? $fish[1] : null,
                    'surname' => $fish ? $fish[0] : null,
                    'middle_name' => $fish ? implode(" ", array_slice($fish, 2)) : null,
                    'phone' => $supervisor->phone_number ?? $user->phone,
                    'login' => $supervisor->passport_number ?? $user->login,
                    'organization_name' => $supervisor->organization_name,
                    'password' => bcrypt($supervisor->stir_or_pinfl),
                    'user_status_id' => UserStatusEnum::ACTIVE,
                    'pinfl' => $supervisor->stir_or_pinfl,
                    'identification_number' => $supervisor->identification_number,
                ]);


                $article->users()->attach($user->id, ['role_id' => $supervisor->role_id]);
                if (!$user->roles()->where('role_id', $supervisor->role_id)->exists())
                    UserRole::query()->create([
                        'user_id' => $user->id,
                        'role_id' => $supervisor->role_id,
                    ]);
            }
            if (!$user) {
                $user = User::create([
                    'name' => $fish ? $fish[1] : null,
                    'surname' => $fish ? $fish[0] : null,
                    'middle_name' => $fish ? implode(" ", array_slice($fish, 2)) : null,
                    'phone' => $supervisor->phone_number,
                    'login' => $supervisor->passport_number,
                    'organization_name' => $supervisor->organization_name,
                    'password' => bcrypt($supervisor->stir_or_pinfl),
                    'user_status_id' => UserStatusEnum::ACTIVE,
                    'pinfl' => $supervisor->stir_or_pinfl,
                    'identification_number' => $supervisor->identification_number,
                ]);
                $article->users()->attach($user->id, ['role_id' => $supervisor->role_id]);
                UserRole::query()->create([
                    'user_id' => $user->id,
                    'role_id' => $supervisor->role_id,
                ]);
            }
        }

        $this->attachInspector($article, $response->inspector_id);
    }

    public function createObjectManual($taskId)
    {
        DB::beginTransaction();
        try {
            $response = DxaResponse::query()->where('task_id', $taskId)->first();
            if (!$response) throw new NotFoundException('Ariza topilmadi');
            $article = $this->saveResponse($response);
            $this->saveResponseUser($response, $article);
            $this->saveEmployee($article, false);
            $article->update([
                'created_at' => $response->created_at,
                'deadline' => Carbon::parse($response->created_at)->addYears(2)
            ]);
            DB::commit();
        } catch (\Exception $exception) {
            DB::rollBack();
            throw new  \Exception($exception->getMessage());
        }
    }

    public function createObjectRegister($request, $user, $roleId)
    {
        DB::beginTransaction();
        try {
            $article = Article::query()->create($request->except('users', 'inspector_id', 'files', 'user_files', 'blocks', 'expertise_files'));
            $this->attachInspector($article, $request['inspector_id']);
            $this->saveArticleUsers($request['users'], $article);
            $this->saveFiles($request['files'], $request['expertise_files'], $request['user_files'], $article);
            $this->saveHistory($article, $user, $roleId, false);
            $response = $this->createResponse($article, $request);
            $this->saveResponseUsers($request['users'], $response);

            $this->saveBlocksRegister($article, $request['blocks'], $response);


            DB::commit();
            return $article;
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    public function createResponse($article, $request)
    {
        try {
            $response = new DxaResponse();
            $response->object_name = $article->name;
            $response->inspector_id = $request['inspector_id'];
            $response->region_id = $article->region_id;
            $response->district_id = $article->district_id;
            $response->dxa_response_status_id = DxaResponseStatusEnum::ACCEPTED;
            $response->object_type_id = $article->object_type_id;
            $response->organization_name = $article->organization_name;
            $response->location_building = $article->location_building;
            $response->address = $article->address;
            $response->cadastral_number = $article->cadastral_number;
            $response->name_expertise = $article->name_expertise;
            $response->category_object_dictionary = $article->difficulty->difficulty;
            $response->cost = $article->construction_cost;
            $response->sphere_id = $article->sphere_id;
            $response->program_id = $article->program_id;
            $response->construction_works = $article->construction_works;
            $response->linear_type = $article->linear_type;
            $response->organization_projects = $article->organization_projects;
            $response->specialists_certificates = $article->specialists_certificates;
            $response->contract_file = $article->contract_file;
            $response->confirming_laboratory = $article->confirming_laboratory;
            $response->file_energy_efficiency = $article->file_energy_efficiency;
            $response->legal_opf = $article->legal_opf;
            $response->lat = $article->lat;
            $response->long = $article->long;
            $response->notification_type = 1;
            $response->administrative_status_id = 1;
            $response->manual_task_id = $article->manual_task_id;
            $response->number_protocol = $article->number_protocol;
            $response->name_date_posopin = $article->name_date_posopin;
            $response->price_supervision_service = $article->price_supervision_service;
            $response->positive_opinion_number = $article->positive_opinion_number;
            $response->date_protocol = $article->date_protocol;
            $response->funding_source_id = $article->funding_source_id;
            $response->end_term_work = $article->deadline;
            $response->application_stir_pinfl = $article->org_stir;
            $response->files = $article->files;
            $response->expertise_files = $article->expertise_files;
            $response->user_files = $article->user_files;
            $response->gnk_id = $article->gnk_id;
            $response->reestr_number = $article->reestr_number;
            $response->save();

            return $response;
        } catch (\Exception $exception) {
            throw $exception;
        }
    }


    private function attachInspector($article, $inspectorId)
    {
        $article->users()->attach($inspectorId, ['role_id' => UserRoleEnum::INSPECTOR->value]);
    }

    private function saveFiles($files, $expertises, $users, $article)
    {

        $objectFiles = [];
        $expertiseFiles = [];
        $userFiles = [];
        foreach ($files as $file) {
            $path = $file->store('object/files', 'public');
            $objectFiles[] = $path;
        }

        foreach ($expertises as $file) {
            $path = $file->store('object/files', 'public');
            $expertiseFiles[] = $path;
        }

        foreach ($users as $file) {
            $path = $file->store('object/files', 'public');
            $userFiles[] = $path;
        }

        $article->update([
            'files' => json_encode($objectFiles),
            'expertise_files' => json_encode($expertiseFiles),
            'user_files' => json_encode($userFiles),
        ]);
    }

    private function saveResponseUsers($data, $response)
    {
        foreach ($data as $item) {
            $dxaResSupervisor = new DxaResponseSupervisor();
            $dxaResSupervisor->dxa_response_id = $response->id;
            $dxaResSupervisor->role_id = $item['role_id'] ?? null;
            $dxaResSupervisor->organization_name = $item['organization_name'] ?? null;
            $dxaResSupervisor->identification_number = $item['pinfl'] ?? null;
            $dxaResSupervisor->stir_or_pinfl = $item['pinfl'] ?? null;
            $dxaResSupervisor->fish = $item['surname'] . ' ' . $item['name'] . ' ' . $item['middle_name'];
            $dxaResSupervisor->passport_number = $item['passport_number'] ?? null;
            $dxaResSupervisor->phone_number = $item['phone'] ?? null;
            $dxaResSupervisor->save();
        }

    }

    private function saveArticleUsers($data, $article)
    {
        foreach ($data as $item) {
            $user = User::query()->where('pinfl', $item['pinfl'])->first();
            if ($user) {
                $article->users()->attach($user->id, ['role_id' => $item['role_id']]);
                if (!$user->roles()->where('role_id', $item['role_id'])->exists())
                    UserRole::query()->create([
                        'user_id' => $user->id,
                        'role_id' => $item['role_id'],
                    ]);
            } else {
                $user = User::create([
                    'name' => $item['name'] ?? null,
                    'surname' => $item['surname'] ?? null,
                    'middle_name' => $item['middle_name'] ?? null,
                    'phone' => $item['phone'] ?? null,
                    'login' => $item['passport_number'] ?? null,
                    'organization_name' => $item['organization_name'] ?? null,
                    'password' => bcrypt($item['pinfl']),
                    'user_status_id' => UserStatusEnum::ACTIVE,
                    'pinfl' => $item['pinfl'],
                    'identification_number' => $item['pinfl'],
                ]);
                $article->users()->attach($user->id, ['role_id' => $item['role_id']]);
                UserRole::query()->create([
                    'user_id' => $user->id,
                    'role_id' => $item['role_id'],
                ]);
            }
        }
    }

    public function updateObjectManual($taskId)
    {
        DB::beginTransaction();
        try {
            $response = DxaResponse::query()->where('task_id', $taskId)->first();
            if (!$response) throw new NotFoundException('Ariza topilmadi');

            $article = $this->saveRepeat($response);
            $this->saveRepeatUser($response, $article);
            $this->saveEmployee($article, false);
            DB::commit();

        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    private function generateFish(?string $fish): ?array
    {
        return $fish ? explode(" ", $fish) : null;
    }

    private function saveBlocks(DxaResponse $response, Article $article): void
    {
        $isAccepted = !($response->administrative_status_id == 8);
        if ($response->blocks) {
            foreach ($response->blocks as $block) {
                $this->blockRepository->updateBlockByArticle($block->id, $article, $isAccepted);
            }
        }

    }

    public function getRating($inn)
    {
        $data =  Http::withBasicAuth(
            config('app.rating.login'),
            config('app.rating.password'),
        )
            ->timeout(10)
            ->connectTimeout(5)
            ->get(config('app.rating.url') . $inn);

        if ($data->successful()) {
            return $data->json();
        }
        return null;
    }

    private function updateRating($article, $response)
    {
        $ratingConfig = config('app.gasn.rating');
        if (!$ratingConfig) {
            throw new \Exception("Rating config not found.");
        }
        $loyiha = $article->users()->where('role_id', UserRoleEnum::LOYIHA->value)->first();
        $qurilish = $article->users()->where('role_id', UserRoleEnum::QURILISH->value)->first();

        $responseLoyiha = $response->supervisors()->where('role_id', UserRoleEnum::LOYIHA->value)->first();
        $responseQurilish = $response->supervisors()->where('role_id', UserRoleEnum::QURILISH->value)->first();

        $loyihaRating = $this->getRating((int)($responseLoyiha->stir_or_pinfl ?? 0));
        $qurilishRating = $this->getRating((int)($responseQurilish->stir_or_pinfl ?? 0));

        $data = json_decode($article->rating, true) ?? [];

        $oldLoyihaRating = $data[0]['loyiha'] ?? null;
        $oldQurilishRating = $data[0]['qurilish'] ?? null;

        $rating[0]['loyiha'] = $loyiha
            ? ($responseLoyiha && $responseLoyiha->stir_or_pinfl != $loyiha->pinfl ? $loyihaRating['data'] ?? null : $oldLoyihaRating)
            : ($responseLoyiha ? $loyihaRating['data']['data'] ?? null : null);

        $rating[0]['qurilish'] = $qurilish
            ? ($responseQurilish && $responseQurilish->stir_or_pinfl != $qurilish->pinfl ? $qurilishRating['data'] ?? null : $oldQurilishRating)
            : ($responseQurilish ? $qurilishRating['data'] ?? null : null);

        $article->update([
            'rating' => json_encode($rating)
        ]);
    }


    private function saveEmployee($article, $create = true): void
    {
        $rating = [];
        $muallif = $article->users()->where('role_id', UserRoleEnum::MUALLIF->value)->first();
        $texnik = $article->users()->where('role_id', UserRoleEnum::TEXNIK->value)->first();
        $buyurtmachi = $article->users()->where('role_id', UserRoleEnum::BUYURTMACHI->value)->first();
        $loyiha = $article->users()->where('role_id', UserRoleEnum::LOYIHA->value)->first();
        $ichki = $article->users()->where('role_id', UserRoleEnum::ICHKI->value)->first();
        $qurilish = $article->users()->where('role_id', UserRoleEnum::QURILISH->value)->first();


        if ($create) {
            $loyihaRating = $this->getRating((int)$loyiha->identification_number);
            $qurilishRating = $this->getRating((int)$qurilish->identification_number);

            $rating[] = [
                'loyiha' => $loyihaRating['data'] ?? null,
                'qurilish' => $qurilishRating['data'] ?? null,
            ];

            $article->update([
                'rating' => json_encode($rating)
            ]);
        }

        if (!UserEmployee::query()->where('user_id', $texnik->id)->where('parent_id', $buyurtmachi->id)->exists()) {
            UserEmployee::query()->create([
                'user_id' => $texnik->id,
                'parent_id' => $buyurtmachi->id,
            ]);
        }

        if (!UserEmployee::query()->where('user_id', $muallif->id)->where('parent_id', $loyiha->id)->exists()) {
            UserEmployee::query()->create([
                'user_id' => $muallif->id,
                'parent_id' => $loyiha->id,
            ]);
        }

        if (!UserEmployee::query()->where('user_id', $ichki->id)->where('parent_id', $qurilish->id)->exists()) {
            UserEmployee::query()->create([
                'user_id' => $ichki->id,
                'parent_id' => $qurilish->id,
            ]);
        }

    }

    private function saveBlocksRegister($article, $blocks, $response)
    {
        foreach ($blocks as $blockData) {
            $blockAttributes = [
                'name' => $blockData['name'],
                'article_id' => $article->id,
                'dxa_response_id' => $response->id,
                'floor' => $blockData['floor'] ?? null,
                'construction_area' => $blockData['construction_area'] ?? null,
                'count_apartments' => $blockData['count_apartments'] ?? null,
                'height' => $blockData['height'] ?? null,
                'length' => $blockData['length'] ?? null,
                'block_mode_id' => $blockData['block_mode_id'] ?? null,
                'block_type_id' => $blockData['block_type_id'] ?? null,
                'appearance_type' => filter_var($blockData['appearance_type'], FILTER_VALIDATE_BOOLEAN),
                'selected_work_type' => false,
                'created_by' => Auth::id(),
                'status' => true,
            ];

            Block::create($blockAttributes);
        }
    }

    public function deadlineLog()
    {
        $logs = DB::table('article_deadline_log')
            ->leftJoin('articles', 'article_deadline_log.object_id', '=', 'articles.id')
            ->select(
                'article_deadline_log.id as id',
                'article_deadline_log.object_id as object_id',
                'article_deadline_log.old_deadline as old_deadline',
                'article_deadline_log.new_deadline as new_deadline',
                'article_deadline_log.files as files',
                'article_deadline_log.created_at as created_at',
                'articles.name as object_name',
                'articles.task_id as task_id'
            )
            ->orderBy('article_deadline_log.created_at', 'desc')
            ->paginate(30);

        $logs->getCollection()->transform(function ($item) {
            $item->files = json_decode($item->files, true);
            return $item;
        });

        return $logs;
    }


    private function sendTax($object)
    {
        try {
            $authUsername = config('app.mygov.login');
            $authPassword = config('app.mygov.password');
            $customer = $object->users()->where('role_id', UserRoleEnum::BUYURTMACHI->value)->first();
            $builder = $object->users()->where('role_id', UserRoleEnum::QURILISH->value)->first();

            $data = [
                'send_id' => $object->id,
                'send_date' => Carbon::now(),
                'cadastral_number' => $object->cadastral_number,
                'construction_type_name' => $object->construction_works,
                'construction_type_id' => ConstructionWork::fromString($object->construction_works)->value,
                'created_at' => $object->created_at,
                'customer_name' => $customer ? $customer->organization_name : '',
                'district_soato' => $object->district->soato ?? '',
                'general_contractor' => $builder ? $builder->organization_name : '',
                'not_completed_construction' => '',
                'object_name' => $object->name,
                'open_date' => $object->deadline,
                'pinfl_customer' => $customer->name ? $customer->pinfl : '',
                'pinfl_general_contractor' => $builder->name ? $builder->pinfl : '',
                'price_construction_installation' => $object->construction_cost,
                'region_soato' => $object->region->soato,
                'tin_customer' => $customer->name ? '' : $customer->pinfl,
                'tin_general_contractor' => $builder->name ? '' : $builder->pinfl,
            ];

            Http::withBasicAuth($authUsername, $authPassword)->post('https://api.shaffofqurilish.uz/api/v1/constructionSave', $data);
        } catch (\Exception $exception) {
            Log::info($exception->getMessage());
        }

    }

    public function acceptResponse(DxaResponse $response)
    {
        try {
            $authUsername = config('app.mygov.login');
            $authPassword = config('app.mygov.password');
            $url = $response->notification_type == 1 ? '/action/issue-amount' : '/action/issue-amount-copy';

            if ($response->object_type_id == 2) {
                $apiUrl = config('app.mygov.url') . '/update/id/' . $response->task_id . $url;
                $formName = $response->notification_type == 1 ? 'IssueAmountV4FormNoticeBeginningConstructionWorks' : 'IssueAmountCopyV4FormNoticeBeginningConstructionWorks';
//                $formName = 'IssueAmountV4FormNoticeBeginningConstructionWorks';
            } else {
                $apiUrl = config('app.mygov.linear') . '/update/id/' . $response->task_id . $url;
                $formName = $response->notification_type == 1 ? 'IssueAmountFormRegistrationStartLinearObject' : 'IssueAmountCopyFormRegistrationStartLinearObject';
//                $formName = 'IssueAmountFormRegistrationStartLinearObject';
            }

            $domain = URL::to('/object-info') . '/' . $response->task_id;

            $qrImage = base64_encode(QrCode::format('png')->size(200)->generate($domain));

            $offerRepo = '<a href="https://nazorat.mc.uz/mygov/pdf/' . $response->id . '">Oferta</a>';

            $qrImageTag = '<img src="data:image/png;base64,' . $qrImage . '" alt="QR Image" />';
            $rekvizit = Rekvizit::query()->where('district_id', $response->district_id)->first();
            if (!$rekvizit) throw new NotFoundException('Rekvizit topilmadi');

            $return = Http::withBasicAuth($authUsername, $authPassword)
                ->post($apiUrl, [
                    $formName => [
                        "requisites" => $rekvizit->name ?? '',
                        "loacation_rep" => $response->region->name_uz . ' ' . $response->district->name_uz . ' ' . $response->location_building,
                        "name_rep" => $response->organization_name,
                        "amount" => price_supervision($response->cost) ?? '',
                        "offer_repo" => $response->agree_ofert ? $offerRepo : null,
                        "qr_image" => $qrImageTag,
                        "qr_comment" => "Ushbu QR kod obyekt pasporti hisoblanadi. QR kodni obyektning ko‘rinarli joyiga o‘rnatib qo‘yishingiz talab etiladi"
                    ]
                ]);


            if ($return->failed()){
                telegramNotification('Obyekt yaratish mygov salbiy javob', $response->task_id, $return->json());
                throw new NotFoundException("mygovda xatolik yuz berdi");
            } else{
                telegramNotification('Obyekt yaratish mygov ijobiy javob', $response->task_id, $return->json());
            }
        } catch (\Exception $exception) {
            telegramNotification('Obyekt yaratish mygov salbiy javob', $response->task_id, $exception->getMessage());
            throw new NotFoundException($exception->getMessage(), $exception->getCode());
        }
    }

    public function sendApartment($article)
    {
        try {
            $client = new Client();
            $apiCredentials = config('app.apartment.login') . ':' . config('app.apartment.password');
            $headers = [
                'Authorization' => 'Basic ' . base64_encode($apiCredentials),
            ];

            $data = ArticleOpenDataResource::make($article);

            $response = $client->post(config('app.apartment.url'), [
                'headers' => $headers,
                'json' => $data,
                'verify' => false
            ]);

            $article->update(['uy_integration' => true]);

            return $response;
        } catch (\Exception $exception) {
            return null;
        }
    }

    public function sendHome($id)
    {
        try {
            $object = $this->findById($id);
            Http::withBasicAuth('Baymax', '7reFNkYoY2BiW7k')
                ->post('https://turarjoy.kommunal.uz/api/new-apartment-add', ['cadastr_number'=>$object->cadastral_number]);

        }catch (\Exception $exception){
            return null;
        }
    }

    /**
     * @throws GuzzleException
     */
    public function getLifts($cad_num)
    {
        try {
            $client = new Client();
            $apiCredentials = config('app.lift.login') . ':' . config('app.lift.password');
            $headers = [
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($apiCredentials)
            ];
            $body = [
                'kadastr_number' => "$cad_num"
            ];
            $response = $client->request('POST', config('app.lift.url'), [
                'headers' => $headers,
                'verify' => false,
                'body' => json_encode($body)
            ])->getBody()->getContents();
            return json_decode($response);
        } catch (\Exception $exception) {
            return null;
        }

    }

}
