<?php

namespace App\Repositories;

use App\Enums\LogType;
use App\Enums\UserRoleEnum;
use App\Helpers\ClaimStatuses;
use App\Models\ClaimGov;
use App\Models\ClaimGovMonitoring;
use App\Models\ClaimGovOrganizationReview;
use App\Models\Response;
use App\Models\User;
use App\Repositories\Interfaces\ClaimGovRepositoryInterface;
use App\Services\ArticleService;
use App\Services\HistoryService;
use Carbon\Carbon;

class ClaimGovRepository implements ClaimGovRepositoryInterface
{
    public function __construct(
        protected ClaimGov $model,
        protected ArticleService $articleService,
        protected Response  $response,
    ){}

    public function all($user, $roleId)
    {

        switch ($roleId) {
            case UserRoleEnum::INSPECTOR->value:
            case UserRoleEnum::ICHKI->value:
            case UserRoleEnum::MUALLIF->value:
            case UserRoleEnum::TEXNIK->value:
            case UserRoleEnum::LOYIHA->value:
            case UserRoleEnum::BUYURTMACHI->value:
            case UserRoleEnum::QURILISH->value:
            case UserRoleEnum::FOYDALANUVCHI->value:
                return $this->getByRoleId($roleId,$user->id);
            case UserRoleEnum::LOYIHA_XODIM->value:
                return $this->getByCompany($user);
            case UserRoleEnum::SANOAT->value:
            case UserRoleEnum::VILOYAT_FVV->value:
            case UserRoleEnum::VILOYAT_SEOM->value:
            case UserRoleEnum::UZGIDROINSPEKSIYA->value:
            case UserRoleEnum::UZKOMNAZORAT->value:
            case UserRoleEnum::SUVINSPEKSIYA->value:
            case UserRoleEnum::TRANSPORT->value:
            case UserRoleEnum::IIB->value:
            case UserRoleEnum::MADANIYAT->value:
            case UserRoleEnum::KADASTR->value:
            case UserRoleEnum::HETK->value:
            case UserRoleEnum::HUDUDGAZ->value:
            case UserRoleEnum::UZSUVTAMINOT->value:
            case UserRoleEnum::ISSIQLIK->value:
            case UserRoleEnum::EKOLOGIYA->value:
            case UserRoleEnum::NOGIRONLAR_JAM->value:
                return $this->getByRegionId($user->region_id)->whereHas('reviews', function ($q) use ($roleId) {
                    $q->where('organization_id', $roleId);
                });
            case UserRoleEnum::OPERATOR->value:
            case UserRoleEnum::INSPEKSIYA->value:
            case UserRoleEnum::QURILISH_MONTAJ->value:
            case UserRoleEnum::HUDUDIY_KUZATUVCHI->value:
            case UserRoleEnum::KOMPLAYNS->value:
                return $this->getByRegionId($user->region_id);
            case UserRoleEnum::RESPUBLIKA_KUZATUVCHI->value:
            case UserRoleEnum::RESPUBLIKA_KOMPLAYNS->value:
                return $this->model->query();

            default:
                return $this->model->query()->whereRaw('1 = 0');
        }
    }

    public function filters($query, $roleId, $filters)
    {
        return $query
            ->when(!empty($filters['task_id']), function ($query) use ($filters) {
                $query->where('task_id', $filters['task_id']);
            })

            ->when(!empty($filters['organization']), function ($query) use ($filters) {
                $query->whereRaw(
                    'LOWER(organization) LIKE ?',
                    ['%' . mb_strtolower($filters['organization']) . '%']
                );
            })

            ->when(isset($filters['status']), function ($query) use ($filters) {
                $query->where('status', $filters['status']);
            })

            ->when(!empty($filters['region_id']), function ($query) use ($filters) {
                $query->where('region_id', $filters['region_id']);
            })

            ->when(!empty($filters['district_id']), function ($query) use ($filters) {
                $query->where('district_id', $filters['district_id']);
            })
            ->when(isset($filters['answered']), function ($query) use ($filters, $roleId) {
                $query->whereHas('reviews', function ($q) use ($filters, $roleId) {
                    $organizationId = $roleId == 61 ? 9 : $roleId;
                    if ((int)$filters['answered'] === 1) {
                        $q->whereNotNull('answered_at')->where('organization_id', $organizationId);
                    } else {
                        $q->whereNull('answered_at')->where('organization_id', $organizationId);
                    }
                });
            })
            ->when(
                !empty($filters['start_date']) || !empty($filters['end_date']),
                function ($query) use ($filters) {
                    $startDate = !empty($filters['start_date'])
                        ? $filters['start_date'] . ' 00:00:00'
                        : null;

                    $endDate = !empty($filters['end_date'])
                        ? $filters['end_date'] . ' 23:59:59'
                        : null;

                    if ($startDate && $endDate) {
                        $query->whereBetween('created_at', [$startDate, $endDate]);
                    } elseif ($startDate) {
                        $query->where('created_at', '>=', $startDate);
                    } elseif ($endDate) {
                        $query->where('created_at', '<=', $endDate);
                    }
                }
            )

            ->when(!empty($filters['sphere']), function ($query) use ($filters) {
                $query->whereHas('object', function ($q) use ($filters) {
                    $q->where('sphere_id', $filters['sphere']);
                });
            });
    }


    public function findById($id)
    {
        return $this->model->query()->find($id);
    }

    public function update($id, $data)
    {
        $model = $this->findById($id);
        $model->update($data);
        return $model;
    }

    public function createMonitoring($claimGov, $organizations)
    {
        $monitoring = ClaimGovMonitoring::query()->create([
           'claim_gov_id' => $claimGov->id,
           'object_id' => $claimGov->object_id,
           'organizations' => $organizations,
        ]);

        return $monitoring;
    }

    public function createOrganizationReview($claimId, $objectId, $monitoringId, $organizationId, $deadline, $sphereId)
    {
       $review = ClaimGovOrganizationReview::query()->create([
            'claim_gov_id' => $claimId,
            'object_id' => $objectId,
            'monitoring_id' => $monitoringId,
            'organization_id' => $organizationId,
            'deadline' => $deadline,
            'claim_sphere_id' => $sphereId,
        ]);

       return $review;
    }


    public function create($claimGov, $expiryDate)
    {
        $info = $claimGov->entities->ConstructionAcceptancePermit;
        $object = $this->articleService->findByTaskId($info->application_number_registered->real_value);
        $oldClaim = ClaimGov::query()->where('object_id', $object->id)->whereIn('status', [15,9])->latest()->first();


        $status = ClaimStatuses::TASK_STATUS_ANOTHER;
        if ($claimGov->task->current_node == "direction-statement-object")
            $status = ClaimStatuses::TASK_STATUS_ACCEPTANCE;
        if ($claimGov->task->current_node == "answer-other-institutions")
            $status = ClaimStatuses::TASK_STATUS_SENT_ORGANIZATION;
        if ($claimGov->task->current_node == "conclusion-minstroy")
            $status = ClaimStatuses::TASK_STATUS_ACCEPTANCE;
        if ($claimGov->task->current_node == "inactive" && $claimGov->task->status == "not_active")
            $status = ClaimStatuses::TASK_STATUS_CANCELLED;

        if ($claimGov->task->status == "new")
            $status = ClaimStatuses::TASK_STATUS_ACCEPTANCE;

        if ($status == ClaimStatuses::TASK_STATUS_ACCEPTANCE) {
            (new HistoryService('claim_gov_histories'))->createHistory(
                guId: $claimGov->task->id,
                status: $status,
                type: LogType::TASK_HISTORY,
                date: null
            );
        }

        if ($claimGov->task->current_node == "inactive" && $claimGov->task->status == "processed") {
            $status = ClaimStatuses::TASK_STATUS_CONFIRMED;
            (new HistoryService('claim_gov_histories'))->createHistory(
                guId: $claimGov->task->id,
                status: ClaimStatuses::TASK_STATUS_ACCEPTANCE,
                type: LogType::TASK_HISTORY,
                date: $claimGov->task->created_date
            );
            (new HistoryService('claim_gov_histories'))->createHistory(
                guId: $claimGov->task->id,
                status: ClaimStatuses::TASK_STATUS_CONFIRMED,
                type: LogType::TASK_HISTORY,
                date: $claimGov->task->last_update
            );
        }

        if ($claimGov->task->current_node == "inactive" && $claimGov->task->status == "rejected") {
            $status = ClaimStatuses::TASK_STATUS_REJECTED;
            (new HistoryService('claim_gov_histories'))->createHistory(
                guId: $claimGov->task->id,
                status: ClaimStatuses::TASK_STATUS_ACCEPTANCE,
                type: LogType::TASK_HISTORY,
                date: $claimGov->task->created_date
            );
            (new HistoryService('claim_gov_histories'))->createHistory(
                guId: $claimGov->task->id,
                status: ClaimStatuses::TASK_STATUS_REJECTED,
                type: LogType::TASK_HISTORY,
                date: $claimGov->task->last_update
            );
        }

        $claim = new ClaimGov();
        $claim->object_id = $object?->id;
        $claim->declaration_id = $object?->declaration?->id;
        $claim->claim_sphere_id = $oldClaim ? $oldClaim->claim_sphere_id : null;
        $claim->expire_date = $expiryDate;
        $claim->declaration_id = $object->declaration->id ?? null;
        $claim->region_id = $object?->region_id;
        $claim->status = $status;
        $claim->status_mygov = $claimGov->task->status;
        $claim->current_node = $claimGov->task->current_node;
        $claim->district_id = $object?->district_id;
        $claim->address = $object?->location_building;
        $claim->task_id = $claimGov->task->id;
        $claim->user_type = $info->user_type->real_value;
        $claim->legal_tin = $info->legal_tin->real_value;
        $claim->legal_name = $info->legal_name->real_value;
        $claim->legal_phone = $info->legal_phone->real_value;
        $claim->property_owner = $info->property_owner->real_value;
        $claim->topography = $info->registry_number_topography->real_value;
        $claim->information_organizations_responsible = $info->information_organizations_responsible->real_value;
        $claim->equipment_acceptance_report = $info->equipment_acceptance_report->real_value;
        $claim->object_task_id = $info->application_number_registered->real_value;

        $claim->save();

//        if ($oldClaim) {
//            $oldReviews = $oldClaim?->reviews->where('status', true)->get();
//            if ($oldReviews->count() > 0) {
//                foreach ($oldReviews as $oldReview) {
//                    ClaimGovOrganizationReview::query()->create([
//                        'claim_gov_id' => $claim->id,
//                        'organization_id' => $oldReview->organization_id,
//                        'object_id' => $claim->object_id,
//                        'answer' => $oldReview->answer,
//                        'status' => $oldReview->status,
//                        'deadline' => $oldReview->deadline,
//                        'answered_at' => $oldReview->answered_at,
//                        'user_id' => $oldReview->user_id,
//                        'pkcs7' => $oldReview->pkcs7,
//                        'created_at' => $oldReview->created_at,
//                        'updated_at' => $oldReview->updated_at,
//                    ]);
//                }
//            }
//        }
        return $claim;
    }

    public function organizationConclusion($id)
    {
        return ClaimGovOrganizationReview::query()->find($id);

    }

    public function updateConclusion($data, $user,  $id, $status, $pkcs7)
    {
        ClaimGovOrganizationReview::query()->where('id', $id)->update(
            [
                'answered_at' => Carbon::now(),
                'status' => $status,
                'answer' => base64_encode(gzcompress(json_encode($data), 9)),
                'user_id' => $user->id,
                'pkcs7' => $pkcs7,
            ]
        );
    }

    public function updateResponseStatus($taskId,  $status)
    {
        $this->response->where('task_id', $taskId)->update(
            [
                'status' => $status
            ]
        );
    }

    public function findByTaskId($taskId)
    {
        return $this->model->query()->where('task_id', $taskId)->first();
    }

    private function getByRegionId($regionId)
    {
        return $this->model->with(['region', 'district', 'object', 'reviews'])
            ->where('region_id', $regionId);
    }

    private function getByRoleId(int $roleId, int $userId)
    {
        return $this->model->with(['region', 'district', 'object', 'reviews'])
            ->whereHas('object.users', function ($q) use ($roleId, $userId) {
                $q->where('article_users.role_id', $roleId)
                    ->where('article_users.user_id', $userId);
            });
    }

    public function getByCompany($user)
    {
        $company = User::query()->where('pinfl', $user->company_inn)->first();

        return $this->model->with(['region', 'district', 'object', 'reviews'])
            ->whereHas('object.users', function ($q) use ($user, $company) {
                $q->where('article_users.role_id', UserRoleEnum::LOYIHA->value)
                    ->where('article_users.user_id', $company->id);
            })
            ->where('region_id', $user->region_id);
    }


}
