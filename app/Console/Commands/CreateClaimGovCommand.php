<?php

namespace App\Console\Commands;

use App\Helpers\ClaimStatuses;
use App\Models\Response;
use App\Services\ClaimGovService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateClaimGovCommand extends Command
{

    public function __construct(protected ClaimGovService $service)
    {
        parent::__construct();
    }

    protected $signature = 'app:create-claim-gov';


    protected $description = 'Command description';


    public function handle()
    {
        $responses = Response::query()->where('status', ClaimStatuses::RESPONSE_NEW)
            ->where('module', 4)
            ->orderBy('id', 'asc')
            ->lockForUpdate()
            ->take(10)
            ->get();

        foreach ($responses as $response) {
            DB::beginTransaction();
            try {
                $data = $this->service->getClaimGov($response->task_id);
                if (!$data) {
                    $this->service->updateResponseStatus(
                        taskId: $response->task_id,
                        status: ClaimStatuses::RESPONSE_ERRORED
                    );
                    DB::commit();
                    continue;
                }

                $this->service->updateResponseStatus(
                    taskId: $response->task_id,
                    status: ClaimStatuses::RESPONSE_WATCHED
                );

                DB::commit();

            }catch (\Exception $e){
                DB::rollBack();
                $this->output->writeln($e->getMessage());
                $this->output->writeln($e->getTraceAsString());

                $this->service->updateResponseStatus(
                    taskId: $response->task_id,
                    status: ClaimStatuses::RESPONSE_ERRORED
                );

                telegramNotification('Davlat obyekti push xatolik', $response->task_id, $e->getMessage());
            }
            sleep(2);
        }
    }
}
