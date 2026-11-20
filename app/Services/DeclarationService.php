<?php

namespace App\Services;

use App\Enums\LogType;
use App\Enums\UserRoleEnum;
use App\Enums\UserStatusEnum;
use App\Http\Requests\ConsumerChangeRequest;
use App\Http\Requests\DeclarationAcceptRequest;
use App\Http\Requests\DeclarationConclusionRequest;
use App\Http\Requests\DeclarationCreateRequest;
use App\Http\Requests\DeclarationUpdateRequest;
use App\Models\Article;
use App\Models\ArticleHistory;
use App\Models\ArticleUser;
use App\Models\DeclarationConclusion;
use App\Models\DocumentSignature;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Interfaces\ArticleRepositoryInterface;
use App\Repositories\Interfaces\DeclarationInterface;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class DeclarationService
{
    private HistoryService $history;

    public function __construct(
        protected DeclarationInterface $repository,
        protected EimzoService         $imzoService,
        protected ShaffofIdService      $shaffofIdService,
    )
    {
        $this->history = new HistoryService('declaration_histories');
    }

    public function all($filters, $user, $roleId)
    {
        return $this->repository->all();
    }

    public function create($user, $roleId, DeclarationCreateRequest $request)
    {
        DB::beginTransaction();
        try {
            $model = $this->repository->create($request->validated());
            $this->saveHistory($model, $user, $roleId, LogType::DECLARATION_CREATED);
            DB::commit();
            return $model;
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }


    }

    public function findById($id)
    {
        return $this->repository->findById($id);
    }


    public function accept($user, $roleId, $id, DeclarationAcceptRequest $request)
    {
        DB::beginTransaction();
        try {
            $model = $this->repository->change($user, $roleId, $id, 1);

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

            $shaffofData = $this->shaffofIdService->getInfo($document->uuid);

            $directorFullName = $shaffofData->signature->signer_name ?? null;

            switch ($roleId) {
                case UserRoleEnum::BUYURTMACHI->value:
                    $consumer = $request->user ? $this->createConsumer($model, $request->user) : null;
                    $model->update([
                        'customer_confirmed_at' => now(),
                        'consumer_id' => $consumer ? $consumer->id : null,
                    ]);
                    if ($consumer) {
                        $consumerData = [
                            'consumer' => [
                                'id' => $consumer->id,
                                'inn' => $consumer->pinfl,
                                'name' => $consumer->organization_name,
                            ],
                        ];

                        $this->saveHistory(
                            $model,
                            $user,
                            $roleId,
                            LogType::DECLARATION_CONSUMER,
                            '',
                            $directorFullName,
                            $document->uuid,
                            $consumerData
                        );

                    }
                    $this->saveHistory($model, $user, $roleId, LogType::DECLARATION_CONFIRMED, '', $directorFullName, $document->uuid);

                    break;
                case UserRoleEnum::QURILISH->value:
                    $model->update([
                        'vendor_comment' => $request->vendor_comment,
                        'vendor_full_name' => $directorFullName,
                        'vendor_confirmed_at' => now()
                    ]);
                    $this->saveHistory($model, $user, $roleId, LogType::DECLARATION_CUSTOMER, $request->vendor_comment, $directorFullName, $document->uuid);
                    break;
                default:
                    break;
            }
            DB::commit();
            return $model;
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }


    private function saveHistory($model, $user, $roleId, $action, $comment = '', $username = null, $pkcs7 = null, ?array $addInfo = null)
    {
        return $this->history->createHistory(
            guId: $model->id,
            status: $model->status,
            type: $action,
            date: null,
            comment: $comment,
            additionalInfo: array_merge([
                'user_id' => $user->id,
                'role_id' => $roleId,
                'director_name' => $username,
                'pkcs7b64' => $pkcs7,
            ], $addInfo ?? [])
        );
    }

    public function reject($user, $roleId, $id)
    {
        DB::beginTransaction();
        try {
            $model = $this->repository->change($user, $roleId, $id, 0);
            $comment = request('comment') ?? null;
            $this->saveHistory($model, $user, $roleId, LogType::DECLARATION_REJECTED, $comment);
            DB::commit();
            return $model;
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    public function update($user, $roleId, $id, DeclarationUpdateRequest $request)
    {
        DB::beginTransaction();
        try {
            $model = $this->repository->update($id, $request->validated());
            $this->saveHistory($model, $user, $roleId, LogType::DECLARATION_UPDATED);
            DB::commit();
            return $model;
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }

    }

    public function getHistory($id)
    {
        $model = $this->repository->findById($id);
        return $model->histories->map(function ($history) {
            return [
                'id' => $history->id,
                'user' => $history->content->user ? User::query()->find($history->content->user, ['name', 'surname', 'middle_name', 'organization_name']) : null,
                'role' => $history->content->role ? Role::query()->find($history->content->role, ['name', 'description']) : null,
                'status' => $history?->content?->status,
                'comment' => $history->content->comment,
                'director_name' => $history->content->additionalInfo->director_name ?? null,
                'consumer' => $history->content->additionalInfo->consumer ?? null,
                'type' => $history->type,
                'is_change' => $history->type ? LogType::getDeclarationLabel($history->type) : null,
                'created_at' => $history->created_at,
            ];
        })->sortBy('created_at')->values();
    }


    public function concCreate($data)
    {
        return $this->repository->concCreate($data);
    }

    public function conclusion($user, $roleId, $filters)
    {
        return $this->repository->getConc($user, $roleId, $filters);
    }

    public function concPdf($user, $roleId, $id)
    {
        $conclusion = DeclarationConclusion::query()->find($id);

        if (!$conclusion) return null;

        $vendor = $conclusion->object->users()->where('role_id', UserRoleEnum::QURILISH->value)->first();
        $organization = $conclusion->object->users()->where('role_id', UserRoleEnum::BUYURTMACHI->value)->first();
        $designer = $conclusion->object->users()->where('role_id', UserRoleEnum::LOYIHA->value)->first();
        $internal = $conclusion->object->users()->where('role_id', UserRoleEnum::ICHKI->value)->first();
        $domain = URL::to('/claimgov/conclusion/') . '/' . $id;
        $qrImage = base64_encode(QrCode::format('png')->size(200)->generate($domain));

        $pdf = Pdf::loadView(
            'pdf.' . ($conclusion->role_id == 6 ? 'technic' : 'author') . '-conclusion',
            compact('conclusion', 'vendor', 'organization', 'internal', 'designer', 'qrImage')
        );

        $pdfContent = $pdf->output();
        return base64_encode($pdfContent);
    }


    public function pdfBase64($id)
    {
        $declaration = $this->repository->findById($id);
        $vendor = $declaration->object->users()->where('role_id', UserRoleEnum::QURILISH->value)->first();
        $designer = $declaration->object->users()->where('role_id', UserRoleEnum::LOYIHA->value)->first();
        $internal = $declaration->object->users()->where('role_id', UserRoleEnum::ICHKI->value)->first();
        $organization = $declaration->object->users()->where('role_id', UserRoleEnum::BUYURTMACHI->value)->first();
        $domain = URL::to('/declaration-pdf') . '/' . $id;
        $qrImage = base64_encode(QrCode::format('png')->size(200)->generate($domain));
        $pdf = Pdf::loadView('pdf.declaration', compact('declaration', 'qrImage', 'vendor', 'designer', 'internal', 'organization'));
        $pdfContent = $pdf->output();
        return base64_encode($pdfContent);
    }

    public function changeConsumer($user, $roleId, ConsumerChangeRequest $request)
    {
        try {
            $data = $this->imzoService->signTimestamp($request->pkcs7);
            $is_yuridik = !empty($data['inn']);

            if (empty($data)) throw new \Exception('E-imzo malumot olishda xatolik yuz berdi');

            $director = $this->imzoService->checkDirector($data, $is_yuridik);

            if ($director['inn'] != $user->pinfl) {
                throw new \Exception('E-imzo egasi mos kelmadi');
            }

            $arr = [
                'pin' => $request->inn,
                'organization_name' => $request->organization_name,
            ];

            $declaration = $this->repository->findById($request->declaration_id);

            $consumer = $this->createConsumer($declaration, $arr);

            if (!$consumer) throw new \Exception('Foydalanuchi qo\'shishda muammo');

            $consumerData = [
                'consumer' => [
                    'id' => $consumer->id,
                    'inn' => $consumer->pinfl,
                    'name' => $consumer->organization_name,
                ],
            ];

            $this->saveHistory(
                $declaration,
                $user,
                $roleId,
                LogType::DECLARATION_CONSUMER_CHANGE,
                $request->comment,
                $director['full_name'],
                $data['pkcs7b64'],
                $consumerData
            );

            $object = Article::query()->findOrFail($request->object_id);

            $objectHistory = new HistoryService('article_histories');

            $meta = [
                'pkcs7'    =>  $data['pkcs7b64'],
                'consumer' => $consumerData,
            ];

            $historyId = $objectHistory->createHistory(
                guId: $object->id,
                status: $object->object_status_id->value,
                type: LogType::CHANGE_CONSUMER,
                date: null,
                additionalInfo: $meta
            );

            $history =  ArticleHistory::query()->find($historyId);

            foreach ($request['files'] as $doc) {
                $path = $doc->store('document/object-history', 'public');
                $history->documents()->create(['url' => $path]);
            }
        } catch (\Exception $exception) {
            throw $exception;
        }
    }


    private function createConsumer($model, $data)
    {
        return DB::transaction(function () use ($model, $data) {

            $user = User::firstOrCreate(
                ['pinfl' => $data['pin']],
                [
                    'identification_number' => $data['pin'],
                    'name' => $data['name'] ?? null,
                    'middle_name' => $data['middle_name'] ?? null,
                    'surname' => $data['surname'] ?? null,
                    'organization_name' => $data['organization_name'] ?? null,
                    'password' => Hash::make($data['pin']),
                    'region_id' => $data['region'] ?? null,
                    'district_id' => $data['district'] ?? null,
                    'login' => $data['passport'] ?? $data['pin'] ?? null,
                    'user_status_id' => UserStatusEnum::ACTIVE->value,
                    'active' => 1
                ]
            );

            $user->roles()->syncWithoutDetaching([
                UserRoleEnum::FOYDALANUVCHI->value
            ]);

            ArticleUser::where('article_id', $model->object_id)
                ->where('role_id', UserRoleEnum::FOYDALANUVCHI->value)
                ->delete();

            ArticleUser::create([
                'article_id' => $model->object_id,
                'user_id' => $user->id,
                'role_id' => UserRoleEnum::FOYDALANUVCHI->value,
            ]);

            return $user;
        });
    }


}
