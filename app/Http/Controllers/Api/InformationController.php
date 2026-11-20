<?php

namespace App\Http\Controllers\Api;

use App\Enums\ObjectStatusEnum;
use App\Enums\UserRoleEnum;
use App\Enums\UserStatusEnum;
use App\Exceptions\NotFoundException;
use App\Exports\RegulationExport;
use App\Http\Requests\CheckDocumentRequest;
use App\Http\Resources\BasisResource;
use App\Http\Resources\DistrictResource;
use App\Http\Resources\NormativeDocumentResource;
use App\Http\Resources\NotificationResource;
use App\Http\Resources\ObjectStatusResource;
use App\Http\Resources\ProgramResource;
use App\Http\Resources\RegionResource;
use App\Http\Resources\RoleResource;
use App\Http\Resources\SphereResource;
use App\Http\Resources\TopicResource;
use App\Models\Article;
use App\Models\Basis;
use App\Models\District;
use App\Models\Monitoring;
use App\Models\NormativeDocument;
use App\Models\NotificationLog;
use App\Models\Program;
use App\Models\Region;
use App\Models\Regulation;
use App\Models\Sphere;
use App\Models\Topic;
use App\Models\User;
use App\Models\Violation;
use App\Repositories\UserRepository;
use App\Services\ArticleService;
use App\Services\ClaimService;
use App\Services\InformationService;
use App\Services\InvoiceService;
use App\Services\ShaffofIdService;
use Barryvdh\DomPDF\Facade\Pdf;
use GuzzleHttp\Client;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Maatwebsite\Excel\Facades\Excel;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class InformationController extends BaseController
{
    protected ArticleService $articleService;
    protected InvoiceService $invoiceService;
    protected UserRepository $userRepository;
    protected ShaffofIdService $shaffofIdService;
    protected InformationService $informationService;
    protected ClaimService $claimService;

    public function __construct(
        ArticleService $articleService,
        InvoiceService $invoiceService,
        UserRepository $userRepository,
        ShaffofIdService $shaffofIdService,
        InformationService $informationService,
        ClaimService $claimService,
    )
    {
        // $this->middleware('auth');
        $this->articleService = $articleService;
        $this->invoiceService = $invoiceService;
        $this->userRepository = $userRepository;
        $this->shaffofIdService = $shaffofIdService;
        $this->informationService = $informationService;
        $this->claimService = $claimService;
        parent::__construct();
    }

    public function monitoringObjects(): JsonResponse
    {
        $customerInn = request('customer_inn');
        $pudratInn = request('pudrat_inn');

        $informationService = new InformationService();
        try {
            $data = getData(config('app.gasn.monitoring'), request('expertise_number'))['data']['result']['data'];
            if (!empty($data)) {
                foreach ($data as $item) {
                    $sphere = Sphere::query()->find($item['object_types_id']);
                    $program = Program::query()->find($item['project_type_id']);
                    $pudratTender = collect($item['pudrat_tender'])
                        ->sortByDesc('tender_created_at')
                        ->first();

                    $meta[] = [
                        'id' => $item['id'],
                        'gnk_id' => $item['gnk_id'],
                        'project_type' => ProgramResource::make($program),
                        'name' => $item['name'],
                        'end_term_work_days' => $item['end_term_work_days']
                            ?? ($pudratTender['end_term_work_days'] ?? null),
                        'sphere' => SphereResource::make($sphere),
                    ];
                }

            } else {
                $meta = $informationService->customer($customerInn, $pudratInn);
            }

            return $this->sendSuccess($meta, 'Monitoring objects successfully.');
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }

    }

    public function organization(): JsonResponse
    {
        try {
            $cadNumber = request('stir');
            $response = Http::withBasicAuth('orgapi-v1', '*@org-apiv_*ali')
                ->get('https://api-sert.mc.uz/api/orginfoapi/' . $cadNumber);


            if ($response->successful()) {

                return response()->json($response->json());

            }
            return $this->sendError('Xatolik yuz berdi');
        } catch (\Exception $exception) {
            return $this->sendError("xatolik aniqlandi", $exception->getMessage());
        }
    }

    public function statement(): JsonResponse
    {
        try {
            $data = getData(config('app.gasn.bayonnoma'), \request('conc'));
            return $this->sendSuccess($data['data'], 'Tender');
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function tender(): JsonResponse
    {
        try {
            $data = getData(config('app.gasn.tender'), \request('conc'));
            return $this->sendSuccess($data['data']['result']['data'], 'Tender');
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function monitoringCustomer(): JsonResponse
    {
        try {
            $customerInn = request('customer_inn');
            $pudratInn = request('pudrat_inn');
            $client = new Client();
            $apiCredentials = config('app.passport.login') . ':' . config('app.passport.password');

            $url = 'https://api.shaffofqurilish.uz/api/v1/request/monitoring-objects?customer_inn=' . $customerInn . '&pudrat_inn=' . $pudratInn;


            $resClient = $client->post($url,
                [
                    'headers' => [
                        'Authorization' => 'Basic ' . base64_encode($apiCredentials),
                    ]
                ]);
            $response = json_decode($resClient->getBody(), true);

            if (isset($response['result']['data']['result']['data'])) {
                foreach ($response['result']['data']['result']['data'] as &$item) {
                    if (isset($item['pudrat_tender']) && is_array($item['pudrat_tender'])) {
                        $item['pudrat_tender'] = array_values($item['pudrat_tender']);

                        $item['pudrat_tender'] = array_filter($item['pudrat_tender'], function ($tender) use ($pudratInn) {
                            return $tender['winner_inn'] == $pudratInn;
                        });

                        if (!empty($item['pudrat_tender'])) {
                            usort($item['pudrat_tender'], function ($a, $b) {
                                return strtotime($b['confirmed_date']) - strtotime($a['confirmed_date']);
                            });

                            $item['pudrat_tender'] = [reset($item['pudrat_tender'])];
                        } else {
                            $item['pudrat_tender'] = [];
                        }

                        if (empty($item['pudrat_tender'])) {
                            unset($item);
                        }
                    }
                }

                $response['result']['data']['result']['data'] = array_values($response['result']['data']['result']['data']);
            }

            $data = $response['result']['data']['result']['data'][0];

            $meta = [
                'id' => $data['id'],
                'gnk_id' => $data['gnk_id'],
                'project_type_id' => $data['project_type_id'],
                'name' => $data['name'],
                'end_term_work_days' => $data['end_term_work_days'] ?? $data['pudrat_tender'][0]['end_term_work_days'],
            ];


            return $this->sendSuccess($meta, 'Monitoring customer information successfully.');
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function reestr(): JsonResponse
    {
        try {
            $data = getData(config('app.gasn.reestr'), \request('reestr_number'));
            return $this->sendSuccess($data['data'], 'Reestr');
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function cadastr(): JsonResponse
    {
        try {
            $cadNumber = request('cad_number');
            $response = Http::withBasicAuth(config('app.passport.login'), config('app.passport.password'))
                ->post(config('app.gasn.cadastr'), [
                    'cad_num' => $cadNumber,
                ]);

            if ($response->successful()) {
                $data = $response->json()['result']['data'];

                $region = Region::query()->where('soato', $data['region_id'])->first();
                $district = District::query()->where('soato', $data['district_id'])->first();
                $meta = [
                    'region' => RegionResource::make($region),
                    'district' => DistrictResource::make($district),
                    'name' => $data['name'],
                    'subjects' => $data['subjects'],
                    'address' => $data['address'],
                    'cad_number' => $data['cad_number'],
                ];

                return $this->sendSuccess($meta, 'Cadastr');

            }
            return $this->sendError('Xatolik yuz berdi', 'Cadastr');

        } catch (\Exception $exception) {
            return $this->sendError('Kadastr bilan xatolik yuz berdi', $exception->getMessage());
        }
    }

    public function monitoringGNK(): JsonResponse
    {
        try {
            $data = getData(config('app.gasn.get_monitoring'), request('gnk_id'))['data']['result']['data'];


            if (!empty($data)) {
                foreach ($data as $item) {
                    $sphere = Sphere::query()->find($item['object_types_id']);
                    $program = Program::query()->find($item['project_type_id']);
                    $pudratTender = collect($item['pudrat_tender'])
                        ->sortByDesc('tender_created_at')
                        ->first();

                    $meta[] = [
                        'id' => $item['id'],
                        'gnk_id' => $item['gnk_id'],
                        'project_type' => $program ? ProgramResource::make($program) : null,
                        'name' => $item['name'],
                        'end_term_work_days' => $item['end_term_work_days']
                            ?? ($pudratTender['end_term_work_days'] ?? null),
                        'sphere' => $sphere ? SphereResource::make($sphere) : null,
                    ];
                }

                return $this->sendSuccess($meta, 'Monitoring');
            }

            return $this->sendSuccess([], 'Monitoring');

        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }


    public function rating(): JsonResponse
    {
        try {
//            $data = getData(config('app.gasn.rating'), \request('inn'));
//            return $this->sendSuccess($data['data']['data'], 'Reyting');

            $data =  Http::withBasicAuth(
                config('app.rating.login'),
                config('app.rating.password'),
            )
                ->timeout(10)
                ->connectTimeout(5)
                ->get(config('app.rating.url') . request('inn'));

            if ($data->successful()) {
                $data = $data->json();
            }else{
                return $this->sendError('Xatolik yuz berdi', 'Rating');
            }
            return $this->sendSuccess($data['data'], 'Rating');

        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function conference(): JsonResponse
    {
        try {
            $data = getData(config('app.gasn.conference'), \request('conc'));
            return $this->sendSuccess($data['data'], 'Kengash');
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function objectCount(): JsonResponse
    {
        try {
            $objectQuery = Article::query()->whereIn('object_status_id', [2, 3, 4]);
            $regulationQuery = Regulation::query()->whereHas('object', function ($query) {
                $query->whereIn('object_status_id', [2, 3, 4]);
            });

            $data = [
                'object_count' => $objectQuery->count(),
                'object_amount' => $objectQuery->selectRaw('SUM(cast(construction_cost as numeric))')->value('sum'),
                'gov_object_amount' => (clone $objectQuery)->where('funding_source_id', 1)->selectRaw('SUM(cast(construction_cost as numeric))')->value('sum'),

//                'object_amount' => $objectQuery->sum('construction_cost'),
                'gov_object_count' => (clone $objectQuery)->where('funding_source_id', 1)->count(),
//                'gov_object_amount' => (clone $objectQuery)->where('funding_source_id', 1)->sum('construction_cost'),
                'monitoring_count' => Monitoring::query()->whereHas('object', function ($query) {
                    $query->whereIn('object_status_id', [2, 3, 4]);
                })->count(),
                'regulation_count' => $regulationQuery->count(),
                'regulation_com_count' => (clone $regulationQuery)->whereIn('regulation_status_id', [6, 8])->count(),
                'violation_count' => Violation::query()->whereHas('regulations.object', function ($query) {
                    $query->whereIn('object_status_id', [2, 3, 4]);
                })->count(),
            ];

            return $this->sendSuccess($data, 'Count');
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage());
        }
    }


    public function expertiseFiles(): JsonResponse
    {
        try {
            $data = getData(config('app.gasn.expertise'), \request('reestr_number'));
            return $this->sendSuccess($data['data']['data'], 'Expertise Files');
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function getExpertiseFile($taskId)
    {
        try {
            $object = $this->articleService->findByTaskId($taskId);

            $data = getData(config('app.gasn.reestr'), $object->reestr_number);

            $url = $data['data']['conclusion_file'] ?? null;

            if (!$url) {
                abort(404);
            }

            return redirect()->away($url);

        } catch (\Exception $exception) {
            abort(404);
        }
    }

    public function getShaffofId(): JsonResponse
    {
        try {
            $code = request('code');
            $codeVerifier = request('code_verifier');

            $data = $this->shaffofIdService->handleCallback($code, $codeVerifier);
            if (empty($data)) return $this->sendError('Malumot topilmadi');

            return $this->sendSuccess($data, 'Success get data');

        }catch (\Exception $exception){
            return $this->sendError($exception->getMessage(), $exception->getMessage());
        }
    }

    public function checkDocument(CheckDocumentRequest $request): JsonResponse
    {
        try {
           $data =  $this->shaffofIdService->checkDocument($this->user, $this->roleId, $request);
           return $this->sendSuccess($data, 'Data');
        }catch (\Exception $exception){
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function shaffofLogout()
    {

        $data = $this->shaffofIdService->logout($this->user);
    }


    public function checkUser(): JsonResponse
    {
        try {
            $url = 'https://sso.egov.uz/sso/oauth/Authorization.do?grant_type=one_authorization_code
            &client_id=' . config('services.oneId.id') .
                '&client_secret=' . config('services.oneId.secret') .
                '&code=' . request('code') .
                '&redirect_url=' . config('services.oneId.redirect');
            $resClient = Http::post($url);
            $response = json_decode($resClient->getBody(), true);


            $url = 'https://sso.egov.uz/sso/oauth/Authorization.do?grant_type=one_access_token_identify
            &client_id=' . config('services.oneId.id') .
                '&client_secret=' . config('services.oneId.secret') .
                '&access_token=' . $response['access_token'] .
                '&Scope=' . $response['scope'];
            $resClient = Http::post($url);
            $data = json_decode($resClient->getBody(), true);
            $tin = !empty($data['pkcs_legal_tin']) ? $data['pkcs_legal_tin'] : null;
            $user = User::query()
                ->where('pinfl', $data['pin'])
                ->where('user_status_id', UserStatusEnum::ACTIVE->value)
                ->where('active', 1)
                ->first();

            if (!$user and $tin) {
                $user = User::where('active', 1)
                    ->where('user_status_id', UserStatusEnum::ACTIVE->value)
                    ->where('pinfl', $tin)
                    ->first();
            }

            if (!$user) {
                $inn = empty($data['pkcs_legal_tin']);
                $person_data = [
                    "name" => $data['first_name'],
                    "surname" => $data['sur_name'],
                    "middle_name" => $data['mid_name'],
                    "pinfl" => $inn ? $data['pin'] : $data['pkcs_legal_tin'],
                    "login" => $inn ? $data['pport_no'] : $data['pkcs_legal_tin'],
                    "password" => $inn ? Hash::make($data['pin']) : Hash::make($data['pkcs_legal_tin']),
                    "active" => UserStatusEnum::ACTIVE->value,
                    "user_status_id" => UserStatusEnum::ACTIVE->value,
                ];
                if (!$inn) {
                    $get_company_info = $this->invoiceService->getCompanyInfo($data['pkcs_legal_tin']);
                    $person_data['organization_name'] = $get_company_info['shortName'];
                    $person_data['director_full_name'] = $get_company_info['director'];
                }
                $new_user = $this->userRepository->createOrUpdate($person_data);
                $this->userRepository->attachRole($new_user, UserRoleEnum::BUYURTMACHI->value);
            }
            if ($user->active == 0) throw new ModelNotFoundException('Foydalanuvchi faol emas');

            if (request('app_id')) {
                $user->update([
                    'notification_app_id' => request('app_id'),
                ]);
            }

            $combinedData = $data['pin'] . ':' . $response['access_token'];

            $encodedData = base64_encode($combinedData);

            $meta = [
                'roles' => RoleResource::collection($user->roles),
                'access_token' => $encodedData,
                'full_name' => $user->full_name
            ];

            return $this->sendSuccess($meta, 'Success');

        } catch (\Exception $exception) {
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function time(): JsonResponse
    {
        return $this->sendSuccess(now(), 'Now');
    }

    public function getRepoPDF()
    {
        try {
            $informationService = new InformationService();
            $data = $informationService->getConclusionPDF(request('task_id'), request('type'));
            return $this->sendSuccess($data, 'Conclusion PDF');
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function normativeDocs(): JsonResponse
    {
        try {
            $docs = NormativeDocument::query()->paginate(request('per_page', 10));

            return $this->sendSuccess(NormativeDocumentResource::collection($docs), 'Normative', pagination($docs));

        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function topics(): JsonResponse
    {
        try {
            $topics = Topic::query()
                ->where('parent_id', request('doc_id'))
                ->paginate(request('per_page', 10));
            return $this->sendSuccess(TopicResource::collection($topics), 'Topics', pagination($topics));
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function basis(): JsonResponse
    {
        try {
            $topics = Basis::query()
                ->where('parent_id', request('topic_id'))
                ->paginate(request('per_page', 10));
            return $this->sendSuccess(BasisResource::collection($topics), 'Basis', pagination($topics));
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function notifications()
    {
        try {
            $user = Auth::user();
            $query = $user->notifications();
            if (!is_null(request('read'))) {
                $query->where('read', request('read'));
            }
            $notifications = $query->orderBy('id', 'DESC')->paginate(request('per_page', 10));
            return $this->sendSuccess(NotificationResource::collection($notifications), 'Notifications', pagination($notifications));
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function notificationCount()
    {
        try {
            $user = Auth::user();
            $data = [
                'all' => $user->notifications()->count(),
                'notRead' => $user->notifications()->where('read', false)->count(),
            ];
            return $this->sendSuccess($data, 'Notifications');
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function notificationRead(): JsonResponse
    {
        try {
            NotificationLog::query()->find(request('id'))->update(['read' => true]);
            return $this->sendSuccess([], 'Notification Read');
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function qrImage($id): JsonResponse
    {
        try {
            $article = Article::query()->find($id);

            $url = URL::to('/object-info') . '/' . $article->task_id;

            $qrImage = base64_encode(QrCode::format('png')->size(200)->generate($url));

            return $this->sendSuccess($qrImage, 'Qr Image');

        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function userObjects(): JsonResponse
    {
        try {
            $responseArr = [];

            $pinfl = request('pinfl');
            $sphere = request('sphere');

            $users = User::query()
                ->where('pinfl', $pinfl)
                ->with(['roles', 'objects.region', 'objects.district', 'objects.objectStatus'])
                ->get();

            if ($users->isEmpty()) {
                throw new NotFoundHttpException('Foydalanuvchi topilmadi');
            }

            if (strlen($pinfl) == 9) {
                $allowedRoleIds = [8, 9, 10, 38];
            } else {
                $allowedRoleIds = [5, 6, 7, 8, 38];
            }

            $data = collect();

            foreach ($users as $user) {

                $userRoleIds = $user->roles->pluck('id')->toArray();

                if (count(array_intersect($userRoleIds, $allowedRoleIds)) === 0) {
                    continue;
                }

                $rolesData = collect($user->roles)
                    ->filter(function ($role) use ($allowedRoleIds) {
                        return in_array($role->id, $allowedRoleIds);
                    })
                    ->map(function ($role) use ($user, $sphere) {

                        $objects = $user->objects()
                            ->when(isset($sphere), function ($q) use ($sphere) {
                                $q->where('sphere_id', $sphere);
                            })
                            ->whereIn('object_status_id', [
                                ObjectStatusEnum::PROGRESS,
                                ObjectStatusEnum::FROZEN,
                                ObjectStatusEnum::SUSPENDED,
                                ObjectStatusEnum::SUBMITTED,
                            ])
                            ->where('role_id', $role->id)
                            ->get();

                        return [
                            'role_name' => $role->name,
                            'object_count' => $objects->count(),
                            'object_list' => $objects->map(function ($object) {
                                return [
                                    'id' => $object->id,
                                    'name' => $object->name,
                                    'cadastr' => $object->cadastral_number,
                                    'address' => $object->location_building,
                                    'lat' => $object->lat,
                                    'long' => $object->long,
                                    'task_id' => $object->task_id,
                                    'district' => optional(new DistrictResource($object->district))->toArray(request()),
                                    'region' => optional(new RegionResource($object->region))->toArray(request()),
                                    'status' => optional(new ObjectStatusResource($object->objectStatus))->toArray(request()),
                                ];
                            })->toArray(),
                        ];
                    })
                    ->values();

                $data = $data->merge($rolesData);
            }

            if ($data->isEmpty()) {
                throw new NotFoundHttpException('Foydalanuvchi topilmadi');
            }

            $responseArr['user_objects'] = $data->values()->toArray();

            if (strlen($pinfl) == 9) {

                $rating = getData(config('app.gasn.rating'), $pinfl);
                if (isset($rating['data']['data'])) {
                    $responseArr['rating'] = $rating['data']['data'];
                }

                $tenderObjects = Http::get("https://apisitender.mc.uz/api/get-winner-tender-info/" . $pinfl)->json();

                $tenderArr = [];

                if (
                    isset($tenderObjects['code']) &&
                    $tenderObjects['code'] == 200 &&
                    $tenderObjects['result']['data']['tender_count'] > 0
                ) {
                    foreach ($tenderObjects['result']['data']['tenders'] as $item) {
                        $tenderArr[] = [
                            'name' => $item['lot_name'],
                            'region' => $item['region'],
                            'soxa_id' => $item['soxa_id'],
                            'soxa' => $item['soxa'],
                            'lot_id' => $item['lot_id'],
                            'date' => $item['protocol_date']
                        ];
                    }
                }

                $responseArr['tender_objects'] = $tenderArr;

                $user = $this->articleService->getUserByInnAndRole($pinfl, UserRoleEnum::QURILISH->value);

                $tempObjects = [];

                if ($user) {

                    $objects = $user->objects();

                    $claimObjects = $objects
                        ->where('object_status_id', ObjectStatusEnum::SUBMITTED)
                        ->whereNotNull('sphere_id')
                        ->get();

                    $tempObjects = $claimObjects->map(function ($object) {
                        return [
                            'id' => $object->id,
                            'name' => $object->name,
                            'task_id' => $object->task_id,
                            'region' => $object->region->name_uz,
                            'soxa_id' => $object->sphere_id,
                            'soxa' => $object->sphere->name_uz,
                            'date' => $object->closed_at
                        ];
                    })->toArray();
                }

                $responseArr['claim_objects'] = $tempObjects;
            }

            return $this->sendSuccess($responseArr, 'User Objects');

        } catch (\Exception $exception) {
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }


    public function regulationExcel()
    {
        try {

            $timestamp = time();
            $fileName = 'Toshkent.xlsx';

            return Excel::download(new RegulationExport(1), $fileName);
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getLine());
        }
    }

    public function conclusionDxaPDF($task_id)
    {
        try {
            $data = $this->informationService->getConclusionPDF($task_id);

            $base64 = data_get($data, 'file');

            if (empty($base64)) {
                throw new NotFoundException('PDF fayl topilmadi');
            }

            $base64 = preg_replace('/^data:application\/pdf;base64,/', '', $base64);

            $pdf = base64_decode($base64, true);

            if ($pdf === false) {
                throw new \Exception('PDF Base64 formati noto‘g‘ri');
            }

            if (isset($data->md5) && md5($pdf) !== $data->md5) {
                throw new \Exception('PDF fayl yaxlitligi buzilgan');
            }

            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "attachment; filename=\"xulosa_{$task_id}.pdf\"",
                'Content-Length' => strlen($pdf),
            ]);
        }catch (\Exception $exception){
            abort(response()->json([
                'success' => false,
                'message' => 'PDF fayl topilmadi',
            ], 404));
        }

    }

    public function conclusionClaimPDF($task_id)
    {
        try {
            $data = $this->claimService->getConclusionPDF($task_id);

            $base64 = data_get($data, 'file');

            if (empty($base64)) {
                throw new NotFoundException('PDF fayl topilmadi');
            }

            $base64 = preg_replace('/^data:application\/pdf;base64,/', '', $base64);

            $pdf = base64_decode($base64, true);

            if ($pdf === false) {
                throw new \Exception('PDF Base64 formati noto‘g‘ri');
            }

            if (isset($data->md5) && md5($pdf) !== $data->md5) {
                throw new \Exception('PDF fayl yaxlitligi buzilgan');
            }

            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "attachment; filename=\"xulosa_{$task_id}.pdf\"",
                'Content-Length' => strlen($pdf),
            ]);
        }catch (\Exception $exception){
            abort(response()->json([
                'success' => false,
                'message' => 'PDF fayl topilmadi',
            ], 404));
        }

    }


}
