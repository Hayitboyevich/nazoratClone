<?php

namespace App\Console\Commands;

use App\Enums\UserRoleEnum;
use App\Models\Article;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendToStatisticsCommand extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-to-statistics-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $objects = Article::query()
            ->where('sent_to_statistics', 0)
            ->where('sphere_id', 57)
            ->with(['region:id,name_uz', 'district:id,name_uz', 'objectType:id,name',
                'blocks' => function ($query) {
                    $query->select(['id', 'article_id', 'name', 'floor', 'construction_area', 'count_apartments']);
                }
                , 'fundingSource:id,description', 'applicantUser', 'vendor', 'organization', 'designer', 'sphere', 'difficulty', 'objectStatus'])
            ->limit(30)
            ->orderBy('id')
            ->get();
        foreach ($objects as $object) {
            $correlation_id = uuid_create();
            $applicant = $object->applicantUser()->first();
            $vendor = $object->vendor()->first();
            $organization = $object->organization()->first();
            $designer = $object->designer()->first();
            $obj = Article::query()->where('id', $object->id)->first();
            $data = [
                'correlationId' => $correlation_id,
                'destinationSubscribers' => ["space-land-monitoring"],
                "data" => [
                    'object_id' => $object->id,
                    'object_name' => $object->name,
                    'task_id' => $object->task_id,
                    'designer_tin' => $designer->pinfl?? null,
                    'designer_name' => $designer->full_name?? ($designer->organization_name?? null),
                    'sphere_id' => !empty($object->sphere) ? $object->sphere->id : null,
                    'sphere_name' => !empty($object->sphere) ? $object->sphere->name_uz : null,
                    'created_at' => !empty($object->created_at) ? $object->created_at : null,
                    'closed_at' => $object->closed_at,
                    'customer_name' => $organization->full_name ?? ($organization->organization_name?? null),
                    'applicant_name' => $applicant->full_name ?? ($applicant->organization_name?? null),
                    'builder_name' => $vendor->full_name ?? ($vendor->organization_name?? null),
                    'cadastral_number' => $object->cadastral_number,
                    'deadline' => $object->deadline,
                    'difficulty_category' => !empty($object->difficulty) ? [
                        'id' => $object->difficulty->id,
                        'name' => $object->difficulty->difficulty,
                    ] : null,
                    'construction_works' => $object->construction_works?? null,
                    'region' => $object->region ?? null,
                    'district' => $object->district ?? null,
                    'object_type' => $object->objectType ?? null,
                    'address' => $object->address,
                    'number_protocol' => $object->number_protocol,
                    'status_id' => $object->objectStatus->id,
                    'status_name' => $object->objectStatus->name,
                    'reestr_number' => $object->reestr_number,
                    'funding_source' => $object->fundingSource ?? null,
                    'construction_cost' => $object->construction_cost,
                    'tin_applicant' => $applicant->pinfl ?? null,
                    'tin_general_contractor' => $vendor->pinfl ?? null,
                    'organization' => $organization->pinfl ?? null,
                    'blocks' => collect($object->blocks)->map(function ($block) {
                        return [
                            'id' => $block->id,
                            'name' => $block->name,
                            'floor' => $block->floor,
                            'construction_area' => $block->construction_area,
                            'count_apartments' => $block->count_apartments,
                            'work_types' => []
                        ];
                    })
                ]
            ];
            $apiCredentials = "internal_user@mip:)!cq81Q£q1-h";
            $headers = [
                'Authorization' => 'Basic ' . base64_encode($apiCredentials),
            ];
            $client = new Client();
            try {
                $response = $client->request('POST', 'https://api.shaffofqurilish.uz/api/v1/request/egov-push-object',
                    [
                        'headers' => $headers,
                        'json' => $data,
                        'timeout' => 10,
                        'verify' => false
                    ]);
                $result = $response->getBody()->getContents();
                DB::transaction(function () use ($obj, $correlation_id) {
                    $obj->update(['statistics_correlation_id' => $correlation_id, 'sent_to_statistics' => 1]);
                });
                $this->info($result);
            } catch (\Exception $exception) {
                $obj->update(['sent_to_statistics' => 2]);
                $this->error($exception->getMessage());
            }
        }
    }
}
