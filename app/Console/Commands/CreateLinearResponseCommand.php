<?php

namespace App\Console\Commands;

use App\Enums\DxaResponseStatusEnum;
use App\Helpers\ClaimStatuses;
use App\Models\Article;
use App\Models\DxaResponse;
use App\Models\Response;
use App\Services\DxaLinearResponseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateLinearResponseCommand extends Command
{

    protected $signature = 'module:create-linear-response';


    protected $description = 'Command description';
    public function __construct(
        protected DxaLinearResponseService $service
    )
    {
        parent::__construct();
    }

    public function handle()
    {
        $data = Response::query()
            ->where('status', ClaimStatuses::RESPONSE_NEW)
            ->where('module', 3)
            ->orderBy('id')
            ->lockForUpdate()
            ->take(1)
            ->get();

        foreach ($data as $item) {
            $response = $this->service->fetchTaskData($item->task_id);
            $data = $this->service->parseResponse($response);
            $updated = DxaResponse::query()->where('task_id', $item->task_id)->where('dxa_response_status_id', DxaResponseStatusEnum::SEND_UPDATE)->first();
            if (!DxaResponse::query()->where('task_id', $item->task_id)->exists() || $updated) {
                $item->update(['status' => ClaimStatuses::RESPONSE_PROCESSING]);
                DB::beginTransaction();
                try {
                    $taskId = $item->task_id;

                    $userType = $this->service->determineUserType($data['user_type']['real_value']);
                    $dxa = $this->service->saveDxaResponse($taskId, $data, $userType, $response->json());
                    $this->service->sendMyGov($dxa);
                    $this->service->saveExpertise($dxa);
                    $item->update([
                        'status' => ClaimStatuses::RESPONSE_WATCHED
                    ]);
                    DB::commit();
                } catch (\Exception $exception) {
                    DB::rollBack();

                        $item->update([
                            'status' => ClaimStatuses::RESPONSE_ERRORED
                        ]);
                    telegramNotification('Tarmoqda xatolik ariza yaratish', $item->task_id, $exception->getMessage());
                    continue;
                }

                sleep(15);
            }else{
                $json = $response->json();

                if ($json['task']['current_node'] == 'inactive')
                {
                    if ($json['task']['status'] == 'processed')
                    {
                        $status = DxaResponseStatusEnum::ACCEPTED;
                    }
                    elseif ($json['task']['status'] == 'rejected')
                    {
                        $status = DxaResponseStatusEnum::REJECTED;
                    }
                    elseif ($json['task']['status'] == 'not_active')
                    {
                        $status = DxaResponseStatusEnum::CANCELED;
                    }
                    elseif ($json['task']['status'] == 'resend')
                    {
                        $status = DxaResponseStatusEnum::SEND_UPDATE;
                    }
                    else{
                        $status = DxaResponseStatusEnum::NEW;
                    }
                    DxaResponse::query()->where('task_id', $item->task_id)->update(['dxa_response_status_id' => $status]);
                }

                if ($json['task']['current_node'] == 'correction-application' and $json['task']['status'] == "waiting_sub_user"){
                    DxaResponse::query()->where('task_id', $item->task_id)->update(['dxa_response_status_id' => DXaResponseStatusEnum::SEND_UPDATE]);
                }
                if ($json['task']['current_node'] == 'info-change-app' and $json['task']['status'] == "waiting_sub_user"){
                    DxaResponse::query()->where('task_id', $item->task_id)->update(['dxa_response_status_id' => DXaResponseStatusEnum::SEND_UPDATE]);
                }
                
                if ($json['task']['current_node'] == "acception-consideration" and $json['task']['status'] == "resend"){
                    DxaResponse::query()->where('task_id', $item->task_id)->update(['dxa_response_status_id' => DXaResponseStatusEnum::IN_REGISTER]);
                }

                if ($json['task']['current_node'] == "consideration-application" and $json['task']['status'] == "resend"){
                    DxaResponse::query()->where('task_id', $item->task_id)->update(['dxa_response_status_id' => DXaResponseStatusEnum::IN_REGISTER]);
                }

                $item->update([
                    'status' => ClaimStatuses::RESPONSE_WATCHED
                ]);
            }

        }
    }


}
