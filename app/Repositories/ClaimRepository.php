<?php

namespace App\Repositories;

use App\Enums\LogType;
use App\Enums\ObjectStatusEnum;
use App\Enums\UserRoleEnum;
use App\Helpers\ClaimStatuses;
use App\Http\Requests\ClaimRequests\ConclusionOrganization;
use App\Models\Article;
use App\Models\Claim;
use App\Models\ClaimMonitoring;
use App\Models\ClaimOrganizationReview;
use App\Models\Response;
use App\Repositories\Interfaces\ClaimRepositoryInterface;
use App\Services\HistoryService;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;


class ClaimRepository implements ClaimRepositoryInterface
{
    private Response $response;
    private Claim $claim;

    public function __construct(Response $response, Claim $claim)
    {
        $this->response = $response;
        $this->claim = $claim;
    }

    // ---------------------- Begin ConsolidationResponse Methods ------------------------- //
    public function updateClaim(int $guId, array $data): bool
    {
        return Claim::where('guid', $guId)->update($data);
    }

    public function getResponseByGuId(int $guId)
    {
        return $this->response->where('guid', $guId)->first();
    }

    public function updateResponseStatus(int $guId, int $status)
    {
        $this->response->where('task_id', $guId)->update(
            [
                'status' => $status
            ]
        );
    }

    public function getExpiredTaskList()
    {
        return $this->claim->query()
            ->whereIn('claims.status', [
                ClaimStatuses::TASK_STATUS_ACCEPTANCE
            ])
            ->where('claims.expired', '=', 0)
            ->where(function ($query) {
                $query->whereDate('claims.expiry_date', '<', Carbon::today())
                    ->orWhereNull('claims.expiry_date');
            })
            ->orderBy('claims.id', 'ASC')
            ->select([
                "claims.id",
                "claims.guid",
                "claims.status",
                "claims.created_at",
                "claims.expiry_date"
            ])
            ->get();
    }

    public function getActiveResponses()
    {
        return $this->response
            ->whereIn('status', [
                ClaimStatuses::RESPONSE_NEW
            ])
            ->where('module', '=', 2)
            ->orderBy('id', 'asc')
            ->take(10)
            ->get();
    }

    // ---------------------- End ClaimResponse Methods ------------------------- //

    public function getStatisticsRepeated(int $region = null): array
    {
        return $this->claim->query()
            ->select([
                DB::raw('count(pinfl) as summ'),
                DB::raw('(CASE WHEN full_name IS NOT NULL THEN full_name ELSE legal_entity_name END) as ind_name')
            ])
            ->when(($region), function ($q) use ($region) {
                $q->where('region', $region);
            })
            ->groupBy('ind_name')
            ->orderByDesc('summ')
            ->take(10)
            ->get()->toArray();
    }

    public function organizationStatistics(int  $roleId, ?int $regionId,
                                           ?int $districtId, ?string $dateFrom, ?string $dateTo)
    {
        $role = match ($roleId) {
            21, 20 => 46,
            23, 22 => 45,
            24 => 17,
            25 => 18,
            35 => 34,
            default => $roleId,
        };

        return $this->claim->query()
            ->join('claim_organization_reviews', 'claim_organization_reviews.claim_id', '=', 'claims.id')
            ->join('regions', 'regions.soato', '=', 'claims.region')
            ->join('districts', 'districts.soato', '=', 'claims.district')
            ->select(DB::raw("
                COUNT(CASE WHEN claim_organization_reviews.answered_at IS NOT NULL THEN 1 ELSE null END) as answered,
                COUNT(CASE WHEN claim_organization_reviews.answered_at IS NULL THEN 1 ELSE null END) as not_answered,
                COUNT(claim_organization_reviews.id) as total
            "))
            ->when($dateFrom, function ($q) use ($dateFrom) {
                $q->whereDate('claims.created_at', '>=', $dateFrom);
            })
            ->when($dateTo, function ($q) use ($dateTo) {
                $q->whereDate('claims.created_at', '<=', $dateTo);
            })
            ->when($regionId, function ($q) use ($regionId) {
                $q->where('regions.id', '=', $regionId);
            })
            ->when($districtId, function ($q) use ($districtId) {
                $q->where('districts.id', '=', $districtId);
            })
            ->when($role, function ($q) use ($role) {
                $q->whereIn(
                    'claim_organization_reviews.organization_id',
                    match ($role) {
                        45 => [15, 45],
                        46 => [16, 46],
                        default => [$role],
                    }
                );
            })
            ->first()
            ->setAppends([]);
    }

    public function getStatisticsForInspector()
    {
        return $this->claim->query()
            ->join('article_users', 'article_users.article_id', '=', 'claims.object_id')
            ->join('articles', 'articles.id', '=', 'claims.object_id')
            ->select(DB::raw("
                COUNT(CASE WHEN claims.status = " . ClaimStatuses::TASK_STATUS_ACCEPTANCE . " THEN 1 ELSE null END) as in_process,
                COUNT(CASE WHEN claims.status = " . ClaimStatuses::TASK_STATUS_ATTACH_OBJECT . " THEN 1 ELSE null END) as attach_object,
                COUNT(CASE WHEN claims.status = " . ClaimStatuses::TASK_STATUS_INSPECTOR . " THEN 1 ELSE null END) as inspector,
                COUNT(CASE WHEN claims.status = " . ClaimStatuses::TASK_STATUS_SENT_ORGANIZATION . " THEN 1 ELSE null END) as sent_organization,
                COUNT(CASE WHEN claims.status = " . ClaimStatuses::TASK_STATUS_DIRECTOR . " THEN 1 ELSE null END) as director,
                COUNT(CASE WHEN claims.status = " . ClaimStatuses::TASK_STATUS_OPERATOR . " THEN 1 ELSE null END) as operator,
                COUNT(CASE WHEN claims.status = " . ClaimStatuses::TASK_STATUS_SENT_ANOTHER_ORG . " THEN 1 ELSE null END) as minstroy,
                COUNT(CASE WHEN claims.status = " . ClaimStatuses::TASK_STATUS_ORGANIZATION_REJECTED . " THEN 1 ELSE null END) as organization_rejected,
                COUNT(CASE WHEN claims.status = " . ClaimStatuses::TASK_STATUS_CONFIRMED . " THEN 1 ELSE null END) as confirmed,
                COUNT(CASE WHEN claims.status = " . ClaimStatuses::TASK_STATUS_REJECTED . " THEN 1 ELSE null END) as rejected,
                COUNT(CASE WHEN claims.status = " . ClaimStatuses::RESEND . " THEN 1 ELSE null END) as resend,
                COUNT(CASE WHEN claims.status = " . ClaimStatuses::TASK_STATUS_CANCELLED . " THEN 1 ELSE null END) as cancelled,
                COUNT(CASE WHEN claims.status <> " . ClaimStatuses::TASK_STATUS_ANOTHER . " THEN 1 ELSE null END) as total,
                COUNT(CASE WHEN claims.expired = 1 THEN 1 ELSE null END) as total_expired
            "))
            ->where('article_users.role_id', UserRoleEnum::INSPECTOR)
            ->where('article_users.user_id', Auth::user()->id)
            ->first()
            ->setAppends([]);
    }

    public function getStatisticsCount(
        ?int    $regionId,
        ?int    $districtId,
        ?int    $expired,
        ?string $dateFrom,
        ?string $dateTo
    )
    {
        return $this->claim->query()
            ->join('regions', 'regions.soato', '=', 'claims.region')
            ->join('districts', 'districts.soato', '=', 'claims.district')
            ->select(DB::raw("
                COUNT(CASE WHEN claims.status = " . ClaimStatuses::TASK_STATUS_ACCEPTANCE . " THEN 1 ELSE null END) as in_process,
                COUNT(CASE WHEN claims.status = " . ClaimStatuses::TASK_STATUS_ATTACH_OBJECT . " THEN 1 ELSE null END) as attach_object,
                COUNT(CASE WHEN claims.status = " . ClaimStatuses::TASK_STATUS_INSPECTOR . " THEN 1 ELSE null END) as inspector,
                COUNT(CASE WHEN claims.status = " . ClaimStatuses::TASK_STATUS_SENT_ORGANIZATION . " THEN 1 ELSE null END) as sent_organization,
                COUNT(CASE WHEN claims.status = " . ClaimStatuses::TASK_STATUS_DIRECTOR . " THEN 1 ELSE null END) as director,
                COUNT(CASE WHEN claims.status = " . ClaimStatuses::TASK_STATUS_OPERATOR . " THEN 1 ELSE null END) as operator,
                COUNT(CASE WHEN claims.status = " . ClaimStatuses::TASK_STATUS_SENT_ANOTHER_ORG . " THEN 1 ELSE null END) as minstroy,
                COUNT(CASE WHEN claims.status = " . ClaimStatuses::TASK_STATUS_ORGANIZATION_REJECTED . " THEN 1 ELSE null END) as organization_rejected,
                COUNT(CASE WHEN claims.status = " . ClaimStatuses::TASK_STATUS_CONFIRMED . " THEN 1 ELSE null END) as confirmed,
                COUNT(CASE WHEN claims.status = " . ClaimStatuses::TASK_STATUS_REJECTED . " THEN 1 ELSE null END) as rejected,
                COUNT(CASE WHEN claims.status = " . ClaimStatuses::TASK_STATUS_CANCELLED . " THEN 1 ELSE null END) as cancelled,
                COUNT(CASE WHEN claims.status = " . ClaimStatuses::RESEND . " THEN 1 ELSE null END) as resend,
                COUNT(CASE WHEN claims.status <> " . ClaimStatuses::TASK_STATUS_ANOTHER . " THEN 1 ELSE null END) as total,
                COUNT(CASE WHEN claims.expired = 1 THEN 1 ELSE null END) as total_expired
            "))
            ->when($dateFrom, function ($q) use ($dateFrom) {
                $q->whereDate('claims.created_at', '>=', $dateFrom);
            })
            ->when($dateTo, function ($q) use ($dateTo) {
                $q->whereDate('claims.created_at', '<=', $dateTo);
            })
            ->when($regionId, function ($q) use ($regionId) {
                $q->where('regions.id', $regionId);
            })
            ->when($districtId, function ($q) use ($districtId) {
                $q->where('districts.id', $districtId);
            })
            ->when($expired, function ($q) use ($expired) {
                $q->where('expired', $expired);
            })
            ->first()
            ->setAppends([]);
    }


    public function getStatistics(?int $regionId, ?int $districtId)
    {
        return $this->claim->query()
            ->join('regions', 'regions.soato', '=', 'claims.region')
            ->select(DB::raw("
                COUNT(CASE WHEN claims.status = " . ClaimStatuses::TASK_STATUS_ANOTHER . " THEN 1 ELSE null END) as new,
                COUNT(CASE WHEN claims.status = " . ClaimStatuses::TASK_STATUS_ACCEPTANCE . " THEN 1 ELSE null END) as in_process,
                COUNT(CASE WHEN claims.status = " . ClaimStatuses::TASK_STATUS_ACCEPTANCE . " AND claims.expired = 1 THEN 1 ELSE null END) as in_process_expired,
                COUNT(CASE WHEN claims.status = " . ClaimStatuses::TASK_STATUS_CONFIRMED . " THEN 1 ELSE null END) as confirmed,
                COUNT(CASE WHEN claims.status = " . ClaimStatuses::TASK_STATUS_CONFIRMED . " AND claims.expired = 1 THEN 1 ELSE null END) as confirmed_expired,
                COUNT(CASE WHEN claims.status = " . ClaimStatuses::TASK_STATUS_REJECTED . " THEN 1 ELSE null END) as rejected,
                COUNT(CASE WHEN claims.status = " . ClaimStatuses::TASK_STATUS_REJECTED . " AND claims.expired = 1 THEN 1 ELSE null END) as rejected_expired,
                COUNT(CASE WHEN claims.status = " . ClaimStatuses::TASK_STATUS_CANCELLED . " THEN 1 ELSE null END) as cancelled,
                COUNT(CASE WHEN claims.status = " . ClaimStatuses::TASK_STATUS_CANCELLED . " AND claims.expired = 1 THEN 1 ELSE null END) as cancelled_expired,
                COUNT(*) as total,
                COUNT(CASE WHEN claims.expired = 1 THEN 1 ELSE null END) as total_expired
            "))
            ->when($regionId, function ($q) use ($regionId) {
                $q->where('regions.id', $regionId);
            })
            ->first();
    }

    public function getClaimById(int $id, ?int $role_id)
    {
        if ($role_id == null){
            $claim =  $this->claim->query()
                ->join('responses', 'responses.task_id', '=', 'claims.guid')
                ->leftJoin('articles', 'articles.id', '=', 'claims.object_id')
                ->select([
                    'claims.id as id',
                    'claims.guid as gu_id',
                    'articles.task_id as object_task_id',
                    'responses.api as api_type',
                    'claims.district as district',
                    'claims.region as region',
                    'claims.status as status',
                    'claims.object_id as object_id',
                    DB::raw("(CASE WHEN claims.user_type = 'J' THEN claims.legal_name ELSE claims.ind_name END) as customer"),
                    DB::raw("(CASE WHEN claims.user_type = 'J' THEN claims.legal_tin ELSE claims.ind_pinfl END) as customer_inn"),
                    DB::raw("(CASE WHEN claims.property_owner = '2' THEN claims.ind_name ELSE null END) as property_owner"),
                    DB::raw("(CASE WHEN claims.property_owner = '2' THEN claims.ind_pinfl ELSE null END) as property_tin"),
                    'claims.expiry_date as expiry_date',
                    'claims.expired as expired',
                    'claims.current_node as current_node',
                    'claims.type_object_dic as watcher_type',
                    'claims.building_cadastral as building_cadastral',
                    'claims.building_address as building_address',
                    'claims.building_name as building_name',
                    'claims.building_type as building_type',
                    'claims.building_type_name as building_type_name',
                    'claims.user_type as user_type',
                    'claims.document_registration_based as document_registration_based',
                    'claims.object_project_user as object_project_user',
                    'claims.type_object_dic as type_object_dic',
                    'claims.cadastral_passport_object_file as cadastral_passport_object_file',
                    'claims.cadastral_passport_object_json as cadastral_passport_object_json',
                    'claims.cadastral_passport_object as cadastral_passport_object',
                    'claims.ownership_document as ownership_document',
                    'claims.act_acceptance_customer_file as act_acceptance_customer_file',
                    'claims.declaration_conformity_file as declaration_conformity_file',
                    'claims.conclusion_approved_planning_file as conclusion_approved_planning_file',
                    'claims.conclusion_approved_planning_file_autofill as conclusion_approved_planning_file_autofill',
                    'claims.building_cadastral as building_cadastral',
                    'claims.number_conclusion_project as number_conclusion_project',
                    'claims.end_date as end_date',
                    'claims.source_type as source_type',
                    'claims.created_at as created_at',
                    'claims.resend as resend',
                    'claims.my_home_id as my_home_id',
                ])
                ->with(['region', 'district', 'object', 'reviews.images', 'monitoring'])
                ->where('claims.id', $id)->first();

        }
        elseif ($role_id == UserRoleEnum::INSPECTOR->value){
            $claim =  $this->claim->query()
                ->join('responses', 'responses.task_id', '=', 'claims.guid')
                ->join('article_users', 'article_users.article_id', '=', 'claims.object_id')
                ->join('articles', 'articles.id', '=', 'claims.object_id')
                ->select([
                    'claims.id as id',
                    'claims.guid as gu_id',
                    'articles.task_id as object_task_id',
                    'responses.api as api_type',
                    'claims.district as district',
                    'claims.region as region',
                    'claims.status as status',
                    'claims.object_id as object_id',
                    DB::raw("(CASE WHEN claims.user_type = 'J' THEN claims.legal_name ELSE claims.ind_name END) as customer"),
                    DB::raw("(CASE WHEN claims.user_type = 'J' THEN claims.legal_tin ELSE claims.ind_pinfl END) as customer_inn"),
                    DB::raw("(CASE WHEN claims.property_owner = '2' THEN claims.ind_name ELSE null END) as property_owner"),
                    DB::raw("(CASE WHEN claims.property_owner = '2' THEN claims.ind_pinfl ELSE null END) as property_tin"),
                    'claims.expiry_date as expiry_date',
                    'claims.expired as expired',
                    'claims.current_node as current_node',
                    'claims.building_cadastral as building_cadastral',
                    'claims.building_address as building_address',
                    'claims.type_object_dic as watcher_type',
                    'claims.building_name as building_name',
                    'claims.building_type as building_type',
                    'claims.building_type_name as building_type_name',
                    'claims.user_type as user_type',
                    'claims.document_registration_based as document_registration_based',
                    'claims.object_project_user as object_project_user',
                    'claims.type_object_dic as type_object_dic',
                    'claims.cadastral_passport_object_file as cadastral_passport_object_file',
                    'claims.cadastral_passport_object_json as cadastral_passport_object_json',
                    'claims.ownership_document as ownership_document',
                    'claims.act_acceptance_customer_file as act_acceptance_customer_file',
                    'claims.declaration_conformity_file as declaration_conformity_file',
                    'claims.conclusion_approved_planning_file as conclusion_approved_planning_file',
                    'claims.cadastral_passport_object as cadastral_passport_object',
                    'claims.conclusion_approved_planning_file_autofill as conclusion_approved_planning_file_autofill',
                    'claims.building_cadastral as building_cadastral',
                    'claims.number_conclusion_project as number_conclusion_project',
                    'claims.end_date as end_date',
                    'claims.created_at as created_at',
                    'claims.source_type as source_type',
                    'claims.resend as resend',
                    'claims.my_home_id as my_home_id',
                ])
                ->with(['region', 'district', 'object', 'reviews.images', 'monitoring'])
                ->where('article_users.role_id', UserRoleEnum::INSPECTOR)
                ->where('article_users.user_id', Auth::user()->id)
                ->where('claims.id', $id)->first();
        }
        else{
            $claim = $this->claim->query()
                ->join('responses', 'responses.task_id', '=', 'claims.guid')
                ->join('articles', 'articles.id', '=', 'claims.object_id')
                ->join('claim_organization_reviews', 'claim_organization_reviews.claim_id', '=', 'claims.id')
                ->select([
                    'claims.id as id',
                    'claims.guid as gu_id',
                    'articles.task_id as object_task_id',
                    'responses.api as api_type',
                    'claims.district as district',
                    'claim_organization_reviews.id as review_id',
                    'claim_organization_reviews.answered_at as answered_at',
                    'claims.region as region',
                    'claims.type_object_dic as watcher_type',
                    'claims.status as status',
                    'claims.object_id as object_id',
                    DB::raw("(CASE WHEN claims.user_type = 'J' THEN claims.legal_name ELSE claims.ind_name END) as customer"),
                    DB::raw("(CASE WHEN claims.user_type = 'J' THEN claims.legal_tin ELSE claims.ind_pinfl END) as customer_inn"),
                    DB::raw("(CASE WHEN claims.property_owner = '2' THEN claims.ind_name ELSE null END) as property_owner"),
                    DB::raw("(CASE WHEN claims.property_owner = '2' THEN claims.ind_pinfl ELSE null END) as property_tin"),
                    'claims.expiry_date as expiry_date',
                    'claims.expired as expired',
                    'claims.current_node as current_node',
                    'claims.building_cadastral as building_cadastral',
                    'claims.building_address as building_address',
                    'claims.building_name as building_name',
                    'claims.building_type as building_type',
                    'claims.building_type_name as building_type_name',
                    'claims.user_type as user_type',
                    'claims.document_registration_based as document_registration_based',
                    'claims.object_project_user as object_project_user',
                    'claims.type_object_dic as type_object_dic',
                    'claims.cadastral_passport_object_file as cadastral_passport_object_file',
                    'claims.cadastral_passport_object_json as cadastral_passport_object_json',
                    'claims.ownership_document as ownership_document',
                    'claims.act_acceptance_customer_file as act_acceptance_customer_file',
                    'claims.declaration_conformity_file as declaration_conformity_file',
                    'claims.conclusion_approved_planning_file as conclusion_approved_planning_file',
                    'claims.cadastral_passport_object as cadastral_passport_object',
                    'claims.conclusion_approved_planning_file_autofill as conclusion_approved_planning_file_autofill',
                    'claims.building_cadastral as building_cadastral',
                    'claims.number_conclusion_project as number_conclusion_project',
                    'claims.end_date as end_date',
                    'claims.created_at as created_at',
                    'claims.source_type as source_type',
                    'claims.resend as resend',
                    'claims.my_home_id as my_home_id',
                ])
                ->with(['region', 'district', 'object', 'reviews.images', 'monitoring'])
                ->where('claims.id', $id)->whereIn('claim_organization_reviews.organization_id',  match ($role_id) {
                    45 => [15, 45],
                    46 => [16, 46],
                    default => [$role_id],
                })->first();
        }

        if ($claim) {
            $claim->reviews->each(function ($review) {
                $review->images->each(function ($image) {
                    $image->url = Storage::disk('public')->url($image->url);
                });
            });
        }
        return $claim;
    }

    public function getClaimByTaskId($taskId)
    {
            $claim =  $this->claim->query()
                ->join('responses', 'responses.task_id', '=', 'claims.guid')
                ->leftJoin('articles', 'articles.id', '=', 'claims.object_id')
                ->select([
                    'claims.id as id',
                    'claims.guid as gu_id',
                    'articles.task_id as object_task_id',
                    'responses.api as api_type',
                    'claims.district as district',
                    'claims.region as region',
                    'claims.status as status',
                    'claims.object_id as object_id',
                    DB::raw("(CASE WHEN claims.user_type = 'J' THEN claims.legal_name ELSE claims.ind_name END) as customer"),
                    DB::raw("(CASE WHEN claims.user_type = 'J' THEN claims.legal_tin ELSE claims.ind_pinfl END) as customer_inn"),
                    DB::raw("(CASE WHEN claims.property_owner = '2' THEN claims.ind_name ELSE null END) as property_owner"),
                    DB::raw("(CASE WHEN claims.property_owner = '2' THEN claims.ind_pinfl ELSE null END) as property_tin"),
                    'claims.expiry_date as expiry_date',
                    'claims.expired as expired',
                    'claims.current_node as current_node',
                    'claims.type_object_dic as watcher_type',
                    'claims.building_cadastral as building_cadastral',
                    'claims.building_address as building_address',
                    'claims.building_name as building_name',
                    'claims.building_type as building_type',
                    'claims.building_type_name as building_type_name',
                    'claims.user_type as user_type',
                    'claims.document_registration_based as document_registration_based',
                    'claims.object_project_user as object_project_user',
                    'claims.type_object_dic as type_object_dic',
                    'claims.cadastral_passport_object_file as cadastral_passport_object_file',
                    'claims.cadastral_passport_object_json as cadastral_passport_object_json',
                    'claims.cadastral_passport_object as cadastral_passport_object',
                    'claims.ownership_document as ownership_document',
                    'claims.act_acceptance_customer_file as act_acceptance_customer_file',
                    'claims.declaration_conformity_file as declaration_conformity_file',
                    'claims.conclusion_approved_planning_file as conclusion_approved_planning_file',
                    'claims.conclusion_approved_planning_file_autofill as conclusion_approved_planning_file_autofill',
                    'claims.building_cadastral as building_cadastral',
                    'claims.number_conclusion_project as number_conclusion_project',
                    'claims.end_date as end_date',
                    'claims.source_type as source_type',
                    'claims.created_at as created_at'
                ])
                ->with([
                    'region',
                    'district',
                    'object',
                    'reviews:id,monitoring_id,organization_id,answer,expiry_date,status,created_at,answered_at,claim_id',
                    'reviews.images',
                    'monitoring'
                ])
                ->where('claims.guid', $taskId)->first();

                if ($claim) {
                    $claim->reviews->each(function ($review) {
                        $review->images->each(function ($image) {
                            $image->url = Storage::disk('public')->url($image->url);
                        });
                    });
                }
        return $claim;
    }



    public function getClaimByGUID(int $guid)
    {
        return $this->claim->query()
            ->join('responses', 'responses.task_id', '=', 'claims.guid')
            ->select([
                'claims.id as id',
                'claims.guid as gu_id',
                'responses.api as api_type',
                'claims.district as district',
                'claims.region as region',
                'claims.status as status',
                'claims.object_id as object_id',
                DB::raw("(CASE WHEN claims.user_type = 'J' THEN claims.legal_name ELSE claims.ind_name END) as customer"),
                DB::raw("(CASE WHEN claims.user_type = 'J' THEN claims.legal_tin ELSE claims.ind_pinfl END) as customer_inn"),
                DB::raw("(CASE WHEN claims.property_owner = '2' THEN claims.ind_name ELSE null END) as property_owner"),
                DB::raw("(CASE WHEN claims.property_owner = '2' THEN claims.ind_pinfl ELSE null END) as property_tin"),
                'claims.expiry_date as expiry_date',
                'claims.expired as expired',
                'claims.current_node as current_node',
                'claims.building_cadastral as building_cadastral',
                'claims.building_address as building_address',
                'claims.building_name as building_name',
                'claims.building_type as building_type',
                'claims.building_type_name as building_type_name',
                'claims.user_type as user_type',
                'claims.document_registration_based as document_registration_based',
                'claims.object_project_user as object_project_user',
                'claims.type_object_dic as type_object_dic',
                'claims.cadastral_passport_object_file as cadastral_passport_object_file',
                'claims.cadastral_passport_object_json as cadastral_passport_object_json',
                'claims.ownership_document as ownership_document',
                'claims.act_acceptance_customer_file as act_acceptance_customer_file',
                'claims.declaration_conformity_file as declaration_conformity_file',
                'claims.conclusion_approved_planning_file as conclusion_approved_planning_file',
                'claims.building_cadastral as building_cadastral',
                'claims.number_conclusion_project as number_conclusion_project',
                'claims.end_date as end_date',
                'claims.created_at as created_at'
            ])
            ->with(['region', 'district', 'object'])
            ->where(['claims.guid' => $guid])->first();
    }

    public function getObjects(int $id, $filters, ?string $type)
    {
        $claim = $this->getClaimById(id: $id, role_id: null);

        if ($type == 'all') {
            $query = Article::query()
                ->join('regions', 'articles.region_id', '=', 'regions.id')
                ->where('regions.soato', $claim->region);
        } else {
            $query = Article::query()
                ->where('object_status_id', ObjectStatusEnum::PROGRESS)
                ->where(function ($query) use ($claim) {
                    $query->where('cadastral_number', $claim->building_cadastral);
                    //->orWhere('number_protocol', $claim->number_conclusion_project);
                });

            if (!$query->exists()) {
                $query = Article::query()
                    ->join('districts', 'articles.district_id', '=', 'districts.id')
                    ->where('districts.soato', $claim->district);
            }
        }

        $query->select('articles.*');
        $query->when(isset($filters['name']), function ($query) use ($filters) {
            $query->where(function ($subQuery) use ($filters) {
                $subQuery->searchByName($filters['name'])
                    ->orWhere(function ($subQuery) use ($filters) {
                        $subQuery->searchByOrganization($filters['name']);
                    })
                    ->orWhere(function ($subQuery) use ($filters) {
                        $subQuery->searchByTaskId($filters['name']);
                    });
            });
        });

        return $query->get();
    }


    public function getList(
        ?int    $regionId,
        ?int    $task_id,
        ?int    $object_task_id,
        ?string $name,
        ?string $customer,
        ?string $sender,
        ?int    $districtId,
        ?string $sortBy,
        ?int    $status,
        ?int    $expired,
        ?int    $role_id,
        ?string $start_date,
        ?string $end_date,
        ?int    $sphereId,
        ?int    $type,
    ): LengthAwarePaginator
    {
        $role = match ($role_id) {
            21, 20 => 46,
            23, 22 => 45,
            24 => 17,
            25 => 18,
            35 => 34,
            default => $role_id,
        };

        if ($role_id == null)
            return $this->claim->query()
                ->with(['object', 'region', 'district'])
                ->join('regions', 'regions.soato', '=', 'claims.region')
                ->leftJoin('articles', 'articles.id', '=', 'claims.object_id')
                ->join('districts', 'districts.soato', '=', 'claims.district')
                ->join('responses', 'responses.task_id', '=', 'claims.guid')
                ->when($regionId, function ($q) use ($regionId) {
                    $q->where('regions.id', $regionId);
                })
                ->when($districtId, function ($q) use ($districtId) {
                    $q->where('districts.id', $districtId);
                })
                ->when($task_id, function ($q) use ($task_id) {
                    $q->where('claims.guid', 'LIKE', '%' . $task_id . '%');
                })
                ->when($type, function ($q) use ($type) {
                    $q->whereHas('operator', function ($sub) use ($type) {
                        $sub->whereRaw("
                            EXISTS (
                                SELECT 1
                                FROM jsonb_array_elements(operator_answer) elem
                                WHERE (elem->>'type')::int = ?
                            )
                        ", [$type]);
                    });
                })
                ->when($object_task_id, function ($q) use ($object_task_id) {
                    $q->where('articles.task_id', 'LIKE', '%' . $object_task_id . '%');
                })
                ->when($sphereId, function ($q) use ($sphereId) {
                    $q->where('articles.sphere_id', $sphereId);
                })
                ->when($name, function ($q) use ($name) {
                    $q->where('claims.building_name', 'iLIKE', '%' . $name . '%');
                })
                ->when($customer, function ($q) use ($customer) {
                    $q->where('claims.legal_name', 'iLIKE', '%' . $customer . '%')->orWhere('claims.ind_name', 'iLIKE', '%' . $customer . '%');
                })
                ->when($sender, function ($q) use ($sender) {
                    $q->where('claims.legal_name', 'iLIKE', '%' . $sender . '%')->orWhere('claims.ind_name', 'iLIKE', '%' . $sender . '%');
                })
                ->when($status, function ($q) use ($status) {
                    $q->where('claims.status', $status);
                })
                ->when($start_date || $end_date, function ($query) use ($start_date, $end_date) {
                    $startDate = ($start_date) ? $start_date . ' 00:00:00' : null;
                    $endDate = ($end_date) ? $end_date . ' 23:59:59' : null;

                    if ($startDate && $endDate) {
                        $query->whereBetween('claims.created_at', [$startDate, $endDate]);
                    } elseif ($start_date) {
                        $query->where('claims.created_at', '>=', $startDate);
                    } elseif ($end_date) {
                        $query->where('claims.created_at', '<=', $endDate);
                    }
                })
                ->when($expired, function ($q) use ($expired) {
                    $q->where('claims.expired', $expired);
                })
                ->where('claims.status', '<>', ClaimStatuses::TASK_STATUS_ANOTHER)
                ->groupBy('claims.id', 'responses.api', 'articles.task_id')
                ->when(function () use ($status) {
                    return in_array($status, [15, 20, 9]);
                }, function ($q) {
                    $q->orderByRaw("claims.end_date IS NULL, claims.end_date DESC");
                }, function ($q) use ($sortBy) {
                    $q->orderBy('claims.created_at', strtoupper($sortBy));
                })
                ->select([
                    'claims.id as id',
                    'claims.guid as gu_id',
                    'articles.task_id as object_task_id',
                    'responses.api as api_type',
                    'claims.district as district',
                    'claims.region as region',
                    'claims.status as status',
                    'claims.object_id as object_id',
                    DB::raw("(CASE WHEN claims.user_type = 'J' THEN claims.legal_name ELSE claims.ind_name END) as customer"),
                    DB::raw("(CASE WHEN claims.user_type = 'J' THEN claims.legal_tin ELSE claims.ind_pinfl END) as customer_inn"),
                    DB::raw("(CASE WHEN claims.property_owner = '2' THEN claims.ind_name ELSE null END) as property_owner"),
                    DB::raw("(CASE WHEN claims.property_owner = '2' THEN claims.ind_pinfl ELSE null END) as property_tin"),
                    'claims.expiry_date as expiry_date',
                    'claims.expired as expired',
                    'claims.current_node as current_node',
                    'claims.building_cadastral as building_cadastral',
                    'claims.building_address as building_address',
                    'claims.type_object_dic as watcher_type',
                    'claims.building_name as building_name',
                    'claims.building_type as building_type',
                    'claims.building_type_name as building_type_name',
                    'claims.user_type as user_type',
                    'claims.document_registration_based as document_registration_based',
                    'claims.object_project_user as object_project_user',
                    'claims.type_object_dic as type_object_dic',
                    'claims.cadastral_passport_object_file as cadastral_passport_object_file',
                    'claims.cadastral_passport_object_json as cadastral_passport_object_json',
                    'claims.cadastral_passport_object as cadastral_passport_object',
                    'claims.conclusion_approved_planning_file_autofill as conclusion_approved_planning_file_autofill',
                    'claims.ownership_document as ownership_document',
                    'claims.act_acceptance_customer_file as act_acceptance_customer_file',
                    'claims.declaration_conformity_file as declaration_conformity_file',
                    'claims.conclusion_approved_planning_file as conclusion_approved_planning_file',
                    'claims.building_cadastral as building_cadastral',
                    'claims.number_conclusion_project as number_conclusion_project',
                    'claims.end_date as end_date',
                    'claims.created_at as created_at'
                ])
                ->paginate(request()->get('per_page'));
        elseif ($role_id == UserRoleEnum::INSPECTOR->value)
            return $this->claim->query()
                ->with(['object', 'region', 'district', 'operator'])
                ->join('regions', 'regions.soato', '=', 'claims.region')
                ->join('articles', 'articles.id', '=', 'claims.object_id')
                ->join('article_users', 'article_users.article_id', '=', 'claims.object_id')
                ->join('districts', 'districts.soato', '=', 'claims.district')
                ->join('responses', 'responses.task_id', '=', 'claims.guid')
                ->when($regionId, function ($q) use ($regionId) {
                    $q->where('regions.id', $regionId);
                })
                ->when($districtId, function ($q) use ($districtId) {
                    $q->where('districts.id', $districtId);
                })
                ->when($task_id, function ($q) use ($task_id) {
                    $q->where('claims.guid', 'LIKE', '%' . $task_id . '%');
                })
                ->when($type, function ($q) use ($type) {
                    $q->whereHas('operator', function ($sub) use ($type) {
                        $sub->whereRaw("
                            EXISTS (
                                SELECT 1
                                FROM jsonb_array_elements(operator_answer) elem
                                WHERE (elem->>'type')::int = ?
                            )
                        ", [$type]);
                    });
                })
                ->when($object_task_id, function ($q) use ($object_task_id) {
                    $q->where('articles.task_id', 'LIKE', '%' . $object_task_id . '%');
                })
                ->when($name, function ($q) use ($name) {
                    $q->where('claims.building_name', 'iLIKE', '%' . $name . '%');
                })
                ->when($customer, function ($q) use ($customer) {
                    $q->where('claims.legal_name', 'iLIKE', '%' . $customer . '%')->orWhere('claims.ind_name', 'iLIKE', '%' . $customer . '%');
                })
                ->when($sender, function ($q) use ($sender) {
                    $q->where('claims.legal_name', 'iLIKE', '%' . $sender . '%')->orWhere('claims.ind_name', 'iLIKE', '%' . $sender . '%');
                })
                ->when($status, function ($q) use ($status) {
                    $q->where('claims.status', $status);
                })
                ->when($expired, function ($q) use ($expired) {
                    $q->where('claims.expired', $expired);
                })
                ->where('article_users.role_id', UserRoleEnum::INSPECTOR)
                ->where('article_users.user_id', Auth::user()->id)
                ->groupBy('claims.id', 'responses.api', 'articles.task_id')
                ->when(function () use ($status) {
                    return in_array($status, [15, 20, 9]);
                }, function ($q) {
                    $q->orderByRaw("claims.end_date IS NULL, claims.end_date DESC");
                }, function ($q) use ($sortBy) {
                    $q->orderBy('claims.created_at', strtoupper($sortBy));
                })
                ->select([
                    'claims.id as id',
                    'claims.guid as gu_id',
                    'articles.task_id as object_task_id',
                    'responses.api as api_type',
                    'claims.district as district',
                    'claims.region as region',
                    'claims.status as status',
                    'claims.object_id as object_id',
                    DB::raw("(CASE WHEN claims.user_type = 'J' THEN claims.legal_name ELSE claims.ind_name END) as customer"),
                    DB::raw("(CASE WHEN claims.user_type = 'J' THEN claims.legal_tin ELSE claims.ind_pinfl END) as customer_inn"),
                    DB::raw("(CASE WHEN claims.property_owner = '2' THEN claims.ind_name ELSE null END) as property_owner"),
                    DB::raw("(CASE WHEN claims.property_owner = '2' THEN claims.ind_pinfl ELSE null END) as property_tin"),
                    'claims.expiry_date as expiry_date',
                    'claims.expired as expired',
                    'claims.current_node as current_node',
                    'claims.building_cadastral as building_cadastral',
                    'claims.type_object_dic as watcher_type',
                    'claims.building_address as building_address',
                    'claims.building_name as building_name',
                    'claims.building_type as building_type',
                    'claims.building_type_name as building_type_name',
                    'claims.user_type as user_type',
                    'claims.document_registration_based as document_registration_based',
                    'claims.object_project_user as object_project_user',
                    'claims.type_object_dic as type_object_dic',
                    'claims.cadastral_passport_object_file as cadastral_passport_object_file',
                    'claims.cadastral_passport_object_json as cadastral_passport_object_json',
                    'claims.cadastral_passport_object as cadastral_passport_object',
                    'claims.conclusion_approved_planning_file_autofill as conclusion_approved_planning_file_autofill',
                    'claims.ownership_document as ownership_document',
                    'claims.act_acceptance_customer_file as act_acceptance_customer_file',
                    'claims.declaration_conformity_file as declaration_conformity_file',
                    'claims.conclusion_approved_planning_file as conclusion_approved_planning_file',
                    'claims.building_cadastral as building_cadastral',
                    'claims.number_conclusion_project as number_conclusion_project',
                    'claims.end_date as end_date',
                    'claims.created_at as created_at'
                ])
                ->paginate(request()->get('per_page'));
        else
            return $this->claim->query()
                ->with(['object', 'region', 'district', 'operator'])
                ->join('regions', 'regions.soato', '=', 'claims.region')
                ->join('articles', 'articles.id', '=', 'claims.object_id')
                ->join('claim_organization_reviews', 'claim_organization_reviews.claim_id', '=', 'claims.id')
                ->join('districts', 'districts.soato', '=', 'claims.district')
                ->join('responses', 'responses.task_id', '=', 'claims.guid')
                ->when($regionId, function ($q) use ($regionId) {
                    $q->where('regions.id', $regionId);
                })
                ->when($type, function ($q) use ($type) {
                    $q->whereHas('operator', function ($sub) use ($type) {
                        $sub->whereRaw("
                            EXISTS (
                                SELECT 1
                                FROM jsonb_array_elements(operator_answer) elem
                                WHERE (elem->>'type')::int = ?
                            )
                        ", [$type]);
                    });
                })
                ->when($districtId, function ($q) use ($districtId) {
                    $q->where('districts.id', $districtId);
                })
                ->when($task_id, function ($q) use ($task_id) {
                    $q->where('claims.guid', 'LIKE', '%' . $task_id . '%');
                })
                ->when($object_task_id, function ($q) use ($object_task_id) {
                    $q->where('articles.task_id', 'LIKE', '%' . $object_task_id . '%');
                })
                ->when($name, function ($q) use ($name) {
                    $q->where('claims.building_name', 'iLIKE', '%' . $name . '%');
                })
                ->when($customer, function ($q) use ($customer) {
                    $q->where('claims.legal_name', 'iLIKE', '%' . $customer . '%')->orWhere('claims.ind_name', 'iLIKE', '%' . $customer . '%');
                })
                ->when($sender, function ($q) use ($sender) {
                    $q->where('claims.legal_name', 'iLIKE', '%' . $sender . '%')->orWhere('claims.ind_name', 'iLIKE', '%' . $sender . '%');
                })
                ->when($status, function ($q) use ($status) {
                    ($status == 1) ? $q->whereNull('claim_organization_reviews.answered_at') : $q->whereNotNull('claim_organization_reviews.answered_at');
                })
                ->when($expired, function ($q) use ($expired) {
                    $q->where('claims.expired', $expired);
                })
                ->whereIn(
                    'claim_organization_reviews.organization_id',
                    match ($role) {
                        45 => [15, 45],
                        46 => [16, 46],
                        default => [$role],
                    }
                )
                ->groupBy('claims.id', 'responses.api', 'claim_organization_reviews.id', 'articles.task_id')
                ->when(function () use ($status) {
                    return in_array($status, [15, 20, 9]);
                }, function ($q) {
                    $q->orderByRaw("claims.end_date IS NULL, claims.end_date DESC");
                }, function ($q) use ($sortBy) {
                    $q->orderBy('claims.created_at', strtoupper($sortBy));
                })
                ->select([
                    'claims.id as id',
                    'claims.guid as gu_id',
                    'articles.task_id as object_task_id',
                    'responses.api as api_type',
                    'claim_organization_reviews.id as review_id',
                    'claims.district as district',
                    'claims.region as region',
                    'claims.status as status',
                    'claims.object_id as object_id',
                    DB::raw("(CASE WHEN claims.user_type = 'J' THEN claims.legal_name ELSE claims.ind_name END) as customer"),
                    DB::raw("(CASE WHEN claims.user_type = 'J' THEN claims.legal_tin ELSE claims.ind_pinfl END) as customer_inn"),
                    DB::raw("(CASE WHEN claims.property_owner = '2' THEN claims.ind_name ELSE null END) as property_owner"),
                    DB::raw("(CASE WHEN claims.property_owner = '2' THEN claims.ind_pinfl ELSE null END) as property_tin"),
                    'claims.expiry_date as expiry_date',
                    'claims.expired as expired',
                    'claims.current_node as current_node',
                    'claims.building_cadastral as building_cadastral',
                    'claims.building_address as building_address',
                    'claims.building_name as building_name',
                    'claims.building_type as building_type',
                    'claims.building_type_name as building_type_name',
                    'claims.type_object_dic as watcher_type',
                    'claims.user_type as user_type',
                    'claims.document_registration_based as document_registration_based',
                    'claims.cadastral_passport_object as cadastral_passport_object',
                    'claims.conclusion_approved_planning_file_autofill as conclusion_approved_planning_file_autofill',
                    'claims.object_project_user as object_project_user',
                    'claims.type_object_dic as type_object_dic',
                    'claims.cadastral_passport_object_file as cadastral_passport_object_file',
                    'claims.cadastral_passport_object_json as cadastral_passport_object_json',
                    'claims.ownership_document as ownership_document',
                    'claims.act_acceptance_customer_file as act_acceptance_customer_file',
                    'claims.declaration_conformity_file as declaration_conformity_file',
                    'claims.conclusion_approved_planning_file as conclusion_approved_planning_file',
                    'claims.building_cadastral as building_cadastral',
                    'claims.number_conclusion_project as number_conclusion_project',
                    'claims.end_date as end_date',
                    'claims.created_at as created_at'
                ])
                ->paginate(request()->get('per_page'));

    }

    public function createClaim($claimGov, $expiryDate)
    {
        $info = $claimGov->entities->CompletedBuildingsRegistrationCadastral;
        $resend = null;

        $status = ClaimStatuses::TASK_STATUS_ANOTHER;
        if ($claimGov->task->current_node == "direction-statement-object")
            $status = ClaimStatuses::TASK_STATUS_ACCEPTANCE;
        if ($claimGov->task->current_node == "answer-other-institutions"){
            $status = ClaimStatuses::TASK_STATUS_SENT_ORGANIZATION;
            if ($claimGov->task->status === 'resend') {
                $claim = Claim::query()
                    ->with('monitoring:id,claim_id,inspector_answer')
                    ->where('guid', $claimGov->task->id)
                    ->first();

                $status = $claim?->monitoring?->inspector_answer == 15
                    ? ClaimStatuses::TASK_STATUS_INSPECTOR
                    : ClaimStatuses::TASK_STATUS_OPERATOR;
                $resend = true;

                (new HistoryService('claim_histories'))->createHistory(
                    guId: $claimGov->task->id,
                    status: $status,
                    type: LogType::CLAIM_CONSUMER_CHANGE,
                    date: null,
                    comment: 'Ariza taxrirlandi'
                );
            }
        }
        if ($claimGov->task->current_node == "conclusion-minstroy")
            $status = ClaimStatuses::TASK_STATUS_SENT_ANOTHER_ORG;
        if ($claimGov->task->current_node == "inactive" && $claimGov->task->status == "not_active")
            $status = ClaimStatuses::TASK_STATUS_CANCELLED;
        if ($claimGov->task->current_node == "change-application" && $claimGov->task->status == "waiting_sub_user")
            $status = ClaimStatuses::RESEND;

        if ($status == ClaimStatuses::TASK_STATUS_ACCEPTANCE) {
            (new HistoryService('claim_histories'))->createHistory(
                guId: $claimGov->task->id,
                status: $status,
                type: LogType::TASK_HISTORY,
                date: null
            );
        }


        if ($claimGov->task->current_node == "inactive" && $claimGov->task->status == "processed") {
            $status = ClaimStatuses::TASK_STATUS_CONFIRMED;
            (new HistoryService('claim_histories'))->createHistory(
                guId: $claimGov->task->id,
                status: ClaimStatuses::TASK_STATUS_ACCEPTANCE,
                type: LogType::TASK_HISTORY,
                date: $claimGov->task->created_date
            );
            (new HistoryService('claim_histories'))->createHistory(
                guId: $claimGov->task->id,
                status: ClaimStatuses::TASK_STATUS_CONFIRMED,
                type: LogType::TASK_HISTORY,
                date: $claimGov->task->last_update
            );
        }

        if ($claimGov->task->current_node == "inactive" && $claimGov->task->status == "rejected") {
            $status = ClaimStatuses::TASK_STATUS_REJECTED;
            (new HistoryService('claim_histories'))->createHistory(
                guId: $claimGov->task->id,
                status: ClaimStatuses::TASK_STATUS_ACCEPTANCE,
                type: LogType::TASK_HISTORY,
                date: $claimGov->task->created_date
            );
            (new HistoryService('claim_histories'))->createHistory(
                guId: $claimGov->task->id,
                status: ClaimStatuses::TASK_STATUS_REJECTED,
                type: LogType::TASK_HISTORY,
                date: $claimGov->task->last_update
            );
        }

        $claimAdd = Claim::query()->updateOrCreate(
            [
                'guid' => $claimGov->task->id,
            ],
            [
                'created_date_mygov' => $claimGov->task->created_date,
                'updated_date_mygov' => $claimGov->task->last_update,
                'status_mygov' => $claimGov->task->status,
                'current_node' => $claimGov->task->current_node,
                'operator_org' => $claimGov->task->operator_org ?? null,
                'user_type' => $info->user_type->real_value,

                'end_date' => in_array($status, [
                    ClaimStatuses::TASK_STATUS_REJECTED,
                    ClaimStatuses::TASK_STATUS_CONFIRMED,
                    ClaimStatuses::TASK_STATUS_CANCELLED,
                ])
                    ? $claimGov->task->last_update
                    : null,

                'district' => $info->district->real_value,
                'resend' => $resend,
                'region' => $info->region->real_value,
                'building_cadastral' => $info->building_cadastral->real_value,

                'legal_name' => $info->legal_name->real_value,
                'legal_tin' => $info->legal_tin->real_value,
                'legal_email' => $info->legal_email->real_value,
                'legal_address' => $info->legal_address->real_value,
                'legal_phone' => $info->legal_phone->real_value,

                'building_name' => $info->building_name->real_value
                    ?? $info->name_building_project->real_value,

                'conclusion_approved_planning_file_autofill'
                => $info->conclusion_approved_planning_file_autofill->real_value,

                'property_owner' => $info->property_owner->real_value,
                'building_address' => $info->building_address->real_value,
                'building_type' => $info->building_type->real_value,
                'building_type_name' => $info->building_type->value,

                'ind_pinfl' => $info->ind_pinfl->real_value,
                'ind_passport' => $info->ind_passport->real_value,
                'ind_address' => $info->ind_address->real_value,
                'ind_name' => $info->ind_name->real_value,

                'tin_project_organization' => $info->tin_project_organization->real_value,
                'document_registration_based' => $info->document_registration_based->real_value,
                'object_project_user' => $info->object_project_user->real_value,
                'type_object_dic' => $info->type_object_dic->real_value,

                'cadastral_passport_object_file'
                => $info->cadastral_passport_object_file->real_value,

                'cadastral_passport_object'
                => $info->cadastral_passport_object->real_value,

                'cadastral_passport_object_json'
                => $info->source_type->real_value === 'MKD'
                    ? $info->cadastral_passport_object_json->real_value
                    : null,

                'ownership_document' => $info->ownership_document->real_value,
                'act_acceptance_customer_file'
                => $info->act_acceptance_customer_file->real_value,

                'declaration_conformity_file'
                => $info->declaration_conformity_file->real_value,

                'conclusion_approved_planning_file'
                => $info->conclusion_approved_planning_file->real_value,

                'number_conclusion_project'
                => $info->number_conclusion_project->real_value,

                'source_type' => $info->source_type->real_value,
                'status' => $status,

                'created_at' => in_array($status, [
                    ClaimStatuses::TASK_STATUS_ACCEPTANCE,
                    ClaimStatuses::TASK_STATUS_ANOTHER,
                ])
                    ? now()
                    : $claimGov->task->created_date,

                'updated_at' => in_array($status, [
                    ClaimStatuses::TASK_STATUS_ACCEPTANCE,
                    ClaimStatuses::TASK_STATUS_ANOTHER,
                ])
                    ? now()
                    : $claimGov->task->last_update,
            ]
        );

        return $claimAdd;

    }

    public function createMonitoring($blocks, $organizations, $id, $object_id)
    {
        return ClaimMonitoring::query()->create(
            [
                'blocks' => json_encode($blocks),
                'organizations' => json_encode($organizations),
                'claim_id' => $id,
                'object_id' => $object_id
            ]
        );
    }

    public function createOrganizationReview($claim_id, $monitoring_id, $organization_id, $expiry_date)
    {
        ClaimOrganizationReview::query()->updateOrCreate(
            [
                'claim_id' => $claim_id,
                'monitoring_id' => $monitoring_id,
                'organization_id' => $organization_id,
            ],
            [
                'expiry_date' => $expiry_date
            ]

        );
    }

    public function updateConclusionOrganization(array $data, int $id, bool $status, $pkcs7, $images = [])
    {
        $claimGov = ClaimOrganizationReview::query()->find($id);
        $claimGov->update(
            [
                'answered_at' => Carbon::now(),
                'status' => $status,
                'answer' => base64_encode(gzcompress(json_encode($data), 9)),
                'pkcs7' => $pkcs7
            ]
        );


        if ($claimGov->organization_id == UserRoleEnum::NOGIRONLAR_JAM->value){
            $claimGov->images()->delete();

            if (!empty($images)) {
                foreach ($images as $image) {
                    $imagePath = $image->store('claim-review', 'public');
                    $claimGov->images()->create([
                        'url' => $imagePath,
                    ]);
                }
            }
        }



    }

    public function manualConfirmByDirector(int $object_id, string $comment, string $file)
    {
        DB::table('claim_manual_confirmed_objects')->insertGetId(
            [
                'object_id' => $object_id,
                'comment' => $comment,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
                'file' => $file,
                'user_id' => Auth::user()->id
            ]
        );
    }
}
