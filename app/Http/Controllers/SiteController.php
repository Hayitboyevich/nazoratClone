<?php

namespace App\Http\Controllers;

use App\Enums\RegulationStatusEnum;
use App\Enums\UserRoleEnum;
use App\Exceptions\GeneralJsonException;
use App\Exports\ObjectExport;
use App\Models\ClaimOrganizationReview;
use App\Models\Declaration;
use App\Models\DeclarationConclusion;
use App\Models\DxaResponse;
use App\Models\HiddenWorksAct;
use App\Models\Inspection;
use App\Models\LabClaim;
use App\Models\LabContract;
use App\Models\Presentation;
use App\Models\Regulation;
use App\Models\Rekvizit;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Interfaces\ArticleRepositoryInterface;
use App\Repositories\Interfaces\ClaimGovRepositoryInterface;
use App\Services\ClaimGovService;
use App\Services\ElasticSearchService;
use App\Services\MediaMtxService;
use App\Services\UserService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Maatwebsite\Excel\Facades\Excel;
use SimpleSoftwareIO\QrCode\Facades\QrCode;


class SiteController extends Controller
{
    private ArticleRepositoryInterface $articleRepository;
    protected ElasticSearchService $elastic;


    public function __construct(
        ArticleRepositoryInterface $articleRepository,
        ElasticSearchService $elastic,
        protected ClaimGovService $claimGovService,
        private ClaimGovRepositoryInterface $claim_gov_repository,
        protected  UserService $userService,
    )
    {
        $this->articleRepository = $articleRepository;
        $this->elastic = $elastic;
    }

//    public function elastic(Request $request)
//    {
//        $index = 'articles';
//        $filters = request()->only([
//            'id',
//            'status',
//            'sphere_id',
//            'name',
//            'manual',
//            'null_sphere',
//            'inn',
//            'start_date',
//            'end_date',
//            'customer_name',
//            'inspector_id',
//            'funding_source',
//            'object_type',
//            'task_id',
//            'region_id',
//            'district_id',
//            'user_search'
//        ]);
//
//        $builder = new ElasticQueryBuilder();
//
//        $builder
//            ->addNestedTerm('status', 'status.id', $filters['status'] ?? null)
//            ->addTerm('id', $filters['id'] ?? null)
//            ->addMultiMatch($filters['task_id'] ?? null, ['task_id', 'manual_task_id'])
//            ->addMatchPhrase('name', $filters['name'] ?? null)
//            ->addRange('created_at', $filters['start_date'] ?? null, $filters['end_date'] ?? null)
//            ->addNestedTerm('sphere','sphere.id', $filters['sphere_id'] ?? null)
//            ->addNestedTerm('funding_source','funding_source.id', $filters['funding_source'] ?? null)
//            ->addNestedTerm('object_type','object_type.id', $filters['object_type'] ?? null)
//            ->addNestedTerm('region','region.id', $filters['region_id'] ?? null)
//            ->addNestedTerm('district','district.id', $filters['district_id'] ?? null);
//
//        if (!empty($filters['null_sphere'])) {
//            $builder->addExistsNot('sphere.id');
//        }
//
//
//        if (!empty($filters['customer_name'])) {
//            $builder->addBoolShould([
//                ['match_phase' => ['name' => $filters['customer_name']]],
//                [
//                    'nested' => [
//                        'path' => 'users',
//                        'query' => [
//                            'bool' => [
//                                'must' => [
//                                    ['term' => ['users.role.id' => 8]],
//                                    ['match' => ['users.organization_name' => $filters['customer_name']]],
//                                ]
//                            ]
//                        ]
//                    ]
//                ]
//            ]);
//        }
//
//        if (!empty($filters['inn'])) {
//            $builder->addUserFilter(9, 'users.pinfl', $filters['inn']);
//        }
//
//
//        if (!empty($filters['inspector_id'])) {
//            $builder->addUserFilter(3, 'users.id', (int)$filters['inspector_id']);
//        }
//
//
//        $query = $builder->build();
//
//        $response = $this->elastic->client()->search([
//            'index' => config('elasticsearch.prefix') . $index,
//            'body' => [
//                'size' => 40,
//                'sort' => [['id' => ['order' => 'desc']]],
//                'track_total_hits' => true,
//                'query' => $query,
//            ],
//        ]);
//
//        $articles = collect($response['hits']['hits'])->pluck('_source');
//        $total = $response['hits']['total']['value'] ?? 0;
//
//        return response()->json([
//            'total' => $total,
//            'data' => $articles,
//        ]);
//    }

//    public function elastic(Request $request)
//    {
//        $term = $request->get('q', '');
//        $index = 'articles';
//
//
//        $query = empty($term)
//            ? ['match_all' => (object)[]]
//            : ['multi_match' => [
//                'query' => $term,
//                'fields' => ['task_id'],
//            ]];
//
//
//        $response = $this->elastic->client()->search([
//            'index' => config('elasticsearch.prefix') . $index,
//            'body' => [
//                'size' => 20,
//                'sort' => [['id' => ['order' => 'desc']],],
//                'query' => $query,
//            ],
//        ]);
//
//        $responseCount = $this->elastic->client()->count([
//            'index' => config('elasticsearch.prefix') . 'articles',
//        ]);
//
//
//        $articles = collect($response['hits']['hits'])->pluck('_source');
//
//        return response()->json([
//            'total' => $responseCount['count'],
//            'data' => $articles,
//        ]);
//    }

    public function index($id)
    {
        $visitedTaskIds = [];
        $currentTaskId = $id;
        $objectModel = null;

        while ($currentTaskId && !isset($visitedTaskIds[$currentTaskId])) {
            $visitedTaskIds[$currentTaskId] = true;

            $objectModel = $this->articleRepository->findByTaskId($currentTaskId);

            if ($objectModel) {
                break;
            }

            $currentTaskId = DxaResponse::query()
                ->where('old_task_id', $currentTaskId)
                ->where('dxa_response_status_id', 4)
                ->value('task_id');
        }

        abort_unless($objectModel, 404);

        $rating = json_decode($objectModel->rating ?? '[]', true);

        $ratingData = is_array($rating) ? ($rating[0] ?? []) : [];

        $loyihaData = data_get($ratingData, 'loyiha', []);
        $qurilishData = data_get($ratingData, 'qurilish', []);

        $ratingLoyiha = [];

        if (isset($loyihaData[0]) && is_array($loyihaData[0])) {
            foreach ($loyihaData as $item) {
                $value = data_get($item, 'reyting') ?? data_get($item, 'reyting_loyha');

                if ($value !== null && $value !== '') {
                    $ratingLoyiha[] = $value;
                }
            }
        } else {
            $value = data_get($loyihaData, 'reyting_loyha')
                ?? data_get($loyihaData, 'reyting');

            if ($value !== null && $value !== '') {
                $ratingLoyiha[] = $value;
            }
        }

        $ratingQurilish = [];

        if (isset($qurilishData[0]) && is_array($qurilishData[0])) {
            foreach ($qurilishData as $item) {
                $value = data_get($item, 'reyting');

                if ($value !== null && $value !== '') {
                    $ratingQurilish[] = $value;
                }
            }
        } else {
            foreach (['reyting_umumiy', 'reyting_mel'] as $key) {
                $value = data_get($qurilishData, $key);

                if ($value !== null && $value !== '') {
                    $ratingQurilish[] = $value;
                }
            }
        }


        $users = $objectModel->users()
            ->wherePivotIn('role_id', [
                UserRoleEnum::LOYIHA->value,
                UserRoleEnum::QURILISH->value,
                UserRoleEnum::BUYURTMACHI->value,
                UserRoleEnum::TEXNIK->value,
                UserRoleEnum::MUALLIF->value,
                UserRoleEnum::ICHKI->value,
            ])
            ->get()
            ->keyBy('pivot.role_id');

        $getCertificates = function (UserRoleEnum $role) use ($users) {
            $user = $users->get($role->value);

            if (!$user) {
                return [];
            }

            try {
                return $this->userService->getCertificate($user, $role->value) ?? [];
            } catch (\Throwable $e) {
                report($e);

                return [];
            }
        };

        $texnikSert = $getCertificates(UserRoleEnum::TEXNIK);
        $muallifSert = $getCertificates(UserRoleEnum::MUALLIF);
        $ichkiSert = $getCertificates(UserRoleEnum::ICHKI);

        $objectModel->load([
            'regulations.createdByUser',
            'regulations.createdByRole',
            'regulations.responsibleUser',
            'regulations.responsibleRole',
            'regulations.violations',
            'regulations.monitoring.block',
        ]);

        $data = getData(config('app.gasn.reestr'), $objectModel->reestr_number);

        $conclusion_file = data_get($data, 'data.conclusion_file', '');

            $regulationCount = $objectModel->regulations->count();
            $violationCount = $objectModel->regulations->flatMap(function ($regulation) {
                        return $regulation->violations;
            })->unique('id')->count();

            $finishedRegulations = $objectModel->regulations()->whereIn('regulation_status_id', [RegulationStatusEnum::LATE_EXECUTION, RegulationStatusEnum::ELIMINATED])->count();


        return view('qr-copy', [
            'object' => $objectModel,
            'users' => $users,

            'texnik' => $texnikSert,
            'muallif' => $muallifSert,
            'ichki' => $ichkiSert,

            'rating_loyiha' => $ratingLoyiha,

            'rating_qurilish' => $ratingQurilish,

            'reestr' => $conclusion_file,
            'regulation_count' => $regulationCount,
            'violation_count' => $violationCount,
            'finished_regulations' => $finishedRegulations,
        ]);
    }

    public function regulation($id)
    {
        try {
            $regulation = Regulation::query()->findOrFail($id);
            $createdByRole = Role::query()->findOrFail($regulation->created_by_role_id);
            $createdByUser = User::query()->findOrFail($regulation->created_by_user_id);
            $object = $regulation->object;
            $responsibleUser = User::query()->findOrFail($regulation->user_id);
            $responsibleRole = Role::query()->findOrFail($regulation->role_id);
            $organizationName = '';

            $roleMap = [
                UserRoleEnum::TEXNIK->value => UserRoleEnum::BUYURTMACHI->value,
                UserRoleEnum::ICHKI->value => UserRoleEnum::QURILISH->value,
                UserRoleEnum::MUALLIF->value => UserRoleEnum::LOYIHA->value,
            ];


            if (is_scalar($responsibleRole->id) && isset($roleMap[$responsibleRole->id])) {
                $user = $object->users()->where('role_id', $roleMap[$responsibleRole->id])->first();
                if (!empty($user)) {
                    $organizationName = $user->name ? $user->full_name : $user->organization_name;
                }
            }

            $domain = URL::to('/regulation-info').'/'.$regulation->id;

            $qrImage = base64_encode(QrCode::format('png')->size(200)->generate($domain));

//            $qrImageTag = '<img src="data:image/png;base64,' . $qrImage . '" alt="QR Image" />';
            $pdf = Pdf::loadView('pdf.regulation', compact(
                'regulation',
                'object',
                'responsibleUser',
                'responsibleRole',
                'createdByUser',
                'createdByRole',
                'qrImage',
                'organizationName'
            ));

            return $pdf->download('yozma-korsatma'.time().'.pdf');

        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()]);
        }
    }

    public function regulationSimple($id)
    {
        try {
            $regulation = Regulation::query()->findOrFail($id);
            $createdByRole = Role::query()->findOrFail($regulation->created_by_role_id);
            $createdByUser = User::query()->findOrFail($regulation->created_by_user_id);
            $object = $regulation->object;
            $responsibleUser = User::query()->findOrFail($regulation->user_id);
            $responsibleRole = Role::query()->findOrFail($regulation->role_id);
            $domain = URL::to('/regulation-info').'/'.$regulation->id;

            $qrImage = base64_encode(QrCode::format('png')->size(200)->generate($domain));

            $pdf = Pdf::loadView('pdf.regulation', compact(
                'regulation',
                'object',
                'responsibleUser',
                'responsibleRole',
                'createdByUser',
                'createdByRole',
                'qrImage'
            ));

            return view('pdf.regulation', compact(
                'regulation',
                'object',
                'responsibleUser',
                'responsibleRole',
                'createdByUser',
                'createdByRole',
                'qrImage'));

        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getLine());
        }
    }

    public function presentationPdf($id)
    {
        try {
            $presentation = Presentation::find($id);
            $inspection = Inspection::query()->where(['region_id' => $presentation->object->region_id])->first();
            $buyurtmachi = $presentation->object->users()->wherePivot('role_id', UserRoleEnum::BUYURTMACHI->value)->first();
            $pudrat = $presentation->object->users()->wherePivot('role_id', UserRoleEnum::QURILISH->value)->first();
            $domain = URL::to('/presentation-pdf').'/'.$id;



            $qrImage = base64_encode(QrCode::format('png')->size(200)->generate($domain));
            $pdf = PDF::loadView('pdf.presentation', compact('presentation', 'inspection', 'buyurtmachi', 'pudrat', 'qrImage'));

            return $pdf->stream('protocol');
        } catch (\Exception $exception) {
            return $exception->getMessage() .' '.$exception->getLine();
        }
    }

    public function presentationDeedPdf($id)
    {
        try {
            $presentation = Presentation::find($id);
            $inspection = Inspection::query()->where(['region_id' => $presentation->object->region_id])->first();
            $buyurtmachi = $presentation->object->users()->wherePivot('role_id', UserRoleEnum::BUYURTMACHI->value)->first();
            $pudrat = $presentation->object->users()->wherePivot('role_id', UserRoleEnum::QURILISH->value)->first();
            $domain = URL::to('/regulation-info').'/'.$id;



            $qrImage = base64_encode(QrCode::format('png')->size(200)->generate($domain));
            $pdf = PDF::loadView('pdf.presentation-deed', compact('presentation', 'inspection', 'buyurtmachi', 'pudrat', 'qrImage'));

            return $pdf->stream('protocol');
        } catch (\Exception $exception) {
            return $exception->getMessage() .' '.$exception->getLine();
        }
    }

    public function hiddenWorkPdf($id)
    {
        try {
            $work = HiddenWorksAct::query()->with(['object', 'histories', 'internal', 'technic', 'author'])->whereId($id)->whereStatus(3)->first();

            $domain = URL::to('/hidden-work-pdf') . '/' . $id;

            $qrImage = base64_encode(QrCode::format('png')->size(200)->generate($domain));

            $pdf = Pdf::loadView('pdf.hide-work', compact(
                'work',
                'qrImage'
            ));

            return $pdf->stream('works');

        }catch (\Exception $exception){
            return $exception->getMessage() .' '.$exception->getLine();
        }
    }

    public function declarationPdf($id)
    {
        $declaration = Declaration::query()->find($id);
        $vendor = $declaration->object->users()->where('role_id', UserRoleEnum::QURILISH->value)->first();
        $designer = $declaration->object->users()->where('role_id', UserRoleEnum::LOYIHA->value)->first();
        $organization = $declaration->object->users()->where('role_id', UserRoleEnum::BUYURTMACHI->value)->first();

        $internal = $declaration->object->users()->where('role_id', UserRoleEnum::ICHKI->value)->first();
        $domain = URL::to('/declaration-pdf').'/'.$declaration->id;

        $qrImage = base64_encode(QrCode::format('png')->size(200)->generate($domain));

        $pdf = Pdf::loadView('pdf.declaration', compact('declaration', 'qrImage', 'vendor', 'designer', 'organization', 'internal'));

        return $pdf->stream();
    }

    public function claimgovPdf($id)
    {
        try {
            $claimGov = $this->claimGovService->findById($id);
            $object = $claimGov->object;
            $declaration = $claimGov->declaration;
            $designer = $object->users()->where('role_id', UserRoleEnum::LOYIHA->value)->first();
            $vendor = $object->users()->where('role_id', UserRoleEnum::QURILISH->value)->first();
            $pdf = Pdf::loadView('pdf.claim_gov', compact('claimGov', 'declaration', 'designer', 'vendor', 'object'));
            return $pdf->stream();
        }catch (\Exception $exception){
            return response()->json(['error' => $exception->getMessage()]);
        }
    }

    public function  claimgovConclusionPdf($id)
    {
        $conclusion = DeclarationConclusion::query()->find($id);
        $vendor = $conclusion->object->users()->where('role_id', UserRoleEnum::QURILISH->value)->first();
        $organization = $conclusion->object->users()->where('role_id', UserRoleEnum::BUYURTMACHI->value)->first();
        $designer = $conclusion->object->users()->where('role_id', UserRoleEnum::LOYIHA->value)->first();
        $internal = $conclusion->object->users()->where('role_id', UserRoleEnum::ICHKI->value)->first();
        $domain = URL::to('/claimgov/conclusion/') . '/' . $id;
        $qrImage = base64_encode(QrCode::format('png')->size(200)->generate($domain));

        if (!$conclusion) return null;
        $pdf = Pdf::loadView(
            'pdf.' . ($conclusion->role_id == 6 ? 'technic' : 'author') . '-conclusion',
            compact('conclusion', 'vendor', 'organization', 'internal', 'designer', 'qrImage')
        );

        return $pdf->stream('file');
    }

    public function object($id)
    {
        try{
            $dxa = DxaResponse::query()->findOrFail($id);
            $region = $dxa->region;
            $inspection = Inspection::query()->where('region_id', $dxa->region_id)->first();
            $rekvizit = Rekvizit::query()->where('district_id', $dxa->district_id)->first();

            $domain = 'https://nazorat.mc.uz/mygov/pdf'.$id;

            $qrImage = base64_encode(QrCode::format('png')->size(200)->generate($domain));

            $pdf = Pdf::loadView('pdf.object', compact('inspection', 'region', 'dxa', 'rekvizit', 'qrImage'));
            return $pdf->download('obyekt-'.time().'.pdf');

//            return view('pdf.object', compact('id'));

        }catch (\Exception $exception){
            return response()->json(['error' => $exception->getMessage()]);
        }
    }

    public function lab($id)
    {
        try {
            $months_uz = [
                'January' => 'yanvar',
                'February' => 'fevral',
                'March' => 'mart',
                'April' => 'aprel',
                'May' => 'may',
                'June' => 'iyun',
                'July' => 'iyul',
                'August' => 'avgust',
                'September' => 'sentabr',
                'October' => 'oktyabr',
                'November' => 'noyabr',
                'December' => 'dekabr',
            ];

            $lab_claim = LabClaim::query()->with([
                'region',
                'district',
                'labClaimUsers' => function ($q) {
                    $q->where('role_id', UserRoleEnum::BUYURTMACHI->value)
                        ->with(['user.region', 'user.district']);
                }
            ])
                ->findOrFail($id);

            Carbon::setLocale('uz');
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

            $contractInfo = $contract?->contractInfo()
                ->with('labBlock')
                ->get()
                ->groupBy(fn ($item) => $item?->labBlock?->block_name);

            $details = $contract?->contractInfo;

            if (!$contract) throw new GeneralJsonException('Lab contract not found!', 404);
                $inspeksiya_confirmed_at_par = Carbon::parse($contract->inspeksiya_confirmed_at);
                $customer_confirmed_at_par = Carbon::parse($contract->customer_confirmed_at);
                $month_name_ins = $months_uz[$inspeksiya_confirmed_at_par->format('F')];
                $month_name_cus = $months_uz[$customer_confirmed_at_par->format('F')];
                $customer_confirmed_at = $inspeksiya_confirmed_at_par->format('Y') . ' yil ' . $inspeksiya_confirmed_at_par->format('d') . ' ' . $month_name_ins;
                $inspeksiya_confirmed_at = $customer_confirmed_at_par->format('Y') . ' yil ' . $customer_confirmed_at_par->format('d') . ' ' . $month_name_cus;
                if (!$lab_claim->manually_created) {
                    $inspection_director = User::withTrashed()->find($contract->inspection_director_id);
                } else {
                    $inspection_director = User::withTrashed()->find($lab_claim->inspection_director_id);
                }
                $inspeksiya = Inspection::query()->where(['region_id' => $lab_claim->region_id])->first();
                $rekvizit = Rekvizit::query()->where('district_id', $lab_claim->district_id)->first();
                $pdf = Pdf::loadView('lab', compact('lab_claim', 'contract', 'customer_confirmed_at',
                    'inspeksiya_confirmed_at', 'inspeksiya', 'details', 'rekvizit', 'inspection_director', 'contractInfo'));

            return $pdf->stream('obyekt-'.time().'.pdf');
//            $pdfOutput = $pdf->output();
//            return base64_encode($pdfOutput);
        } catch (\Exception $exception) {
            report($exception);
            return $exception->getMessage() . '  ' . '  '. $exception->getLine();
        }
    }

    public function claimGovPdfOrganizationDownload($id)
    {
        $review = $this->claim_gov_repository->organizationConclusion($id);

        $review->load(['organization', 'claim','object', 'object.region', 'object.district', 'user']);
        $domain = config('app.url')."/claim-gov/organization-pdf/$id/download";
        $qr_image = base64_encode(QrCode::format('png')->size(200)->generate($domain));




        $pdf = Pdf::loadView('pdf.claim_gov_review', compact('review', 'qr_image'));
        return $pdf->stream($review->organization->name. "-" .$review->id);
    }

    public function json()
    {
        $file = storage_path() . "/objects.json";
        $objects = json_decode(file_get_contents($file), true);
        return Excel::download(new ObjectExport($objects), 'objects.xlsx');
    }

    public function testExcel()
    {

        $data = ClaimOrganizationReview::query()
            ->with([
                'claim.object.region',
                'claim.object.district',
            ])
            ->whereIn('organization_id', [18])
            ->whereNotNull('answer')
            ->whereHas('claim', function ($query) {
                $query->whereBetween('created_at', [
                    '2025-01-01 00:00:00',
                    '2026-07-09 23:59:59',
                ]);
            })
            ->get()
            ->map(function ($item) {

                $object = $item->claim?->object;

                $buyer = DB::table('article_users as au')
                    ->join('users as u', 'u.id', '=', 'au.user_id')
                    ->where('au.article_id', $object?->id)
                    ->where('au.role_id', 8)
                    ->select('u.organization_name', 'u.pinfl')
                    ->first();

                return [
                    'Ariza raqami' => $item->claim?->guid,
                    'Obyekt nomi' => $object?->name,
                    'Obyekt ariza raqami' => $object?->task_id ?? $object?->manual_task_id,
                    'Viloyat' => $object?->region?->name_uz,
                    'Tuman' => $object?->district?->name_uz,
                    'Buyurtmachi' => $buyer?->organization_name,
                    'Buyurtmachi INN' => $buyer?->pinfl,

                    'User type' => $item->claim?->user_type == 'I'
                        ? 'Jismoniy shaxs'
                        : ($item->claim?->user_type == 'J'
                            ? 'Yuridik shaxs'
                            : null),

                    'Inspeksiyaga kelgan vaqt' => $item->claim?->created_at,
                    'Tashkilotlarga yuborilgan sana' => $item->created_at,
                    'Xulosa berilgan vaqt' => $item->answered_at,
                    'Status' => $item->status ? 'Ijobiy' : 'Salbiy',

                    'Xulosa' => is_array($item->answer)
                        ? json_encode($item->answer, JSON_UNESCAPED_UNICODE)
                        : $item->answer,
                ];
            });
        return Excel::download(
            new class($data) implements
                \Maatwebsite\Excel\Concerns\FromCollection,
                \Maatwebsite\Excel\Concerns\WithHeadings {

                public function __construct(public $data)
                {
                }

                public function collection()
                {
                    return collect($this->data);
                }

                public function headings(): array
                {
                    return [
                        'Ariza raqami',
                        'Obyekt nomi',
                        'Obyekt ariza raqami',
                        'Viloyat',
                        'Tuman',
                        'Buyurtmachi',
                        'Buyurtmachi INN',
                        'User type',
                        'Inspeksiyaga kelgan vaqt',
                        'Tashkilotlarga yuborilgan sana',
                        'Xulosa berilgan vaqt',
                        "Status",
                        'Xulosa',
                    ];
                }
            },
            'claims.xlsx'
        );
    }

    public function test($id, MediaMtxService $media)
    {

        $url = $media->createOrUpdate(
            5940,
            "rtsp://admin:Passw0rd@12.13.1.63:8558/Streaming/Channels/101"
        );

        return response()->json([
            'camera_id'=>5940,
            'stream_url'=>$url
        ]);



    }

    public function testMyGov()
    {
        try {
            $authUsername = config('app.mygov.login');
            $authPassword = config('app.mygov.password');
            $action = request('action');
            $taskId = request('taskId');
            $formName = request('formName');
            $notice = request('notice');
            $theme = request('theme');


            $apiUrl = config('app.mygov.url') . '/update/id/' . $taskId . '/action/'. $action;

            $return =  Http::withBasicAuth($authUsername, $authPassword)
                ->post($apiUrl, [
                    $formName => [
                        $theme => $notice,
                    ]
                ]);

            return $return->json();

        }catch (\Exception $exception){
            return $exception->getMessage();
        }
    }
}
