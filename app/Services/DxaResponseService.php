<?php

namespace App\Services;

use App\Enums\DxaResponseStatusEnum;
use App\Enums\LawyerStatusEnum;
use App\Enums\LogType;
use App\Enums\UserRoleEnum;
use App\Exceptions\NotFoundException;
use App\Helpers\ClaimStatuses;
use App\Models\Block;
use App\Models\Claim;
use App\Models\DocumentSignature;
use App\Models\DxaResponse;
use App\Models\DxaResponseStatus;
use App\Models\MonitoringObject;
use App\Models\Rekvizit;
use App\Models\Role;
use App\Models\User;
use App\Notifications\InspectorNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DxaResponseService
{
    public array $data = [];

    private HistoryService $history;

    public function __construct(
        protected DxaResponse $dxaResponse,
        protected ShaffofIdService $shaffofIdService,
    )
    {
        $this->history = new HistoryService('dxa_response_histories');
    }

    public function getClaims($user, $roleId)
    {
        $response = Claim::query()
            ->join('regions', 'regions.soato', '=', 'claims.region');

        return match ($roleId) {
            (string)UserRoleEnum::INSPEKSIYA->value, (string)UserRoleEnum::HUDUDIY_KUZATUVCHI->value, (string)UserRoleEnum::OPERATOR->value, (string)UserRoleEnum::REGISTRATOR->value => $response
                ->when($user, function ($q) use ($user) {
                    $q->where('regions.id', $user->region_id);
                })->where('claims.status', '<>', ClaimStatuses::TASK_STATUS_ANOTHER),
            default => $response->where('claims.status', '<>', ClaimStatuses::TASK_STATUS_ANOTHER),
        };
    }

    public function getRegisters($user, $roleId, $type)
    {
        $response = $this->dxaResponse->with(
            'status', 'fundingSource', 'monitoring', 'sphere', 'program', 'administrativeStatus', 'documents', 'objectType', 'region', 'district', 'lawyerStatus', 'supervisors', 'rekvizit'
        )->where('notification_type', $type);
        switch ($roleId) {
            case UserRoleEnum::INSPECTOR->value:
                return $response
                    ->where('inspector_id', $user->id)
                    ->where('dxa_response_status_id', DxaResponseStatusEnum::SEND_INSPECTOR);
            case UserRoleEnum::INSPEKSIYA->value:
            case UserRoleEnum::HUDUDIY_KUZATUVCHI->value:
            case UserRoleEnum::REGISTRATOR->value:
            case UserRoleEnum::KOMPLAYNS->value:
                return $response
                    ->where('region_id', $user->region_id);
            case UserRoleEnum::RESPUBLIKA_KUZATUVCHI->value:
            case UserRoleEnum::RESPUBLIKA_KOMPLAYNS->value:
                return $response;
            case UserRoleEnum::YURIST->value:
                return $response->where('region_id', $user->region_id)
                    ->where('administrative_status_id', 6)
                    ->where('dxa_response_status_id', DxaResponseStatusEnum::REJECTED);
            case UserRoleEnum::RESPUBLIKA_YURIST->value:
                return $response
                    ->where('administrative_status_id', 6)
                    ->where('dxa_response_status_id', DxaResponseStatusEnum::REJECTED);
            default:
                return $this->dxaResponse->whereRaw('1 = 0');
        }
    }

    public function searchRegisters($query, $filters)
    {
        return $query
            ->when(isset($filters['task_id']), function ($query) use ($filters) {
                $query->searchByTaskId($filters['task_id']);
            })
            ->when(isset($filters['customer_name']), function ($query) use ($filters) {
                $query->searchByCustomerName($filters['customer_name']);
            })
            ->when(isset($filters['name']), function ($query) use ($filters) {
                $query->searchByName($filters['name']);
            })
            ->when(isset($filters['manual']), function ($query) use ($filters) {
                $query->whereNotNull('manual_task_id');
            })
            ->when(isset($filters['object_type']), function ($query) use ($filters) {
                $query->where('object_type_id', $filters['object_type']);
            })
            ->when(isset($filters['funding_source']), function ($query) use ($filters) {
                $query->where('funding_source_id', $filters['funding_source']);
            })
            ->when(isset($filters['status']), function ($query) use ($filters) {
                $query->where('dxa_response_status_id', $filters['status']);
            })
            ->when(isset($filters['sphere_id']), function ($query) use ($filters) {
                $query->where('sphere_id', $filters['sphere_id']);
            })
            ->when(isset($filters['district_id']), function ($query) use ($filters) {
                $query->where('district_id', $filters['district_id']);
            })
            ->when(isset($filters['inspector_id']), function ($query) use ($filters) {
                $query->where('inspector_id', $filters['inspector_id']);
            })
            ->when(isset($filters['region_id']), function ($query) use ($filters) {
                $query->where('region_id', $filters['region_id']);
            })

            ->when(isset($filters['district_soato']), function ($query) use ($filters) {
                $query->whereHas('district', function ($q) use ($filters) {
                    $q->where('soato', $filters['district_soato']);
                });
            })

            ->when(isset($filters['in_compliance']), function ($query) use ($filters) {
                $query->where('in_compliance', true);
            })
            ->when(isset($filters['region_soato']), function ($query) use ($filters) {
                $query->whereHas('region', function ($q) use ($filters) {
                    $q->where('soato', $filters['region_soato']);
                });
            })
            ->when(isset($filters['start_date']) || isset($filters['end_date']), function ($query) use ($filters) {
                $startDate = isset($filters['start_date']) ? $filters['start_date'] . ' 00:00:00' : null;
                $endDate = isset($filters['end_date']) ? $filters['end_date'] . ' 23:59:59' : null;

                if ($startDate && $endDate) {
                    $query->whereBetween('created_at', [$startDate, $endDate]);
                } elseif ($startDate) {
                    $query->where('created_at', '>=', $startDate);
                } elseif ($endDate) {
                    $query->where('created_at', '<=', $endDate);
                }
            })
            ->when(isset($filters['lawyer_status']), function ($query) use ($filters) {
                $query->where('lawyer_status_id', $filters['lawyer_status']);
            });
    }

    public function sendInspector($user, $roleId): DxaResponse
    {
        DB::beginTransaction();
        try {
            $response = $this->findResponse();
            $response->dxa_response_status_id = isset($this->data['type']) ? DxaResponseStatusEnum::IN_REGISTER : DxaResponseStatusEnum::SEND_INSPECTOR;
            $response->inspector_sent_at = Carbon::now();
            $response->inspector_id = $this->data['inspector_id'];
            $response->gnk_id = $this->data['gnk_id'];
            $response->funding_source_id = $this->data['funding_source_id'];
            $response->sphere_id = $this->data['sphere_id'];
            $response->program_id = $this->data['program_id'];
            $response->end_term_work = $this->data['end_term_work'];
            $response->register_id = $this->data['register_id'];
            $response->send_operator = null;
            if ($this->data['funding_source_id'] == 1) {
                $monitoring = $this->saveMonitoringObject($this->data['gnk_id']);
                $response->monitoring_object_id = $monitoring->id;
            }
            $response->save();
            if ($response->dxa_response_status_id == DxaResponseStatusEnum::SEND_INSPECTOR) {
                $this->sendNotification($this->data['inspector_id'], $response->task_id);
            }


//            if ($response->sphere_id == 57){
//                $this->expertiseBlocks($response);
//            }
            $this->saveHistory($response, $user, $roleId, LogType::SEND_INSPECTOR, 'Inspektorga yuborildi', $this->data);
            DB::commit();
            return $response;
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }


    }

    public function expertiseBlocks($dxa)
    {
        try {
            $url = 'https://api-ekspertiza.mc.uz/api/integration-routes/get-appeal-eskrou/'.$dxa->reestr_number;
            $response = Http::withBasicAuth(
                'gasn@mc.uz',
                'eLf1JXTSPS7FNQT'
            )
                ->get($url);

            if ($response->successful()) {
                $data =  $response->json() ?? null;
                if (!empty($data['latest_final_id']['blocks'])) {
                    foreach ($data['latest_final_id']['blocks'] as $block) {
                        $maxBlock = null;

                        if (
                            ($block['no_residential'] == 0 || $block['no_residential'] == 2)
                            && isset($block['block_infos']) && is_array($block['block_infos'])
                        ) {
                            $blockInfos = collect($block['block_infos']);

                            $max1 = collect($blockInfos->get(1, []))->sortByDesc('floor')->first();
                            $max0 = collect($blockInfos->get(0, []))->sortByDesc('floor')->first();

                            if ($max1 && $max0) {
                                $maxBlock = $max1['floor'] >= $max0['floor'] ? $max1 : $max0;
                            } elseif ($max1) {
                                $maxBlock = $max1;
                            } elseif ($max0) {
                                $maxBlock = $max0;
                            }

                            if(!$dxa->blocks()->where('block_unique_id', $block['block_unique_id'])->exists()){
                                Block::query()->create([
                                    'name' => $block['name'],
                                    'block_unique_id' => $block['block_unique_id'],
                                    'block_mode_id' => 1,
                                    'block_type_id' => 1,
                                    'floor' => $maxBlock['floor'] ?? null,
                                    'construction_area' => $block['total_area'] ?? null,
                                    'count_apartments' => $block['apartments'] ?? null,
                                    'status' => true,
                                    'accepted' => false,
                                    'dxa_response_id' => $dxa->id,
                                    'appearance_type' => $block['no_residential'] == 2 ? 1 : 0,
                                ]);
                            }
                        }
                    }
                }

            }
        }catch (\Exception $exception){
        }
    }


    private function sendNotification($inspectorId, $taskId)
    {
        try {
            $inspector = User::query()->find($inspectorId);
            $user = Auth::user();
            $data = [
                'screen' => 'register'
            ];
            $message = MessageTemplate::attachObjectInspector($user->full_name, $taskId, 'Registrator', now());
            $inspector->notify(new InspectorNotification(title: "Ro'yxatdan o'tkazish uchun ariza keldi", message: $message, url: null, additionalInfo: $data));

        } catch (\Exception $exception) {

        }
    }

    public function getHistory($id)
    {
        $dxa = DxaResponse::query()->find($id);
        $histories = $dxa->histories
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
                        ? DxaResponseStatus::query()->find(
                            $history->content->status,
                            ['id', 'status']
                        )
                        : null,

                    'comment' => $history->content->comment,

                    'type' => $history->type,

                    'is_change' => $history->type
                        ? LogType::getDxaLabel($history->type)
                        : null,

                    'created_at' => $history->created_at,
                ];
            })
            ->sortByDesc('created_at')
            ->values();

        return $histories;

    }
    public function check($id)
    {
        try {
            $response = DxaResponse::query()->find($id);
            $data = getData(config('app.gasn.funding_source'), $response->reestr_number);
            if ($data)
                return $data['data'];
            return null;
        }catch (\Exception $exception){

        }
    }

    public function saveHistory($model, $user, $roleId, $action, $comment = '', $additionalInfo = [])
    {

        return $this->history->createHistory(
            guId: $model->id,
            status: $model->dxa_response_status_id->value,
            type: $action,
            date: null,
            comment: $comment,
            additionalInfo: [
                'user_id' => $user->id, 'role_id' => $roleId, 'data' => json_encode($additionalInfo)
            ]
        );
    }


    private function saveMonitoringObject($gnkId)
    {
        $data = getData(config('app.gasn.get_monitoring'), $gnkId);
        $monitoring = $data['data']['result']['data'][0];

        if(!MonitoringObject::query()->where('monitoring_object_id', $monitoring['id'])->exists()) {
            $object = new MonitoringObject();
            $object->monitoring_object_id = $monitoring['id'];
            $object->project_type_id = $monitoring['project_type_id'];
            $object->name = $monitoring['name'];
            $object->gnk_id = $monitoring['gnk_id'];
            $object->end_term_work_days = $monitoring['end_term_work_days'];
            $object->response = $monitoring;
            $object->save();
            return $object;
        }
        return MonitoringObject::query()->where('monitoring_object_id', $monitoring['id'])->first();

    }

    public function sendRegister(): DxaResponse
    {
        DB::beginTransaction();
        try {
            $response = $this->findResponse();
            $oldResponse = DxaResponse::query()->where('old_task_id', $response->task_id)->first();
            $response->dxa_response_status_id = DxaResponseStatusEnum::IN_REGISTER;
            $response->administrative_status_id = $this->data['administrative_status_id'] ?? ($oldResponse ? $oldResponse->administrative_status_id : null);
            $response->inspector_answered_at = Carbon::now();
            $response->price_supervision_service = price_supervision((int)$response->cost);
            $response->long = $this->data['long'];
            $response->lat = $this->data['lat'];
            $response->inspector_commit = $this->data['commit'];
            $response->send_operator = null;
            $response->save();

            $this->saveImages();
            if ($response->administrative_status_id != 6 && isset($this->data['blocks']))
            {
                $this->saveBlocks($response);
            }
            $this->saveRekvizit();
            DB::commit();
            return $response;
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    public function sendUpdate($id)
    {
        try {

        }catch (\Exception $exception){
            throw $exception;
        }
    }

    private function saveDocuments()
    {
        $model = $this->findResponse();

        foreach ($this->data['documents'] as $document) {
            $path = $document->store('documents/administrative-files', 'public');
            $model->documents()->create(['url' => $path]);
        }
    }

    private function saveRekvizit()
    {
        $response = $this->findResponse();
        $rekvizit = Rekvizit::query()->where('district_id', $response->district_id)->first();
        $response->update([
            'rekvizit_id' => $rekvizit->id,
        ]);
    }

//    private function saveBlocks($response)
//    {
//        if (isset($this->data['blocks'])) {
//            foreach ($this->data['blocks'] as $blockData) {
//                $blockAttributes = [
//                    'name' => $blockData['name'],
//                    'dxa_response_id' => $response->id,
//                    'floor' => $blockData['floor'] ?? null,
//                    'construction_area' => $blockData['construction_area'],
//                    'count_apartments' => $blockData['count_apartments'],
//                    'height' => $blockData['height'] ?? null,
//                    'length' => $blockData['length'] ?? null,
//                    'block_mode_id' => $blockData['block_mode_id'] ?? null,
//                    'block_type_id' => $blockData['block_type_id'] ?? null,
//                    'appearance_type' => $blockData['appearance_type'] ?? null,
//                    'created_by' => Auth::id(),
//                    'status' => true,
//                ];
//
//                if (isset($blockData['block_id'])){
//                    $block = Block::query()->find($blockData['block_id']);
//                    if (!$blockData['block_type_id']) throw new \Exception('Blok turini tanlang');
//                    if ($block) {
//                        $blockAttributes['selected_work_type'] = $block->selected_work_type;
//                        $block->update($blockAttributes);
//                    }
//                }else{
//                    $blockAttributes['selected_work_type'] = $response->administrative_status_id != 8;
//                    Block::create($blockAttributes);
//                }
//            }
//        }else{
//            throw new NotFoundException('Block qo\'shilmagan');
//        }
//    }

    private function saveBlocks($response): void
    {
        if (empty($this->data['blocks'])) {
            throw new NotFoundException("Block qo'shilmagan");
        }

        foreach ($this->data['blocks'] as $blockData) {

            if (!empty($blockData['block_id'])) {

                $block = Block::query()->find($blockData['block_id']);

                if (!$block) {
                    throw new NotFoundException(
                        "Block topilmadi: {$blockData['block_id']}"
                    );
                }

//                if (empty($blockData['block_type_id'])) {
//                    throw new \Exception('Blok turini tanlang');
//                }

                $blockAttributes = [];

                $fields = [
                    'name',
                    'floor',
                    'construction_area',
                    'count_apartments',
                    'height',
                    'length',
                    'block_mode_id',
                    'block_type_id',
                    'appearance_type',
                    'selected_work_type',
                ];

                foreach ($fields as $field) {
                    if (array_key_exists($field, $blockData)) {
                        $blockAttributes[$field] = $blockData[$field];
                    }
                }

                $blockAttributes['dxa_response_id'] = $response->id;
                $blockAttributes['created_by'] = Auth::id();
//                $blockAttributes['status'] = true;

                $block->update($blockAttributes);

            } else {

                if (empty($blockData['block_type_id'])) {
                    throw new \Exception('Blok turini tanlang');
                }

                $blockAttributes = [
                    'name' => $blockData['name'] ?? null,
                    'dxa_response_id' => $response->id,
                    'floor' => $blockData['floor'] ?? null,
                    'construction_area' => $blockData['construction_area'] ?? null,
                    'count_apartments' => $blockData['count_apartments'] ?? null,
                    'height' => $blockData['height'] ?? null,
                    'length' => $blockData['length'] ?? null,
                    'block_mode_id' => $blockData['block_mode_id'],
                    'block_type_id' => $blockData['block_type_id'],
                    'appearance_type' => $blockData['appearance_type'] ?? null,
                    'created_by' => Auth::id(),
                    'status' => true,
                    'selected_work_type' => array_key_exists(
                        'selected_work_type',
                        $blockData
                    )
                        ? $blockData['selected_work_type']
                        : $response->administrative_status_id != 8,
                ];

                Block::query()->create($blockAttributes);
            }
        }
    }

    public function sendReject($response, $comment): DxaResponse
    {

        $response->dxa_response_status_id = DxaResponseStatusEnum::REJECTED;
        $response->rejection_comment = $comment;
        $response->rejected_at = now();
        $response->send_operator = null;
        $response->save();
        if ($response->administrative_status_id == 6) {
            $response->update([
                'lawyer_status_id' => 1,
            ]);

            if ($response->notification_type == 1) {
                $response->update([
                    'in_compliance' => true
                ]);
            }

        }
        return $response;
    }

    public function updateMyGov($response, $comment)
    {
        $authCredentials = [
            'username' => config('app.mygov.login'),
            'password' => config('app.mygov.password'),
        ];

        $apiDetails = $response->object_type_id == 2
            ? [
                'url' => config('app.mygov.url') . '/update/id/' . $response->task_id . '/action/resend-to-corrections',
                'form' => 'ResendToCorrectionFormNoticeBeginningConstructionWorks',
                'info' => 'info_text_correction'
            ]
            : [
                'url' => config('app.mygov.linear') . '/update/id/' . $response->task_id . '/action/change-applications',
                'form' => 'ChangeApplicationFormRegistrationStartLinearObject',
                'info' => 'application_correction'
            ];

        $return =  Http::withBasicAuth($authCredentials['username'], $authCredentials['password'])
            ->post($apiDetails['url'], [
                $apiDetails['form'] => [
                    $apiDetails['info'] => $comment,
                ],
            ]);
        telegramNotification('Ariza taxrirlash', $response->task_id, $return->json());

        return $return->status();
    }

    public function sendMyGovReject($response, $comment)
    {
        $authCredentials = [
            'username' => config('app.mygov.login'),
            'password' => config('app.mygov.password'),
        ];

        $apiDetails = $response->object_type_id == 2
            ? [
                'url' => config('app.mygov.url') . '/update/id/' . $response->task_id . '/action/reject-notice',
                'form' => 'RejectNoticeV4FormNoticeBeginningConstructionWorks',
            ]
            : [
                'url' => config('app.mygov.linear') . '/update/id/' . $response->task_id . '/action/reject-notice',
                'form' => 'RejectNoticeFormRegistrationStartLinearObject',
            ];

        $response =  Http::withBasicAuth($authCredentials['username'], $authCredentials['password'])
            ->post($apiDetails['url'], [
                $apiDetails['form'] => [
                    "reject_reason" => $comment,
                ],
            ]);

        return $response->status();

    }

    public function checkHasFileInvest($response)
    {
        if ($response->reestr_number){
            try {
                $data = Http::withBasicAuth(
                    'gasnnew',
                    'GHVB1g6bpxW7bAm')
                    ->timeout(10)
                    ->connectTimeout(5)
                    ->get("https://api.dx.mc.uz/api/module3/v1/invest/finals/".$response->reestr_number);
                if ($data->successful()){
                    $result = $data->json('result.data');
                    return $result;
                }
                return throw new \Exception('Invest kompaniyasidan ma\'lumot olib bolmadi');
            }catch (\Throwable $exception){
                throw $exception;
            }
        }
        return null;
    }

    public function rejectInspection($user, $roleId, $response, $comment): DxaResponse
    {
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
            $meta = ['uuid' => $document->uuid, 'guid' => $document->guid];
            $response->update(['dxa_response_status_id' => DxaResponseStatusEnum::IN_REGISTER, 'inspection_comment' => request('comment')]);
            $this->saveHistory($response, $user, $roleId, LogType::SEND_REGISTER, $comment, $meta);
            return $response;
        }catch (\Exception $exception){
            throw $exception;
        }
    }

    private function findResponse(): ?DxaResponse
    {
        return $this->dxaResponse->where('task_id', $this->data['task_id'])->first() ?? null;
    }

    private function saveImages(): void
    {
        $model = $this->findResponse();

        if ($model->notification_type == 2) {
            $response = DxaResponse::getResponse($model->old_task_id);
            if ($response)
            {
                $images = $response->images;
                foreach ($images as $image) {
                    $newImage = $image->replicate();
                    $newImage->imageable_id = $model->id;
                    $newImage->imageable_type = get_class($model);
                    $newImage->save();
                }
            }

        }
        if (isset($this->data['images'])) {
            foreach ($this->data['images'] as $image) {
                $path = $image->store('images/response', 'public');
                $model->images()->create(['url' => $path]);
            }
        }
    }
}
