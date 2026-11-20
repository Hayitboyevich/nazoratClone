<?php

namespace App\Http\Controllers\Api;

use App\DTO\RegulationDto;
use App\Enums\LawyerStatusEnum;
use App\Enums\RegulationStatusEnum;
use App\Enums\UserRoleEnum;
use App\Exports\AuthorRegulationsExport;
use App\Helpers\ClaimStatuses;
use App\Http\Requests\RegulationAcceptRequest;
use App\Http\Requests\RegulationDemandRequest;
use App\Http\Requests\RegulationEventRequest;
use App\Http\Requests\RegulationFineRequest;
use App\Http\Resources\AuthorRegulationResource;
use App\Http\Resources\RegulationListResource;
use App\Http\Resources\RegulationResource;
use App\Models\ActViolation;
use App\Models\AiControl;
use App\Models\Article;
use App\Models\AuthorRegulation;
use App\Models\Regulation;
use App\Models\RegulationDemand;
use App\Models\RegulationEvent;
use App\Models\RegulationFine;
use App\Models\Role;
use App\Models\User;
use App\Notifications\InspectorNotification;
use App\Repositories\Interfaces\ClaimRepositoryInterface;
use App\Services\EimzoService;
use App\Services\HistoryService;
use App\Services\MessageTemplate;
use App\Services\RegulationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use PHPUnit\Framework\Exception;
use Rap2hpoutre\FastExcel\FastExcel;
class RegulationController extends BaseController
{

    private HistoryService $historyService;

    public function __construct(
        protected RegulationService $regulationService,
        protected EimzoService  $imzoService,
        protected  ClaimRepositoryInterface   $claimRepository,
    )
    {
        $this->middleware('auth');
        parent::__construct();
        $this->historyService = new HistoryService('claim_histories');
    }


    public function regulations(): JsonResponse
    {
        try {
            $query = $this->regulationService->getRegulations($this->user, $this->roleId);
            $filters = request()->only(['object_name', 'is_fined', 'start_date', 'end_date',  'regulation_number', 'task_id', 'created_by_role', 'region_id', 'district_id', 'organization_name', 'funding_source', 'category', 'status', 'lawyer_status', 'deadline_asked']);

            $regulations = $this->regulationService->searchRegulations($query, $filters)
                ->when(
                    !empty($filters['status']) && $filters['status'] == 6,
                    function ($query) {
                        $query->orderBy('updated_at', request('sort_by_date', 'desc'));
                    }
                )
                ->orderBy(
                    request('sort_by_date') ? 'created_at' : 'deadline',
                    request('sort_by_date') ? 'DESC' : 'ASC'
                )
                ->paginate(request('per_page', 10));

            return $this->sendSuccess(
                RegulationListResource::collection($regulations),
                'Regulations',
                pagination($regulations)
            );

        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function lawyerCount(): JsonResponse
    {
        try {
            $query =  $this->regulationService->getRegulations($this->user, $this->roleId);
            $data = [
                'all' => $query->clone()->count(),
                'new' => $query->clone()->where('lawyer_status_id', LawyerStatusEnum::NEW)->count(),
                'process' => $query->clone()->where('lawyer_status_id', LawyerStatusEnum::PROCESS)->count(),
                'administrative' => $query->clone()->where('lawyer_status_id', LawyerStatusEnum::ADMINISTRATIVE)->count(),
                'disassembly' => $query->clone()->where('lawyer_status_id', LawyerStatusEnum::DISASSEMBLY)->count(),
            ];
            return $this->sendSuccess($data, 'Lawyer Count');
        }catch (\Exception $exception){
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function regulationCount(): JsonResponse
    {
        try {
            return $this->sendSuccess($this->regulationService->regulationCountByStatus($this->user, $this->roleId), 'Regulation Count');
        } catch (\Exception $exception){
            return $this->sendError($exception->getMessage());
        }
    }

    public function getAuthorRegulations(): JsonResponse
    {
        try {
            $authorRegulations = AuthorRegulation::query()->where('object_id', request('object_id'))->orderBy('id', 'DESC')->paginate(request('per_page', 30));
            return $this->sendSuccess(AuthorRegulationResource::collection($authorRegulations), 'Regulations', pagination($authorRegulations));
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function getRegulation($id): JsonResponse
    {
        try {
            $regulation = $this->regulationService->getRegulationById($id);
            return $this->sendSuccess(new RegulationResource($regulation), 'Regulation found');
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }


    public function askDate(RegulationDemandRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            $user = Auth::user();
            $roleId = $user->getRoleFromToken();

            $regulation = Regulation::query()->findOrFaiL($request->post('regulation_id'));


            if ($regulation->deadline_asked) return $this->sendError('Muddat oldin  soralgan');

            $act = ActViolation::create([
                'regulation_id' => $regulation->id,
                'user_id' => Auth::id(),
                'act_status_id' => 10,
                'comment' => $request->comment,
                'role_id' => $roleId,
                'act_violation_type_id' => 3,
                'status' => ActViolation::PROGRESS,
            ]);


            $regulation->update([
                'deadline_asked' => true,
                'act_status_id' => 10
            ]);

            if ($regulation->created_by_role_id == UserRoleEnum::INSPECTOR->value)
            {
                $this->sendNotification($this->user, $this->roleId, $regulation);
            }

            DB::commit();
            return $this->sendSuccess([], 'Data saved successfully');

        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->sendError($exception->getMessage(), $exception->getCode());

        }
    }

    public function acceptDate(RegulationAcceptRequest $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $user = Auth::user();
            $roleId = $user->getRoleFromToken();

            $regulation = Regulation::query()->findOrFaiL($request->post('regulation_id'));

            if ($regulation->regulation_status_id == RegulationStatusEnum::IN_LAWYER) throw new Exception('Tasdiqlash imkoni yoq');
            if ($regulation->lawyer_status_id == RegulationStatusEnum::IN_LAWYER) throw new Exception('Tasdiqlash imkoni yoq');

            $act = ActViolation::create([
                'regulation_id' => $regulation->id,
                'user_id' => Auth::id(),
                'act_status_id' => 11,
                'comment' => $request->comment,
                'role_id' => $roleId,
                'act_violation_type_id' => 3,
                'status' => ActViolation::ACCEPTED,
            ]);

            $regulation->update([
                'act_status_id' => 11,
                'deadline' => Carbon::parse($request->post('deadline'))->endOfDay(),
            ]);

            DB::commit();
            return $this->sendSuccess([], 'Data saved successfully');

        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function rejectDate(RegulationDemandRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            $regulation = Regulation::query()->findOrFaiL($request->post('regulation_id'));
            if ($regulation->regulation_status_id == RegulationStatusEnum::IN_LAWYER) throw new Exception('Tasdiqlash imkoni yoq');

            $user = Auth::user();
            $roleId = $user->getRoleFromToken();


            $act = ActViolation::create([
                'regulation_id' => $regulation->id,
                'user_id' => Auth::id(),
                'act_status_id' => 12,
                'comment' => $request->comment,
                'role_id' => $roleId,
                'act_violation_type_id' => 3,
                'status' => ActViolation::REJECTED,
            ]);


            $regulation->update([
                'act_status_id' => 12,
            ]);

            DB::commit();
            return $this->sendSuccess([], 'Data saved successfully');

        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function regulationChange(RegulationEventRequest $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $regulation = Regulation::query()->findOrFaiL($request->regulation_id);

            $event = new RegulationEvent();
            $event->regulation_id = $regulation->id;
            $event->status = $request->status;
            $event->comment = $request->comment;
            $event->user_id = $this->user->id;
            $event->role_id = $this->roleId;
            $event->save();

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $path = $image->store('images/regulation-event', 'public');
                    $event->images()->create(['url' => $path]);
                }
            }

            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $document) {
                    $path = $document->store('document/regulation-event', 'public');
                    $event->documents()->create(['url' => $path]);
                }
            }

            $updateData = [
                'regulation_status_id' => request('status'),
            ];

            if ($regulation->lawyer_status_id != 3) {
                $updateData['lawyer_status_id'] = null;
            }

            $control = AiControl::query()->find($regulation->control_id);

            if ($control) {
                $control->update(['send_operator' => null]);
            }

            $regulation->update($updateData);


            DB::commit();
            return $this->sendSuccess([], 'Data saved successfully');
        }catch (\Exception $exception) {
            DB::rollBack();
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function acceptAnswer(): JsonResponse
    {
        try {
            $dto = new RegulationDto();

            $dto->setRegulationId(request('regulation_id'))
                ->setComment(request('comment'));

            $this->regulationService->acceptAnswer($dto);

            return $this->sendSuccess([], "Data saved successfully");
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function acceptDeed(): JsonResponse
    {
        try {
            $dto = new RegulationDto();

            $dto->setRegulationId(request('regulation_id'))
                ->setComment(request('comment'));

            $this->regulationService->acceptDeed($dto);

            return $this->sendSuccess([], 'Data saved successfully');
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function acceptDeedCmr(): JsonResponse
    {
        try {
            $dto = new RegulationDto();

            $dto->setRegulationId(request('regulation_id'))
                ->setComment(request('comment'));

            $this->regulationService->acceptDeedCmr($dto);

            return $this->sendSuccess([], 'Data saved successfully');
        } catch (\Exception $exception) {
            return $this->sendError('xatolik aniqlandi', $exception->getCode());
        }
    }

    public function sendAnswerAuthorRegulation(): JsonResponse
    {
        $data = request()->all();
        try {
            foreach ($data['journal'] as $item) {
                $files = [];
                $regulation = AuthorRegulation::query()->findOrFaiL($item['regulation_id']);
                if (isset($item['files'])) {
                    foreach ($item['files'] as $file) {
                        $path = $file->store('images/author-regulation', 'public');
                        $files[] = $path;
                    }
                }
                $regulation->update([
                    'comment' => $item['comment'],
                    'images' => json_encode($files),
                ]);
            }


            return $this->sendSuccess([], 'Data saved successfully');

        } catch (\Exception $exception) {
            return $this->sendError('xatolik aniqlandi', $exception->getCode());
        }
    }

    public function authorReport()
    {
        $filters = request()->only(['object_id', 'region_id',  'district_id']);
        return Excel::download(
            new AuthorRegulationsExport($filters),
            'muallif_nazorati.xlsx'
        );
    }


    public function rejectAnswer(): JsonResponse
    {
        try {
            $dto = new RegulationDto();
            $dto->setRegulationId(request('regulation_id'))
                ->setComment(request('comment'));


            $this->regulationService->rejectToAnswer($dto);

            return $this->sendSuccess([], 'Data saved successfully');
        } catch (\Exception $exception) {
            return $this->sendError('xatolik aniqlandi', $exception->getCode());
        }

    }


    public function sendDeed(): JsonResponse
    {
        try {
            $dto = new RegulationDto();
            $dto->setRegulationId(request('regulation_id'))
                ->setMeta(request('violations'));


            $this->regulationService->sendToDeed($dto);

            return $this->sendSuccess([], 'Data saved successfully');
        } catch (\Exception $exception) {
            return $this->sendError('xatolik aniqlandi', $exception->getCode());
        }
    }

    public function rejectDeed(): JsonResponse
    {
        try {
            $dto = new RegulationDto();
            $dto->setRegulationId(request('regulation_id'))
                ->setComment(request('comment'));

            $this->regulationService->rejectDeed($dto);

            return $this->sendSuccess([], 'Data saved successfully');
        } catch (\Exception $exception) {
            return $this->sendError('xatolik aniqlandi', $exception->getCode());
        }
    }

    public function rejectDeedCmr(): JsonResponse
    {
        try {
            $dto = new RegulationDto();
            $dto->setRegulationId(request('regulation_id'))
                ->setComment(request('comment'));

            $this->regulationService->rejectDeedCmr($dto);

            return $this->sendSuccess([], 'Data saved successfully');
        } catch (\Exception $exception) {
            return $this->sendError('xatolik aniqlandi', $exception->getCode());
        }
    }


    public function fine(RegulationFineRequest $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $regulation = Regulation::query()->findOrFaiL($request->regulation_id);

            $fine = new RegulationFine();
            $fine->regulation_id = $regulation->id;
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
                foreach ($request->file('images') as $image) {
                    $path = $image->store('images/hidden-works', 'public');

                    $fine->images()->create([
                        'url' => $path,
                    ]);
                }
            }

            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $document) {
                    $path = $document->store('document/fines', 'public');
                    $fine->documents()->create(['url' => $path]);
                }
            }

            $demand = RegulationDemand::query()->where('regulation_id', $regulation->id)->latest()->first();

            if (!$demand) {
                $status = 1;
            } elseif ($demand->act_violation_type_id = 1) {
                $status = 1;
            } elseif ($demand->act_violation_type_id = 2) {
                $status = 3;
            } else {
                $status = 1;
            }

            $regulation->update([
                'deadline' => null,
                'lawyer_status_id' => LawyerStatusEnum::ADMINISTRATIVE,
                'regulation_status_id' => $status,
            ]);

            $control = AiControl::query()->find($regulation->control_id);

            if ($control) {
                $control->update(['send_operator' => null]);
            }

            DB::commit();
            return $this->sendSuccess([], 'Data saved successfully');
        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->sendError('xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function sendCourt(): JsonResponse
    {
        try {
            $regulation = Regulation::query()->findOrFaiL(request('regulation_id'));

            $regulation->update([
                'lawyer_status_type' => request('type'),
                'lawyer_status_id' => LawyerStatusEnum::PROCESS
            ]);

            return $this->sendSuccess([], 'Data saved successfully');

        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    private function sendNotification($user, $roleId, $regulation)
    {
        try {
            $inspector = User::query()->find($regulation->created_by_role_id);
            $role = Role::query()->find($roleId);
            $data = [
                'screen' => 'confirm_regulations'
            ];
            $message = MessageTemplate::askDate($user->full_name, $regulation->object->task_id, $regulation->regulation_number, $role->name, now());
            $inspector->notify(new InspectorNotification(title: "Yozma ko'rsatmaga muddat uzaytirish so'raldi", message: $message, url: null, additionalInfo: $data));

        } catch (\Exception $exception) {

        }
    }

    public function createRegulation()
    {
        DB::beginTransaction();
        try {
            foreach (request()->file('images') as $image) {
                $path = $image->store('images/author-regulation', 'public');
                $meta[] = $path;
            }
            $object = Article::query()->findOrFail(request('object_id'));

            AuthorRegulation::query()->create([
                'object_id' => $object->id,
                'author_comment' => request('comment'),
                'author_id' => Auth::id(),
                'block_id' => request('block_id'),
                'deadline' => request('deadline'),
                'author_role_id' => UserRoleEnum::MUALLIF->value,
                'author_images' => json_encode($meta),
                'user_id' => $object->users()->wherePivot('role_id', UserRoleEnum::ICHKI->value)->pluck('users.id')->first(),
                'role_id' => UserRoleEnum::ICHKI->value,
            ]);
            DB::commit();
            return $this->sendSuccess([], 'Data saved successfully');

        }catch (Exception $exception){
            DB::rollBack();
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }


    public function test(Request $request)
    {

        try {
            dd([
                'max_file_uploads' => ini_get('max_file_uploads'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'memory_limit' => ini_get('memory_limit'),
                'max_input_time' => ini_get('max_input_time'),
                'max_execution_time' => ini_get('max_execution_time'),
            ]);

//            $authUsername = config('app.mygov.login');
//            $authPassword = config('app.mygov.password');
//            $action = request('action');
//            $taskId = request('taskId');
//            $formName = request('formName');
//            $notice = request('notice');
//            $theme = request('theme');
//
//
//            $apiUrl = config('app.mygov.url') . '/update/id/' . $taskId . '/action/'. $action;
//
//            $return =  Http::withBasicAuth($authUsername, $authPassword)
//                ->post($apiUrl, [
//                    $formName => [
//                        $theme => $notice,
//                    ]
//                ]);
//
//            return $return->json();

        }catch (\Exception $exception){
            return $exception->getMessage();
        }
//        Redis::set(
//            'regions',
//            Region::select('id', 'name_uz', 'name_ru', 'soato')
//                ->orderBy('id')
//                ->get()
//                ->toJson()
//        );
//
//        Region::query()->each(function ($region) {
//            Redis::set(
//                "districts:{$region->id}",
//                District::where('region_id', $region->id)
//                    ->select('id', 'name_uz', 'name_ru','soato', 'region_id')
//                    ->orderBy('id')
//                    ->get()
//                    ->toJson()
//            );
//        });
//        $regions = json_decode(Redis::get('regions'), true);
//        $sphere = json_decode(Redis::get('spheres'), true);
//        dd($sphere);
//
//
//        $data = $this->imzoService->signTimestamp($request->pkcs7);
//
//
//        if (empty($data)) throw new \Exception('E-imzo malumot olishda xatolik yuz berdi');
//
//        $director = $this->imzoService->checkDirector($data, false);
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

    private function autoRejectByOrganization($claim): bool
    {
        if ($claim->current_node == 'answer-other-institutions') {
            $dataArray['SendToStepConclusionGasnV2FormCompletedBuildingsRegistrationCadastral'] = [
                'comment_gasn' => 'Ariza tashkilotlar tomonidan ijobiy xulosa taqdim etilmaganligi sababli rad etildi.',
            ];

            $response = $this->PostRequest("update/id/" . $claim->guid . "/action/send-to-step-conclusion-gasn", $dataArray);

            telegramNotification('Foydalanishga topshirish ariza auto rad qilindi mygov javob', $claim->guid, $response->json());


            if ($response->status() != 200) {
                return false;
            }
        }

        $dataArray['IssuanceExtractRejectGasnV2FormCompletedBuildingsRegistrationCadastral'] = [
            "gasn_name_reject" => 'Shaffof qurilish milliy axborot tizimi',
            "gasn_match" => 2,
            "gasn_cause_reject" => 'Ariza tashkilotlar tomonidan ijobiy xulosa taqdim etilmaganligi sababli rad etildi.',
            "gasn_territory_reject" => $claim->region()->first()->name_uz
        ];

        $response = $this->PostRequest("update/id/" . $claim->guid . "/action/issuance-extract-reject-gasn", $dataArray);

        if ($response->status() != 200) {
            return false;
        }

        $claim->update(
            [
                'status' => ClaimStatuses::TASK_STATUS_ORGANIZATION_REJECTED,
                'end_date' => \Illuminate\Support\Carbon::now()->format('Y-m-d H:i:s')
            ]
        );

        return true;
    }

    public function lawyerRegulationExport(Request $request)
    {
        $region = $request->region;

        $query = DB::table('regulations as reg')
            ->leftJoin('articles as a', 'reg.object_id', '=', 'a.id')
            ->leftJoin('regions as r', 'a.region_id', '=', 'r.id')
            ->leftJoin('districts as d', 'a.district_id', '=', 'd.id')
            ->leftJoin('article_users as au', 'a.id', '=', 'au.article_id')
            ->leftJoin('users as u', 'au.user_id', '=', 'u.id')
            ->leftJoin('users as ins', 'reg.created_by_user_id', '=', 'ins.id')
            ->leftJoin('users as usd', 'reg.user_id', '=', 'usd.id')
            ->leftJoin('roles as rol', 'reg.role_id', '=', 'rol.id')
            ->leftJoin('regulation_fines as rf', 'reg.id', '=', 'rf.regulation_id')
            ->leftJoin('regulation_violations as rv', 'reg.id', '=', 'rv.regulation_id')
            ->leftJoin('spheres as s', 'a.sphere_id', '=', 's.id')
            ->leftJoin('difficulty_categories as dif', 'a.difficulty_category_id', '=', 'dif.id')
            ->leftJoin('funding_sources as fun', 'a.funding_source_id', '=', 'fun.id')
            ->where('reg.lawyer_status_id', 3)
            ->select([
                'r.name_uz as viloyat',
                'd.name_uz as tuman',
                'a.name as object_name',

                DB::raw("
                COALESCE(
                    NULLIF(a.task_id::text, ''),
                    NULLIF(a.manual_task_id::text, '')
                ) AS task_id
            "),

                DB::raw("
                CONCAT_WS(
                    ' ',
                    ins.surname,
                    ins.name,
                    ins.middle_name
                ) AS inspektor
            "),

                DB::raw("
                CONCAT_WS(
                    ' ',
                    usd.surname,
                    usd.name,
                    usd.middle_name
                ) AS korsatma_berilgan_shaxs
            "),

                'rol.name as lovozim',
                'reg.regulation_number as korsatma_raqami',
                'reg.created_at as korsatma_sanasi',

                DB::raw("
                CASE
                    WHEN reg.regulation_status_id IN (6, 8)
                    THEN reg.updated_at
                    ELSE NULL
                END AS bartaraf_qilingan_sana
            "),

                DB::raw("
                MAX(
                    CASE
                        WHEN au.role_id = 8
                        THEN u.organization_name
                    END
                ) AS buyurtmachi
            "),

                DB::raw("
                MAX(
                    CASE
                        WHEN au.role_id = 8
                        THEN u.pinfl
                    END
                ) AS buyurtmachi_inn
            "),

                's.name_uz as soha',
                'dif.difficulty as murakkablik_toifasi',

                'rf.full_name as mamuriy_qilingan_shaxs',
                'rf.pinfl as jshir',
                'rf.decision_series as qaror_seriyasi',
                'rf.decision_number as qaror_raqami',

                DB::raw('COUNT(DISTINCT rv.id) as qoidabuzarlik_soni'),
            ])
            ->when($region, function ($query, $region) {
                return $query->where('a.region_id', $region);
            })
            ->groupBy([
                'reg.id',
                'a.id',
                'r.name_uz',
                'd.name_uz',
                'ins.id',
                'usd.id',
                'rol.id',
                's.id',
                'dif.id',
                'rf.id',
            ])
            ->orderByDesc('reg.id');

        return (new FastExcel(
            $query->cursor()
        ))->download(
            'lawyer_regulations_' . now()->format('Y_m_d_H_i_s') . '.xlsx',
            function ($row) {
                return [
                    'Viloyat' => $row->viloyat,
                    'Tuman' => $row->tuman,
                    'Obyekt nomi' => $row->object_name,
                    'Task ID' => $row->task_id,
                    'Inspektor' => $row->inspektor,
                    'Ko‘rsatma berilgan shaxs' => $row->korsatma_berilgan_shaxs,
                    'Lavozim' => $row->lovozim,
                    'Ko‘rsatma raqami' => $row->korsatma_raqami,
                    'Ko‘rsatma sanasi' => $row->korsatma_sanasi,
                    'Bartaraf qilingan sana' => $row->bartaraf_qilingan_sana,
                    'Buyurtmachi' => $row->buyurtmachi,
                    'Buyurtmachi JSHSHIR' => $row->buyurtmachi_inn,
                    'Soha' => $row->soha,
                    'Murakkablik toifasi' => $row->murakkablik_toifasi,
                    'Ma’muriy qilingan shaxs' => $row->mamuriy_qilingan_shaxs,
                    'JSHSHIR' => $row->jshir,
                    'Qaror seriyasi' => $row->qaror_seriyasi,
                    'Qaror raqami' => $row->qaror_raqami,
                    'Qoidabuzarlik soni' => $row->qoidabuzarlik_soni,
                ];
            }
        );
    }

    private function PostRequest($url, $data)
    {
        $response = Http::withBasicAuth(config('app.mygov.login'), config('app.mygov.password'))->post('https://oldmy.gov.uz/completed-buildings-registration-cadastral-v2/rest-api/' . $url, $data);

        return $response;
    }



}
