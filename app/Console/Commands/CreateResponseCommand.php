<?php

namespace App\Console\Commands;

use App\Enums\DxaResponseStatusEnum;
use App\Enums\LogType;
use App\Helpers\ClaimStatuses;
use App\Models\Article;
use App\Models\DxaResponse;
use App\Models\Response;
use App\Services\DxaBuildingResponseService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateResponseCommand extends Command
{

    protected $signature = 'module:create-building-response';


    protected $description = 'Command description';

    public function __construct(
        protected DxaBuildingResponseService $service
    )
    {
        parent::__construct();
    }

    public function handle()
    {

        $data = Response::query()
            ->where('status', ClaimStatuses::RESPONSE_NEW)
            ->where('module', 1)
            ->orderBy('id', 'asc')
            ->lockForUpdate()
            ->take(10)
            ->get();
        foreach ($data as $item) {
            $response = $this->service->fetchTaskData($item->task_id);
            if (!$response->successful()){
                $item->update(['status' => 5]);
                continue;
            }
            $data = $this->service->parseResponse($response);

            $updated = DxaResponse::query()->where('task_id', $item->task_id)->where('dxa_response_status_id', DxaResponseStatusEnum::SEND_UPDATE)->first();

            if (!DxaResponse::query()->where('task_id', $item->task_id)->exists() || $updated) {
                $item->update(['status' => ClaimStatuses::RESPONSE_PROCESSING]);
                try {
                    DB::transaction(function () use ($item, $response, $data) {
                        $taskId = $item->task_id;

                        $userType = $this->service->determineUserType($data['user_type']['real_value']);
                        $dxa = $this->service->saveDxaResponse($taskId, $data, $userType, $response->json());
                        $this->service->sendMyGov($dxa);
                        $this->service->saveExpertise($dxa);
                        $this->service->checkOldReponse($dxa);

                        $item->update(['status' => ClaimStatuses::RESPONSE_WATCHED]);
                        echo 'Task: '.$item->task_id.' ';
                    });
                } catch (\Exception $exception) {
                    $item->update(['status' => ClaimStatuses::RESPONSE_ERRORED]);
                    telegramNotification('Binoda xatolik ariza yaratish', $item->task_id, $exception->getMessage());
                }
            } else {
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

                if ($json['task']['current_node'] == "acception-consideration" and $json['task']['status'] == "resend"){
                    DxaResponse::query()->where('task_id', $item->task_id)->update(['dxa_response_status_id' => DXaResponseStatusEnum::IN_REGISTER]);
                }

                if ($json['task']['current_node'] == "consideration-application" and $json['task']['status'] == "resend"){
                    DxaResponse::query()->where('task_id', $item->task_id)->update(['dxa_response_status_id' => DXaResponseStatusEnum::IN_REGISTER]);
                }

                $item->update(['status' => ClaimStatuses::RESPONSE_WATCHED]);
            }

            sleep(2);
        }
    }
}
