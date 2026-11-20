<?php

namespace App\Services;

use App\Enums\LogType;
use App\Enums\RegulationStatusEnum;
use App\Enums\UserRoleEnum;
use App\Helpers\PresentationStatus;
use App\Http\Requests\PresentationRequest;
use App\Models\Regulation;
use App\Models\Role;
use App\Models\User;
use App\Models\Violation;
use App\Repositories\Interfaces\PresentationInterface;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PresentationService
{

    private HistoryService $history;


    public function __construct(
        protected PresentationInterface $repository,
        protected QuestionService       $questionService,
        protected ArticleService        $articleService,
    )
    {
        $this->history = new HistoryService('presentation_histories');
    }

    public function all($filters, $user, $roleId)
    {
        $query = $this->repository->all($filters, $user, $roleId);
        return $this->repository->search($query, $filters);
    }

    public function findById($id)
    {
        return $this->repository->findById($id);
    }

    public function create(PresentationRequest $request, $user, $roleId)
    {
        return DB::transaction(function () use ($request, $user, $roleId) {
            $presentation = $this->repository->create(
                $request->except([
                    'images',
                    'certificate_files',
                    'presentation_files',
                    'report_files',
                    'deadline',
                    'block_id',
                ])
            );

            $images = $this->storeImages($request);

            if ($images) {
                $presentation->images()->createMany($images);
            }

            foreach ([
                         'certificate_files',
                         'presentation_files',
                         'report_files',
                     ] as $column) {
                if ($request->hasFile($column)) {
                    $this->saveFiles(
                        $presentation,
                        $request->file($column),
                        $column
                    );
                }
            }

            $this->saveHistory(
                $presentation,
                $user,
                $roleId,
                LogType::PRESENTATION_CREATE,
                status: 1
            );

            $monitoring = $this->questionService->createMonitoring(
                [
                    'block_id' => $request->block_id,
                    'work_in_progress' => null,
                ],
                $presentation->object,
                $roleId
            );

            $violation = $this->createViolation($presentation);

            if ($images) {
                $violation->images()->createMany($images);
            }

            $object = $presentation->object;

            foreach ([
                         UserRoleEnum::TEXNIK->value,
                         UserRoleEnum::ICHKI->value,
                     ] as $regulationRoleId) {
                $regulation = $this->createRegulation(
                    object: $object,
                    presentationId: $presentation->id,
                    monitoringId: $monitoring->id,
                    roleId: $regulationRoleId,
                    deadline: $request->deadline
                );

                $regulation->violations()->attach($violation->id);
                $this->questionService->createRegulationUser($regulation);
                $this->questionService->sendSms($regulation, $object->task_id);
                $this->smsPresentation($presentation, $object, $regulationRoleId);
            }

            return $presentation;
        });
    }

    public function smsPresentation($presentation, $object, $roleId)
    {
        try {

                $user = $object->users()
                    ->wherePivot('role_id', $roleId)
                    ->first();

                $message = MessageTemplate::presentationCreated($presentation->number, $object->task_id);

                (new SmsService($user->phone, $message))->sendSms();

        } catch (\Exception $exception) {

        }
    }

    private function storeImages(PresentationRequest $request): array
    {
        if (!$request->hasFile('images')) {
            return [];
        }

        return collect($request->file('images'))
            ->map(fn($file) => [
                'url' => $file->store(
                    'presentation/images',
                    'public'
                ),
            ])
            ->all();
    }

    private function createViolation($presentation)
    {
        return Violation::create([
            'title' => 'Taqdimnoma',
            'description' => 'Qurilish obyektida aniqlangan sifatsiz qurilish materiallarini ishlatish taqiqlanadi hamda mahsulotlarni qurilish obyektidan chiqarib yuborishingiz so\'raladi.',
            'comment' => $presentation->defect,
        ]);
    }

    private function createRegulation(
        $object,
        int $presentationId,
        int $monitoringId,
        int $roleId,
        int $deadline
    )
    {
        $userId = $object->users()
            ->wherePivot('role_id', $roleId)
            ->value('users.id');
        $role = Role::query()->find($roleId);

        if (!$userId) {
            throw new \Exception(
                "{$role->name} uchun foydalanuvchi topilmadi."
            );
        }

        $lastNumber = DB::table('regulations')
            ->whereRaw("regulation_number ~ '^\\d+$'")
            ->orderByRaw('CAST(regulation_number AS BIGINT) DESC')
            ->lockForUpdate()
            ->value('regulation_number');

        $newNumber = $lastNumber
            ? (int)$lastNumber + 1
            : 299999;

        return Regulation::create([
            'object_id' => $object->id,
            'deadline' => now()->addDays($deadline)->endOfDay(),
            'regulation_status_id' => RegulationStatusEnum::PROVIDE_REMEDY,
            'regulation_number' => $newNumber,
            'regulation_type_id' => 1,
            'created_by_role_id' => UserRoleEnum::INSPECTOR->value,
            'created_by_user_id' => Auth::id(),
            'user_id' => $userId,
            'monitoring_id' => $monitoringId,
            'role_id' => $roleId,
            'presentation_id' => $presentationId,
        ]);
    }


    public function accept($id, $user, $roleId)
    {
        DB::beginTransaction();
        try {
            $model = $this->repository->change($id, $user, $roleId);
            switch ($roleId) {
                case UserRoleEnum::ICHKI->value:
                    $this->saveHistory($model, $user, $roleId, LogType::PRESENTATION_UPDATE_INTERNAL, status: 1);
                    break;
                case UserRoleEnum::TEXNIK->value:
                    $this->saveHistory($model, $user, $roleId, LogType::PRESENTATION_UPDATE_TECHNIC, status: 1);
                    break;
                case UserRoleEnum::INSPECTOR->value:
                    $this->saveHistory($model, $user, $roleId, LogType::PRESENTATION_UPDATE_INSPECTOR, status: 1);
                    break;
                case UserRoleEnum::INSPEKSIYA_XODIMI->value:
                    $this->saveHistory($model, $user, $roleId, LogType::PRESENTATION_UPDATE_EMPLOYEE, status: 1);
                    break;
                case UserRoleEnum::INSPEKSIYA->value:
                    list($correlationId, $statusCode) = $this->sendEgov($model);
                    if ($statusCode == 202 || $statusCode == 200 || $statusCode == 201)
                        $model->update(['egov_id' => $correlationId]);
                    $this->saveHistory($model, $user, $roleId, LogType::PRESENTATION_UPDATE_INSPECTION, status: 1);
                    break;
                default:
                    break;
            }
            DB::commit();
            return $model;
        } catch (\Exception $exception) {
            DB::rollBack();
            throw  $exception;
        }
    }

    public function sendInspector($user, $roleId)
    {
        try {
            $model = $this->repository->update(\request('id'), ['status' => PresentationStatus::IN_INSPECTOR]);
            $this->saveHistory($model, $user, $roleId, LogType::PRESENTATION_SEND_INSPECTOR, comment: \request('comment'), status: 4);
            return $model;
        } catch (\Exception $exception) {
            throw  $exception;
        }
    }

    public function sendEgov($model)
    {

        $data = [
            'UUID' => $model->id,
            'material_type' => $model->material_name,
            'manufacturer_tin' => $model->producer_tin,
            'manufacturer_name' => $model->producer_name,
            'object_name' => $model?->object?->name,
            'region' => $model?->object?->region->soato,
            'district' => $model?->object?->district->soato,
            'adress' => $model?->object?->location_building,
            'defect_comment' => $model->defect,
            'documents' => [
                'submission' => url('/presentation-pdf/' . $model->id),
                'certificate_files' => $model->certificate_files ? collect(json_decode($model->certificate_files))->map(function ($file) {
                    return [
                        'url' => url('storage/' . $file),
                    ];
                }) : null,
                'presentation_files' => $model->presentation_files ? collect(json_decode($model->presentation_files))->map(function ($file) {
                    return [
                        'url' => url('storage/' . $file),
                    ];
                }) : null,
                'report_files' => $model->report_files ? collect(json_decode($model->report_files))->map(function ($file) {
                    return [
                        'url' => url('storage/' . $file),
                    ];
                }) : null,
                'image' => $model->images ? $model->images->map(function ($image) {
                    return ['url' => Storage::disk('public')->url($image->url)];
                }) : null,
            ],
        ];

        $correlationId = $this->gen_uuid();
        $requestData = [
//            "Request" => 'uzstandart',
            "correlationId" => $correlationId,
            "destinationSubscribers" => ["tris_uz"],
            "data" => $data
        ];

        $client = new Client();
        $apiCredentials = config('app.passport.login') . ':' . config('app.passport.password');
        $url = "https://api.shaffofqurilish.uz/api/v1/request/egov-push-object";

        $resClient = $client->post($url,
            [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($apiCredentials),
                ],
                'timeout' => 10,
                'connect_timeout' => 5,
                'json' => $requestData
            ]
        );

        return [$correlationId, $resClient->getStatusCode()];
    }

    public function reject($id, $user, $roleId)
    {
        DB::beginTransaction();
        try {
            $model = $this->repository->reject($id, $user, $roleId);
            switch ($roleId) {
                case UserRoleEnum::ICHKI->value:
                    $this->saveHistory($model, $user, $roleId, LogType::PRESENTATION_REJECT_INTERNAL, comment: \request('comment'), status: 2);
                    break;
                case UserRoleEnum::TEXNIK->value:
                    $this->saveHistory($model, $user, $roleId, LogType::PRESENTATION_REJECT_TECHNIC, comment: \request('comment'), status: 2);
                    break;
                case UserRoleEnum::INSPECTOR->value:
                    $this->saveHistory($model, $user, $roleId, LogType::PRESENTATION_REJECT_INSPECTOR, comment: \request('comment'), status: 2);
                    break;
                case UserRoleEnum::INSPEKSIYA->value:
                    $this->saveHistory($model, $user, $roleId, LogType::PRESENTATION_REJECT_INSPECTION, comment: \request('comment'), status: 2);
                    break;
                default:
                    break;
            }
            DB::commit();
            return $model;
        } catch (\Exception $exception) {
            DB::rollBack();
            throw  $exception;
        }
    }

    public function count($filters, $user, $roleId): array
    {
        $query = $this->all($filters, $user, $roleId);

        return [
            'all' => $query->clone()->count(),
            'confirm' => $query->clone()->where('status', PresentationStatus::CONFIRM)->count(),
            'in_inspector' => $query->clone()->where('status', PresentationStatus::IN_INSPECTOR)->count(),
            'in_employee' => $query->clone()->where('status', PresentationStatus::IN_EMPLOYEE)->count(),
            'in_cmr' => $query->clone()->where('status', PresentationStatus::IN_CMR)->count(),
            'sended' => $query->clone()->where('status', PresentationStatus::SENDED)->count(),
            'reprisal' => $query->clone()->where('status', PresentationStatus::REPRISAL)->count(),
            'cancelled' => $query->clone()->where('status', PresentationStatus::CANCELLED)->count(),

        ];
    }

    public function getHistory($id)
    {
        $model = $this->repository->findById($id);
        return $model->histories->map(function ($history) {
            return [
                'id' => $history->id,
                'user' => $history->content->user ? User::query()->find($history->content->user, ['name', 'surname', 'middle_name']) : null,
                'role' => $history->content->role ? Role::query()->find($history->content->role, ['name', 'description']) : null,
                'status' => $history->content->status,
                'comment' => $history->content->comment,
                'history_status' => $history->content->additionalInfo ? $history->content->additionalInfo->status : null,
                'type' => $history->type,
                'is_change' => $history->type ? LogType::getPresentationLabel($history->type) : null,
                'created_at' => $history->created_at,
            ];
        })->sortByDesc('created_at')->values();

    }

    private function saveHistory($model, $user, $roleId, $action, $comment = '', $status = null)
    {

        return $this->history->createHistory(
            guId: $model->id,
            status: $model->status,
            type: $action,
            date: null,
            comment: $comment,
            additionalInfo: [
                'user_id' => $user->id, 'role_id' => $roleId, 'status' => $status
            ]
        );
    }

    private function saveFiles($presentation, $files, $column)
    {
        $array = [];
        foreach ($files as $file) {
            $path = $file->store('presentation/files', 'public');
            $array[] = $path;
        }

        $presentation->update([
            $column => json_encode($array),
        ]);
    }

    private function gen_uuid()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),

            // 16 bits for "time_mid"
            mt_rand(0, 0xffff),

            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000,

            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000,

            // 48 bits for "node"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }


}
