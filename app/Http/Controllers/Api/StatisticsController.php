<?php

namespace App\Http\Controllers\Api;

use App\Enums\LogType;
use App\Enums\ObjectStatusEnum;
use App\Enums\UserRoleEnum;
use App\Exports\ClaimExcel;
use App\Exports\ClaimReviewExport;
use App\Exports\ClaimTaskExcel;
use App\Exports\DxaResponsesExport;
use App\Exports\RegulationReportExport;
use App\Helpers\ClaimStatuses;
use App\Models\Article;
use App\Models\Block;
use App\Models\CheckListAnswer;
use App\Models\Claim;
use App\Models\ClaimMonitoring;
use App\Models\ClaimOrganizationReview;
use App\Models\District;
use App\Models\Region;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Facades\Excel;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\HttpFoundation\StreamedJsonResponse;

class StatisticsController extends BaseController
{

    public function statistics(Request $request): JsonResponse
    {
        try {
            $startDate = $request->get('date_from');
            $endDate = $request->get('date_to');

            $regionId = $request->get('region_id');

            $regions = $regionId
                ? District::query()->where('region_id', $regionId)->get(['id', 'name_uz'])
                : Region::all(['id', 'name_uz']);

            $group = $regionId ? 'district_id' : 'region_id';


            $userCounts = User::query()
                ->selectRaw($group . ', COUNT(*) as count')
                ->leftJoin('user_roles', 'user_roles.user_id', '=', 'users.id')
                ->where('user_roles.role_id', 3)
                ->groupBy($group)
                ->pluck('count', $group);

            $articleCounts = $this->getGroupedCounts(Article::query(), $group . ', object_status_id, difficulty_category_id', [$group, 'object_status_id', 'difficulty_category_id'], $startDate, $endDate)->groupBy($group);


            $monitoringCounts = Article::query()
                ->selectRaw($group . ', COUNT(monitorings.id) as count')
                ->leftJoin('monitorings', 'articles.id', '=', 'monitorings.object_id')
                ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('monitorings.created_at', [$startDate, $endDate]);
                })
                ->when(\request('program_id'), function ($query) {
                    $query->where('articles.program_id', \request('program_id'));
                })
                ->where('created_by_role', UserRoleEnum::INSPECTOR->value)
                ->where(function ($q) {
                    $q->whereNotNull('work_in_progress')
                        ->orWhereNotNull('constant_checklist');
                })
                ->groupBy('articles.' . $group)
                ->pluck('count', $group);

            $locationChangesCounts = Article::query()
                ->selectRaw($group . ', COUNT(*) as count')
                ->leftJoin('article_histories', 'articles.id', '=', 'article_histories.gu_id')
                ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('article_histories.created_at', [$startDate, $endDate]);
                })
                ->when(\request('program_id'), function ($query) {
                    $query->where('articles.program_id', \request('program_id'));
                })
                ->where('type', LogType::CHANGE_OBJECT_LOCATION)
                ->groupBy('articles.' . $group)
                ->pluck('count', $group);





            $regulationCounts = $this->getRegulationCounts(relation: 'regulations', groupBy: $group, startDate: $startDate, endDate: $endDate);


            $eliminatedRegulations = $this->getRegulationCounts(relation: 'regulations', groupBy: $group, status: [6, 8], startDate: $startDate, endDate: $endDate);
            $inProgressRegulations = $this->getRegulationCounts(relation: 'regulations', groupBy: $group, status: [1, 2, 3, 4, 5], startDate: $startDate, endDate: $endDate);
            $notExecutionRegulations = $this->getRegulationCounts(relation: 'regulations', groupBy: $group, status: [7], startDate: $startDate, endDate: $endDate);

            $costumerRegulationsEliminated = $this->getRegulationCounts(relation: 'regulations', groupBy: $group, roleId: 6, status: [6, 8], startDate: $startDate, endDate: $endDate);
            $customerInProgressRegulations = $this->getRegulationCounts(relation: 'regulations', groupBy: $group, roleId: 6, status: [1, 2, 3, 4, 5], startDate: $startDate, endDate: $endDate);
            $costumerNotExecutionRegulations = $this->getRegulationCounts(relation: 'regulations', groupBy: $group, roleId: 6, status: [7], startDate: $startDate, endDate: $endDate);

            $manageRegulationsEliminated = $this->getRegulationCounts(relation: 'regulations', groupBy: $group, roleId: 5, status: [6, 8], startDate: $startDate, endDate: $endDate);
            $manageInProgressRegulations = $this->getRegulationCounts(relation: 'regulations', groupBy: $group, roleId: 5, status: [1, 2, 3, 4, 5], startDate: $startDate, endDate: $endDate);
            $manageNotExecutionRegulations = $this->getRegulationCounts(relation: 'regulations', groupBy: $group, roleId: 5, status: [7], startDate: $startDate, endDate: $endDate);

            $authorRegulationsEliminated = $this->getRegulationCounts(relation: 'regulations', groupBy: $group, roleId: 7, status: [6, 8], startDate: $startDate, endDate: $endDate);
            $authorInProgressRegulations = $this->getRegulationCounts(relation: 'regulations', groupBy: $group, roleId: 7, status: [1, 2, 3, 4, 5], startDate: $startDate, endDate: $endDate);
            $authorNotExecutionRegulations = $this->getRegulationCounts(relation: 'regulations', groupBy: $group, roleId: 7, status: [7], startDate: $startDate, endDate: $endDate);
            $administratively = $this->getRegulationCounts(relation: 'regulations', groupBy: $group, startDate: $startDate, endDate: $endDate, lawyerStatus: 3);


            //TEXNADZOR
            $technical_supervisor_all_regulations = $this->getRegulationCounts(relation: 'regulations', groupBy: $group, status: [6, 8], startDate: $startDate, endDate: $endDate, created_by_role_id: UserRoleEnum::TEXNIK);
            $technical_supervisor_in_progress_regulations = $this->getRegulationCounts(relation: 'regulations', groupBy: $group, status: [1, 2, 3, 4, 5], startDate: $startDate, endDate: $endDate, created_by_role_id: UserRoleEnum::TEXNIK);
            $technical_supervisor_execution_regulations = $this->getRegulationCounts(relation: 'regulations', groupBy: $group, status: [7], startDate: $startDate, endDate: $endDate, created_by_role_id: UserRoleEnum::TEXNIK);

            $to_manage_regulations_eliminated = $this->getRegulationCounts(relation: 'regulations', groupBy: $group, roleId: 5, status: [6, 8], startDate: $startDate, endDate: $endDate, created_by_role_id: UserRoleEnum::TEXNIK);
            $to_manage_regulations_in_progress = $this->getRegulationCounts(relation: 'regulations', groupBy: $group, roleId: 5, status: [1, 2, 3, 4, 5], startDate: $startDate, endDate: $endDate, created_by_role_id: UserRoleEnum::TEXNIK);
            $to_manage_regulations_not_execution = $this->getRegulationCounts(relation: 'regulations', groupBy: $group, roleId: 5, status: [7], startDate: $startDate, endDate: $endDate, created_by_role_id: UserRoleEnum::TEXNIK);

            $to_author_regulations_eliminated = $this->getRegulationCounts(relation: 'regulations', groupBy: $group, roleId: 7, status: [6, 8], startDate: $startDate, endDate: $endDate, created_by_role_id: UserRoleEnum::TEXNIK);
            $to_author_regulations_in_progress = $this->getRegulationCounts(relation: 'regulations', groupBy: $group, roleId: 7, status: [1, 2, 3, 4, 5], startDate: $startDate, endDate: $endDate, created_by_role_id: UserRoleEnum::TEXNIK);
            $to_author_regulations_not_execution = $this->getRegulationCounts(relation: 'regulations', groupBy: $group, roleId: 7, status: [7], startDate: $startDate, endDate: $endDate, created_by_role_id: UserRoleEnum::TEXNIK);

            ////////////////////////////////////////////////////////////////////////


            //MUALLIF
            $author_all_regulations = $this->getRegulationCounts(relation: 'regulations', groupBy: $group, status: [6, 8], startDate: $startDate, endDate: $endDate, created_by_role_id: UserRoleEnum::MUALLIF);
            $author_in_progress_regulations = $this->getRegulationCounts(relation: 'regulations', groupBy: $group, status: [1, 2, 3, 4, 5], startDate: $startDate, endDate: $endDate, created_by_role_id: UserRoleEnum::MUALLIF);
            $author_execution_regulations = $this->getRegulationCounts(relation: 'regulations', groupBy: $group, status: [7], startDate: $startDate, endDate: $endDate, created_by_role_id: UserRoleEnum::MUALLIF);

            $to_customer_regulations_eliminated = $this->getRegulationCounts(relation: 'regulations', groupBy: $group, roleId: 6, status: [6, 8], startDate: $startDate, endDate: $endDate, created_by_role_id: UserRoleEnum::MUALLIF);
            $to_customer_regulations_in_progress = $this->getRegulationCounts(relation: 'regulations', groupBy: $group, roleId: 6, status: [1, 2, 3, 4, 5], startDate: $startDate, endDate: $endDate, created_by_role_id: UserRoleEnum::MUALLIF);
            $to_customer_regulations_not_execution = $this->getRegulationCounts(relation: 'regulations', groupBy: $group, roleId: 6, status: [7], startDate: $startDate, endDate: $endDate, created_by_role_id: UserRoleEnum::MUALLIF);

            $to_technical_supervisor_regulations_eliminated = $this->getRegulationCounts(relation: 'regulations', groupBy: $group, roleId: 5, status: [6, 8], startDate: $startDate, endDate: $endDate, created_by_role_id: UserRoleEnum::MUALLIF);
            $to_technical_supervisor_regulations_in_progress = $this->getRegulationCounts(relation: 'regulations', groupBy: $group, roleId: 5, status: [1, 2, 3, 4, 5], startDate: $startDate, endDate: $endDate, created_by_role_id: UserRoleEnum::MUALLIF);
            $to_technical_supervisor_regulations_not_execution = $this->getRegulationCounts(relation: 'regulations', groupBy: $group, roleId: 5, status: [7], startDate: $startDate, endDate: $endDate, created_by_role_id: UserRoleEnum::MUALLIF);

            ////////////////////////////////////////////////////////////////////////

            $data = $regions->map(function ($region) use (
                $userCounts,
                $articleCounts,
                $monitoringCounts,
                $locationChangesCounts,
                $regulationCounts,
                $eliminatedRegulations,
                $inProgressRegulations,
                $notExecutionRegulations,
                $costumerRegulationsEliminated,
                $customerInProgressRegulations,
                $costumerNotExecutionRegulations,
                $manageRegulationsEliminated,
                $manageInProgressRegulations,
                $manageNotExecutionRegulations,
                $authorRegulationsEliminated,
                $authorInProgressRegulations,
                $authorNotExecutionRegulations,
                $administratively,
                $technical_supervisor_all_regulations,
                $technical_supervisor_in_progress_regulations,
                $technical_supervisor_execution_regulations,
                $to_manage_regulations_eliminated,
                $to_manage_regulations_in_progress,
                $to_manage_regulations_not_execution,
                $to_author_regulations_eliminated,
                $to_author_regulations_in_progress,
                $to_author_regulations_not_execution,
                $author_all_regulations,
                $author_in_progress_regulations,
                $author_execution_regulations,
                $to_customer_regulations_eliminated,
                $to_customer_regulations_in_progress,
                $to_customer_regulations_not_execution,
                $to_technical_supervisor_regulations_eliminated,
                $to_technical_supervisor_regulations_in_progress,
                $to_technical_supervisor_regulations_not_execution
            ) {
                $regionId = $region->id;
                $regionArticles = $articleCounts->get($regionId, collect());

                return [
                    'id' => $region->id,
                    'name' => $region->name_uz,
                    'inspector_count' => $userCounts->get($regionId, 0),
                    'object_count' => $regionArticles->sum('count'),
                    'suspended' => $regionArticles->where('object_status_id', ObjectStatusEnum::SUSPENDED)->sum('count'),
                    'submitted' => $regionArticles->where('object_status_id', ObjectStatusEnum::SUBMITTED)->sum('count'),
                    'frozen' => $regionArticles->where('object_status_id', ObjectStatusEnum::FROZEN)->sum('count'),
                    'progress' => $regionArticles->where('object_status_id', ObjectStatusEnum::PROGRESS)->sum('count'),
                    'category_1' => $regionArticles->where('difficulty_category_id', 1)->where('object_status_id', ObjectStatusEnum::PROGRESS)->sum('count'),
                    'category_2' => $regionArticles->where('difficulty_category_id', 2)->where('object_status_id', ObjectStatusEnum::PROGRESS)->sum('count'),
                    'category_3' => $regionArticles->where('difficulty_category_id', 3)->where('object_status_id', ObjectStatusEnum::PROGRESS)->sum('count'),
                    'category_4' => $regionArticles->where('difficulty_category_id', 4)->where('object_status_id', ObjectStatusEnum::PROGRESS)->sum('count'),
                    'monitoring_count' => $monitoringCounts->get($regionId, 0),
                    'location_change_count' => $locationChangesCounts->get($regionId, 0),
                    'regulation_count' => $regulationCounts->get($regionId, 0),
                    'regulation_eliminated' => $eliminatedRegulations->get($regionId, 0),
                    'regulation_progress' => $inProgressRegulations->get($regionId, 0),
                    'regulation_not_execution' => $notExecutionRegulations->get($regionId, 0),
                    'costumer_regulation_eliminated' => $costumerRegulationsEliminated->get($regionId, 0),
                    'customer_regulation_progress' => $customerInProgressRegulations->get($regionId, 0),
                    'customer_regulation_not_execution' => $costumerNotExecutionRegulations->get($regionId, 0),
                    'manage_regulation_eliminated' => $manageRegulationsEliminated->get($regionId, 0),
                    'manage_regulation_progress' => $manageInProgressRegulations->get($regionId, 0),
                    'manage_regulation_not_execution' => $manageNotExecutionRegulations->get($regionId, 0),
                    'author_regulation_eliminated' => $authorRegulationsEliminated->get($regionId, 0),
                    'author_regulation_progress' => $authorInProgressRegulations->get($regionId, 0),
                    'author_regulation_not_execution' => $authorNotExecutionRegulations->get($regionId, 0),
                    'technical_supervisor_all_regulations' => $technical_supervisor_all_regulations->get($regionId, 0),
                    'technical_supervisor_in_progress_regulations' => $technical_supervisor_in_progress_regulations->get($regionId, 0),
                    'technical_supervisor_execution_regulations' => $technical_supervisor_execution_regulations->get($regionId, 0),
                    'to_manage_regulations_eliminated' => $to_manage_regulations_eliminated->get($regionId, 0),
                    'to_manage_regulations_in_progress' => $to_manage_regulations_in_progress->get($regionId, 0),
                    'to_manage_regulations_not_execution' => $to_manage_regulations_not_execution->get($regionId, 0),
                    'to_author_regulations_eliminated' => $to_author_regulations_eliminated->get($regionId, 0),
                    'to_author_regulations_in_progress' => $to_author_regulations_in_progress->get($regionId, 0),
                    'to_author_regulations_not_execution' => $to_author_regulations_not_execution->get($regionId, 0),
                    'author_all_regulations' => $author_all_regulations->get($regionId, 0),
                    'author_in_progress_regulations' => $author_in_progress_regulations->get($regionId, 0),
                    'author_execution_regulations' => $author_execution_regulations->get($regionId, 0),
                    'to_customer_regulations_eliminated' => $to_customer_regulations_eliminated->get($regionId, 0),
                    'to_customer_regulations_in_progress' => $to_customer_regulations_in_progress->get($regionId, 0),
                    'to_customer_regulations_not_execution' => $to_customer_regulations_not_execution->get($regionId, 0),
                    'to_technical_supervisor_regulations_eliminated' => $to_technical_supervisor_regulations_eliminated->get($regionId, 0),
                    'to_technical_supervisor_regulations_in_progress' => $to_technical_supervisor_regulations_in_progress->get($regionId, 0),
                    'to_technical_supervisor_regulations_not_execution' => $to_technical_supervisor_regulations_not_execution->get($regionId, 0),
                    'administratively' => $administratively->get($regionId, 0),

                ];
            });

            return $this->sendSuccess($data->values(), 'Data retrieved successfully');
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getLine());
        }
    }


    private function getGroupedCounts($query, $selectRaw, $groupBy, $startDate = null, $endDate = null)
    {
        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }
        if (\request('program_id')) {
            $query->where('program_id', \request('program_id'));
        }

        return $query
            ->selectRaw("$selectRaw, COUNT(*) as count")
            ->groupBy(...$groupBy)
            ->get();
    }

    private function getGroupedBlocksCounts($groupField, $startDate = null, $endDate = null)
    {
        $query = Block::query()
            ->join('articles', 'articles.id', '=', 'blocks.article_id')
            ->join('claims', 'claims.object_id', '=', 'articles.id')
            ->where('articles.sphere_id', 57)
            ->where('claims.status', ClaimStatuses::TASK_STATUS_CONFIRMED);

        if ($startDate && $endDate) {
            $query->whereBetween('claims.created_at', [$startDate, $endDate]);
        }

        return $query
            ->selectRaw("
            articles.$groupField,
            COUNT(blocks.id) AS total_blocks,
            SUM(NULLIF(blocks.count_apartments, '')::INTEGER) AS total_apartments
            ")
            ->groupBy("articles.$groupField")
            ->get();
    }

    private function getGroupedClaimArea($groupField, $startDate = null, $endDate = null)
    {
        $query = Claim::query()
            ->join('articles', 'articles.id', '=', 'claims.object_id')
            ->join('claim_operators', 'claims.id', '=', 'claim_operators.claim_id')
            ->join('claim_monitoring', 'claims.id', '=', 'claim_monitoring.claim_id')
            ->where('articles.sphere_id', 57)
            ->where('claims.status', ClaimStatuses::TASK_STATUS_CONFIRMED);

        if ($startDate && $endDate) {
            $query->whereBetween('claims.created_at', [$startDate, $endDate]);
        }

        return $query
            ->join(
                DB::raw("
                LATERAL (
                    SELECT elem
                    FROM jsonb_array_elements(claim_operators.operator_answer) AS elem
                    WHERE elem->>'type' = '1'
                ) AS operator_data
            "),
                DB::raw('TRUE'),
                DB::raw('TRUE')
            )
            ->selectRaw("
            articles.$groupField,

            SUM(COALESCE(REPLACE(operator_data.elem->>'total_area', ' ', '')::numeric, 0)) AS total_area,

            SUM(COALESCE(REPLACE(operator_data.elem->>'area', ' ', '')::numeric, 0)) AS area,

            SUM(COALESCE(REPLACE(operator_data.elem->>'living_area', ' ', '')::numeric, 0)) AS total_living_area,

            SUM(COALESCE(REPLACE(operator_data.elem->>'total_use_area', ' ', '')::numeric, 0)) AS total_use_area,

            SUM(COALESCE(REPLACE(operator_data.elem->>'apartment_count', ' ', '')::numeric, 0)) AS accepted_apartments,

            SUM(COALESCE(json_array_length(claim_monitoring.blocks), 0)) AS accepted_blocks
        ")
            ->groupBy("articles.$groupField")
            ->get();
    }


    private function getRegulationCounts($relation, $groupBy, $roleId = null, $status = [], $startDate = null, $endDate = null, $lawyerStatus = null, $created_by_role_id = null)
    {
        $created_by_role_id = $created_by_role_id ?? 3;
        return Article::query()
            ->where('object_status_id', '!=', ObjectStatusEnum::CANCELED)
            ->selectRaw($groupBy . ', COUNT(' . $relation . '.id) as count')
            ->leftJoin($relation, 'articles.id', '=', $relation . '.object_id')
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate, $relation) {
                $query->whereBetween($relation . '.created_at', [$startDate, $endDate]);
            })
            ->when($roleId, function ($query) use ($roleId) {
                $query->where('role_id', $roleId);
            })
            ->when(!empty($status), function ($query) use ($status) {
                $query->whereIn('regulation_status_id', $status);
            })
            ->when($lawyerStatus, function ($query) use ($lawyerStatus) {
                $query->where('lawyer_status_id', $lawyerStatus);
            })
            ->when(\request('program_id'), function ($query) {
                $query->where('articles.program_id', \request('program_id'));
            })
            ->where('created_by_role_id', $created_by_role_id)
            ->groupBy($groupBy)
            ->pluck('count', $groupBy);
    }

    private const REPORT_COLUMN_SELECTS = [
        'sphere' => 'articles.sphere_id',
        'district' => 'articles.district_id',
        'status' => 'articles.object_status_id',
        'difficulty_category' => 'articles.difficulty_category_id',
        'funding_source' => 'articles.funding_source_id',
        'cadaster_number' => 'articles.cadastral_number',
        'organization_name' => 'articles.organization_name',
        'inspector' => null,
        'organization_pinfl' => null,
        'participants' => null,
        'blocks' => null,
        'monitorings' => null,
        'regulations' => null,
    ];
    private const REPORT_BASE_SELECT = ['articles.id', 'articles.region_id', 'articles.construction_cost'];
    private const REPORT_CHUNK_SIZE = 2000;
    private const REPORT_MAX_PER_PAGE = 10000;
    public function reports(Request $request): JsonResponse|StreamedJsonResponse
    {
        try {
            $columns = $this->reportColumns($request);
            $filters = $request->only(['region', 'user', 'date_from', 'date_to', 'object_status', 'inspector']);

            $query = $this->reportQuery($columns, $filters);

            $perPage = (int)$request->input('per_page', 0);

            if ($perPage > 0) {
                return $this->reportPaginated(
                    $query,
                    min($perPage, self::REPORT_MAX_PER_PAGE),
                    (int)$request->input('page', 1)
                );
            }

            return $this->reportStreamed($query);
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getLine());
        }
    }
    private function reportColumns(Request $request): array
    {
        $columns = $request->input('columns', []);

        if (is_string($columns)) {
            $columns = explode(',', $columns);
        }

        $columns = array_filter(array_map('trim', (array)$columns), 'strlen');
        $allowed = array_merge(array_keys(self::REPORT_COLUMN_SELECTS), $this->articleColumnListing());

        return array_values(array_unique(array_intersect($columns, $allowed)));
    }

    private function articleColumnListing(): array
    {
        static $listing = null;

        return $listing ??= Schema::getColumnListing('articles');
    }

    private function reportSelect(array $columns): array
    {
        $select = self::REPORT_BASE_SELECT;

        foreach ($columns as $column) {
            if (array_key_exists($column, self::REPORT_COLUMN_SELECTS)) {
                if (self::REPORT_COLUMN_SELECTS[$column] !== null) {
                    $select[] = self::REPORT_COLUMN_SELECTS[$column];
                }

                continue;
            }

            $select[] = 'articles.' . $column;
        }

        return array_values(array_unique($select));
    }

    private function reportQuery(array $columns, array $filters): Builder
    {
        $query = Article::query()
            ->select($this->reportSelect($columns))
            ->where('articles.object_status_id', '!=', ObjectStatusEnum::CANCELED)
            ->with(['region' => function ($query) {
                $query->select('regions.id as id', 'regions.name_uz');
            }]);

        $this->applyReportRelations($query, $columns, $filters);
        $this->applyReportFilters($query, $filters);

        return $query;
    }
    private function applyReportRelations(Builder $query, array $columns, array $filters): void
    {
        $has = fn(string $column): bool => in_array($column, $columns, true);

        $with = [];
        $withCount = [];

        if ($has('inspector')) {
            $with['inspector'] = fn($q) => $q->select('users.surname', 'users.name', 'users.middle_name', 'users.id as user_id', 'users.phone');
        }

        if ($has('organization_pinfl')) {
            $with['organization'] = fn($q) => $q->select('users.pinfl');
        }

        if ($has('participants')) {
            $with['users'] = fn($q) => $q
                ->select('users.surname', 'users.name', 'users.middle_name', 'users.id as user_id', 'users.phone')
                ->whereIn('role_id', [5, 7, 6]);
        }

        if ($has('district')) {
            $with['district'] = fn($q) => $q->select('districts.id', 'districts.name_uz');
        }

        if ($has('sphere')) {
            $with['sphere'] = fn($q) => $q->select('spheres.name_uz', 'spheres.id as id');
        }

        if ($has('difficulty_category')) {
            $with['difficulty'] = fn($q) => $q->select('difficulty_categories.difficulty', 'difficulty_categories.id as id');
        }

        if ($has('funding_source')) {
            $with['fundingSource'] = fn($q) => $q->select('funding_sources.description', 'funding_sources.id as id');
        }

        if ($has('status')) {
            $with['objectStatus'] = fn($q) => $q->select('object_statuses.name', 'object_statuses.id as id');
        }

        if ($has('regulations')) {
            $with['regulations'] = fn($q) => $q
                ->selectRaw($this->regulationSummarySelect())
                ->when($this->hasReportDateRange($filters), function ($q) use ($filters) {
                    return $q->whereBetween('regulations.created_at', [$filters['date_from'], $filters['date_to']]);
                })
                ->groupBy('object_id');
        }

        if ($has('blocks')) {
            $withCount[] = 'blocks';
        }

        if ($has('monitorings')) {
            $withCount['monitorings as monitoring_count'] = fn($q) => $q
                ->where('created_by_role', UserRoleEnum::INSPECTOR->value)
                ->where(function ($q) {
                    $q->whereNotNull('work_in_progress')
                        ->orWhereNotNull('constant_checklist');
                })
                ->when($this->hasReportDateRange($filters), function ($q) use ($filters) {
                    return $q->whereBetween('monitorings.created_at', [$filters['date_from'], $filters['date_to']]);
                });
        }

        if ($with) {
            $query->with($with);
        }

        if ($withCount) {
            $query->withCount($withCount);
        }
    }

    private function hasReportDateRange(array $filters): bool
    {
        return isset($filters['date_from']) && isset($filters['date_to']);
    }

    private function regulationSummarySelect(): string
    {
        return 'object_id, COUNT(id) as all_count,
            SUM(CASE WHEN created_by_role_id = 3 THEN 1 ELSE 0 END) as inspector_count,
            SUM(CASE WHEN regulation_status_id IN (6, 8) AND created_by_role_id = 3 THEN 1 ELSE 0 END) as eliminated_count,
            SUM(CASE WHEN regulation_status_id IN (1, 2, 3, 4, 5) AND created_by_role_id = 3 THEN 1 ELSE 0 END) as progress_count,
            SUM(CASE WHEN regulation_status_id = 7 AND created_by_role_id = 3 THEN 1 ELSE 0 END) as not_execution_count,
            SUM(CASE WHEN regulation_status_id IN (6, 8) AND role_id = 6 AND created_by_role_id = 3 THEN 1 ELSE 0 END) as costumer_eliminated_count,
            SUM(CASE WHEN regulation_status_id IN (1, 2, 3, 4, 5) AND role_id = 6 AND created_by_role_id = 3 THEN 1 ELSE 0 END) as costumer_progress_count,
            SUM(CASE WHEN regulation_status_id = 7 AND role_id = 6 AND created_by_role_id = 3 THEN 1 ELSE 0 END) as costumer_not_execution_count,
            SUM(CASE WHEN regulation_status_id IN (6, 8) AND role_id = 5 AND created_by_role_id = 3 THEN 1 ELSE 0 END) as manage_eliminated_count,
            SUM(CASE WHEN regulation_status_id IN (1, 2, 3, 4, 5) AND role_id = 5 AND created_by_role_id = 3 THEN 1 ELSE 0 END) as manage_progress_count,
            SUM(CASE WHEN regulation_status_id = 7 AND role_id = 5 AND created_by_role_id = 3 THEN 1 ELSE 0 END) as manage_not_execution_count,
            SUM(CASE WHEN regulation_status_id IN (6, 8) AND role_id = 7 AND created_by_role_id = 3 THEN 1 ELSE 0 END) as author_eliminated_count,
            SUM(CASE WHEN regulation_status_id IN (1, 2, 3, 4, 5) AND role_id = 7 AND created_by_role_id = 3 THEN 1 ELSE 0 END) as author_progress_count,
            SUM(CASE WHEN regulation_status_id = 7 AND role_id = 7 AND created_by_role_id = 3 THEN 1 ELSE 0 END) as author_not_execution_count,
            SUM(CASE WHEN lawyer_status_id = 3 AND created_by_role_id = 3 THEN 1 ELSE 0 END) as administratively';
    }

    private function applyReportFilters(Builder $query, array $filters): void
    {
        $query
            ->when(filled($filters['region'] ?? null), function ($q) use ($filters) {
                return $q->where('articles.region_id', $filters['region']);
            })
            ->when(filled($filters['object_status'] ?? null), function ($q) use ($filters) {
                return $q->where('articles.object_status_id', $filters['object_status']);
            });
        $byPivot = function ($userId, ?int $roleId) use ($query) {
            $query->whereIn('articles.id', function ($sub) use ($userId, $roleId) {
                $sub->select('article_users.article_id')
                    ->from('article_users')
                    ->join('users', 'users.id', '=', 'article_users.user_id')
                    ->whereNull('users.deleted_at')
                    ->where('article_users.user_id', $userId)
                    ->when($roleId !== null, function ($q) use ($roleId) {
                        return $q->where('article_users.role_id', $roleId);
                    });
            });
        };

        if (filled($filters['user'] ?? null)) {
            $byPivot($filters['user'], null);
        }

        if (filled($filters['inspector'] ?? null)) {
            $byPivot($filters['inspector'], UserRoleEnum::INSPECTOR->value);
        }
    }

    private function reportPaginated(Builder $query, int $perPage, int $page): JsonResponse
    {
        $paginator = $query->orderBy('articles.id')->paginate($perPage, ['*'], 'page', max(1, $page));

        $data = array_map(
            fn($article) => $article->setAppends([])->toArray(),
            $paginator->items()
        );

        return $this->sendSuccess($data, 'Data retrieved successfully', [
            'currentPage' => $paginator->currentPage(),
            'perPage' => $paginator->perPage(),
            'lastPage' => $paginator->lastPage(),
            'total' => $paginator->total(),
        ]);
    }

    private function reportStreamed(Builder $query): StreamedJsonResponse
    {
        return response()->streamJson([
            'success' => true,
            'message' => 'Data retrieved successfully',
            'result' => [
                'data' => $this->reportRows($query),
            ],
        ]);
    }
    private function reportRows(Builder $query): \Generator
    {
        foreach ($query->lazyById(self::REPORT_CHUNK_SIZE, 'articles.id', 'id') as $article) {
            yield $article->setAppends([])->toArray();
        }
    }

    public function regulationReport(Request $request)
    {
        try {
            $filters = $request->only(['date_from', 'date_to', 'status', 'region_id', 'role_id']);
            $dateFrom = request('date_from');
            $dateTo = request('date_to');

            $fileName = "regulations_{$dateFrom}_{$dateTo}.xlsx";

            return Excel::download(
                new RegulationReportExport($filters),
                $fileName
            );

        }catch (\Exception $exception){
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function claimStatistic(Request $request)
    {
        try {
            $startDate = $request->get('date_from')
                ? Carbon::parse($request->get('date_from'))->startOfDay()
                : now()->yesterday()->startOfDay();

            $endDate = $request->get('date_to')
                ? Carbon::parse($request->get('date_to'))->endOfDay()
                : now()->yesterday()->endOfDay();


            $regionId = $request->get('region_id');

            $regions = $regionId
                ? District::where('region_id', $regionId)->get(['id', 'name_uz'])
                : Region::all(['id', 'name_uz']);

            $group = $regionId ? 'district_id' : 'region_id';

            $blocks = $this->getGroupedBlocksCounts($group)->groupBy($group);
            $area = $this->getGroupedClaimArea($group)->groupBy($group);

            $blocksByYear = $this->getGroupedBlocksCounts($group, '2025-01-01', '2025-12-31')->groupBy($group);
            $areaByYear = $this->getGroupedClaimArea($group, '2025-01-01', '2025-12-31')->groupBy($group);

            $blocksByDate = $this->getGroupedBlocksCounts($group, $startDate, $endDate)->groupBy($group);
            $areaByDate = $this->getGroupedClaimArea($group, $startDate, $endDate)->groupBy($group);

            $safe = fn($collection) => $collection->first() ?? (object)[
                'total_blocks' => 0,
                'accepted_blocks' => 0,
                'total_apartments' => 0,
                'accepted_apartments' => 0,
                'total_area' => 0,
                'total_living_area' => 0,
            ];


            $data = $regions->map(function ($region) use (
                $safe,
                $blocks,
                $area,
                $blocksByYear,
                $areaByYear,
                $blocksByDate,
                $areaByDate
            ) {
                $id = $region->id;

                $current = $safe($blocks->get($id, collect()));
                $currentArea = $safe($area->get($id, collect()));


                $year = $safe($blocksByYear->get($id, collect()));
                $yearArea = $safe($areaByYear->get($id, collect()));

                $date = $safe($blocksByDate->get($id, collect()));
                $dateArea = $safe($areaByDate->get($id, collect()));


                return [
                    'id' => $region->id,
                    'name' => $region->name_uz,

                    // TOTAL
                    'total_blocks' => $current->total_blocks,
                    'accepted' => $currentArea->accepted_blocks,
                    'total_apartments' => $current->total_apartments,
                    'accepted_apartments' => $currentArea->accepted_apartments,
                    'total_area' => $currentArea->total_area,
                    'total_living_area' => $currentArea->total_living_area,

                    // BY YEAR
                    'by_year_total_blocks' => $year->total_blocks,
                    'by_year_accepted' => $yearArea->accepted_blocks,
                    'by_year_total_apartments' => $year->total_apartments,
                    'by_year_accepted_apartments' => $yearArea->accepted_apartments,
                    'by_year_total_area' => $yearArea->total_area,
                    'by_year_total_living_area' => $yearArea->total_living_area,

                    // BY DATE
                    'by_date_total_blocks' => $date->total_blocks,
                    'by_date_accepted' => $dateArea->accepted_blocks,
                    'by_date_total_apartments' => $date->total_apartments,
                    'by_date_accepted_apartments' => $dateArea->accepted_apartments,
                    'by_date_total_area' => $dateArea->total_area,
                    'by_date_total_living_area' => $dateArea->total_living_area,
                ];
            });


            return $this->sendSuccess($data->values(), 'Data retrieved successfully');

        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getLine());
        }
    }


    public function excelTask()
    {
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '-1');
        set_time_limit(0);

        $filters = request()->only(['region_id', 'start_date', 'end_date']);

        return Excel::download(
            new ClaimTaskExcel($filters),
            'statistic.xlsx'
        );
    }

    public function excel()
    {
        ini_set('max_execution_time', 300);
        $filters = request()->only(['region_id', 'start_date', 'end_date']);

        $claims = ClaimMonitoring::query()
            ->join('claims as c', 'c.id', '=', 'claim_monitoring.claim_id')
            ->join('articles as a', 'a.id', '=', 'c.object_id')
            ->join('regions as r', 'r.id', '=', 'a.region_id')
            ->join('districts as d', 'd.id', '=', 'a.district_id')
            ->when(isset($filters['region_id']), fn($q) => $q->where('r.id', $filters['region_id']))
            ->when(isset($filters['start_date']) || isset($filters['end_date']), function ($query) use ($filters) {
                $startDate = isset($filters['start_date']) ? $filters['start_date'] . ' 00:00:00' : null;
                $endDate = isset($filters['end_date']) ? $filters['end_date'] . ' 23:59:59' : null;

                $startDate = $startDate ? Carbon::parse($startDate)->format('Y-m-d') : null;
                $endDate = $endDate ? Carbon::parse($endDate)->format('Y-m-d') : null;

                if ($startDate && $endDate) {
                    $query->whereBetween('c.end_date', [$startDate, $endDate]);
                } elseif ($startDate) {
                    $query->where('c.end_date', '>=', $startDate);
                } elseif ($endDate) {
                    $query->where('c.end_date', '<=', $endDate);
                }
            })
//            ->where('c.guid', 304259723)
            ->where('c.status', ClaimStatuses::TASK_STATUS_CONFIRMED)
            ->whereNotNull('c.object_id')
            ->select([
                'claim_monitoring.*',
                'c.guid as ariza_raqami',
                'c.building_cadastral as cadaster',
                DB::raw("(CASE WHEN c.user_type = 'J' THEN c.legal_name ELSE c.ind_name END) as customer"),
                DB::raw("(CASE WHEN c.user_type = 'J' THEN c.legal_tin ELSE c.ind_pinfl END) as customer_inn"),
                'a.task_id as obyekt_raqami',
                'a.id as article_id',
                'a.name as obyekt_nomi',
                'r.name_uz as region_name',
                'd.name_uz as district_name',
                'c.end_date as end_date',
            ])
            ->get();

        $articleIds = $claims->pluck('article_id')->unique();
        $blocks = Block::query()
            ->whereIn('article_id', $articleIds)
            ->get()
            ->groupBy('article_id');

        $array = [];
        $count = 1;

        foreach ($claims as $claim) {
            $claimBlocks = $blocks[$claim->article_id] ?? collect();
            $blockCounts = [
                'noturar' => $claimBlocks->where('block_type_id', 1)->count(),
                'turar' => $claimBlocks->whereNotIn('block_type_id', [1, 25])->count(),
                'yakka' => $claimBlocks->where('block_type_id', 25)->count(),
            ];


            $meta = [];
            $countTurar = 0;
            $countNoturar = 0;
            $countYakka = 0;

            $blocksData = json_decode($claim->blocks, true);
            if (is_array($blocksData)) {
                foreach ($blocksData as $item) {
                    $block = $claimBlocks->where('id', $item)->first();
                    if (!$block) continue;

                    if ($block->block_type_id == 1) $countTurar++;
                    elseif ($block->block_type_id == 25) $countYakka++;
                    else $countNoturar++;

                    $meta[] = [
                        'type' => optional($block->type)->name,
//                        'count_apartments' => $block->count_apartments,
                    ];
                }
            }


            $operator = base64_decode($claim->operator_answer);
            $uncompressed = @gzuncompress($operator) ?: $operator;
            $areas = json_decode($uncompressed, true);


            if (!$areas) continue;

            $areaSum = $total_area = $total_use_area = $living_area = $countApartment = [
                'total' => 0, 'turar' => 0, 'noturar' => 0, 'yakka' => 0
            ];


            foreach ($areas as $area) {
                $type = match ($area['type']) {
                    "1" => 'turar',
                    "0" => 'noturar',
                    default => 'yakka',
                };

                $areaSum[$type] += $this->toNumber($area['area'] ?? null);
                $total_area[$type] += $this->toNumber($area['total_area'] ?? null);
                $total_use_area[$type] += $this->toNumber($area['total_use_area'] ?? null);
                $living_area[$type] += $this->toNumber($area['living_area'] ?? null);

                $areaSum['total'] += $this->toNumber($area['area'] ?? null);
                $total_area['total'] += $this->toNumber($area['total_area'] ?? null);
                $total_use_area['total'] += $this->toNumber($area['total_use_area'] ?? null);
                $living_area['total'] += $this->toNumber($area['living_area'] ?? null);

                $countApartment[$type] += $this->toNumber($area['apartment_count'] ?? null);
                $countApartment['total'] += $this->toNumber($area['apartment_count'] ?? null);
            }



            $tmpArray = [
                'tartib_raqami' => $count,
                'ariza_raqami' => $claim->ariza_raqami,
                'obyekt_nomi' => $claim->obyekt_nomi,
                'obyekt_raqami' => $claim->obyekt_raqami,
                'region_name' => $claim->region_name,
                'district_name' => $claim->district_name,
                'cadaster' => $claim->cadaster,
                'customer' => $claim->customer,
                'customer_inn' => $claim->customer_inn,
                'jami_honadon' => (string)$claimBlocks->sum('count_apartments'),
                'jami_block' => (string)$claimBlocks->count(),
                'noturar' => (string)$blockCounts['noturar'],
                'turar' => (string)$blockCounts['turar'],
                'yakka' => (string)$blockCounts['yakka'],
                'count_apartments' => (string)$countApartment['total'],
                'priyomka_jami_block' => (string)count($blocksData),
                'priyomka_noturar' => (string)$countNoturar,
                'priyomka_turar' => (string)$countTurar,
                'priyomka_yakka' => (string)$countYakka,

                'umumiy_maydon' => (string)$total_area['total'],
                'umumiy_noturar' => (string)$total_area['noturar'],
                'umumiy_turar' => (string)$total_area['turar'],
                'umumiy_yakka' => (string)$total_area['yakka'],

                'foydalanish_maydon' => (string)$total_use_area['total'],
                'foydalanish_noturar' => (string)$total_use_area['noturar'],
                'foydalanish_turar' => (string)$total_use_area['turar'],
                'foydalanish_yakka' => (string)$total_use_area['yakka'],

                'yashash_maydon' => (string)$living_area['total'],
                'yashash_noturar' => (string)$living_area['noturar'],
                'yashash_turar' => (string)$living_area['turar'],
                'yashash_yakka' => (string)$living_area['yakka'],

                'qurilish_osti_maydoni' => (string)$areaSum['total'],
                'qurilish_osti_noturar' => (string)$areaSum['noturar'],
                'qurilish_osti_turar' => (string)$areaSum['turar'],
                'qurilish_osti_yakka' => (string)$areaSum['yakka'],
            ];

            $array[] = $tmpArray;
            $count++;
        }

        return Excel::download(
            new ClaimExcel($array),
            'statistic.xlsx'
        );
    }

    private function toNumber($value): float
    {
        if ($value === null || $value === '') {
            return 0;
        }

        $value = (string) $value;

        $value = str_replace([" ", "\xc2\xa0"], "", $value);

        $value = str_replace(',', '.', $value);

        return is_numeric($value) ? (float) $value : 0;
    }

    public function registerList(Request $request)
    {
        try {
            ini_set('max_execution_time', 0);
            $filters = $request->only(['region', 'district', 'date_from', 'date_to', 'status', 'inspector', 'type']);
            return Excel::download(new DxaResponsesExport($filters), 'registers.xlsx');


        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getLine());
        }
    }

    public function orgAnswer()
    {
        $data = DB::select("
    SELECT
        ar.name as obyekt_nomi,
        cl.guid as ariza_raqami,
        cl.created_at as ariza_yuborilgan_sana,
        MAX(CASE WHEN o.name = 'mchs' THEN c.created_at END) AS mchsga_jonatilgan_vaqti,
        MAX(CASE WHEN o.name = 'mchs' THEN c.answered_at END) AS mchs,
        MAX(CASE WHEN o.name = 'mchs' THEN c.answer END) AS mchs_javobi,
        MAX(CASE WHEN o.name = 'mchs' THEN CASE WHEN c.status = true THEN 'ijobiy' ELSE 'salbiy' END END) AS mchs_javob_holati,
        MAX(CASE WHEN o.name = 'ses' THEN c.created_at END) AS sesga_jonatilgan_vaqti,
        MAX(CASE WHEN o.name = 'ses' THEN c.answered_at END) AS ses,
        MAX(CASE WHEN o.name = 'ses' THEN c.answer END) AS ses_javobi,
        MAX(CASE WHEN o.name = 'ses' THEN CASE WHEN c.status = true THEN 'ijobiy' ELSE 'salbiy' END END) AS ses_javob_holati,
        MAX(CASE WHEN o.name = 'nogiron' THEN c.created_at END) AS nogironga_jonatilgan_vaqti,
        MAX(CASE WHEN o.name = 'nogiron' THEN c.answered_at END) AS nogiron,
        MAX(CASE WHEN o.name = 'nogiron' THEN c.answer END) AS nogiron_javobi,
        MAX(CASE WHEN o.name = 'nogiron' THEN CASE WHEN c.status = true THEN 'ijobiy' ELSE 'salbiy' END END) AS nogiron_javob_holati,
        MAX(CASE WHEN o.name = 'nogiron2' THEN c.created_at END) AS nogiron2ga_jonatilgan_vaqti,
        MAX(CASE WHEN o.name = 'nogiron2' THEN c.answered_at END) AS nogiron2,
        MAX(CASE WHEN o.name = 'nogiron2' THEN c.answer END) AS nogiron2_javobi,
        MAX(CASE WHEN o.name = 'nogiron2' THEN CASE WHEN c.status = true THEN 'ijobiy' ELSE 'salbiy' END END) AS nogiron2_javob_holati,
        MAX(CASE WHEN o.name = 'kvartira' THEN c.created_at END) AS kvartiraga_jonatilgan_vaqti,
        MAX(CASE WHEN o.name = 'kvartira' THEN c.answered_at END) AS kvartira,
        MAX(CASE WHEN o.name = 'kvartira' THEN c.answer END) AS kvartira_javobi,
        MAX(CASE WHEN o.name = 'kvartira' THEN CASE WHEN c.status = true THEN 'ijobiy' ELSE 'salbiy' END END) AS kvartira_javob_holati,
        MAX(CASE WHEN o.name = 'ekologiya' THEN c.created_at END) AS ekologiyaga_jonatilgan_vaqti,
        MAX(CASE WHEN o.name = 'ekologiya' THEN c.answered_at END) AS ekologiya,
        MAX(CASE WHEN o.name = 'ekologiya' THEN c.answer END) AS ekologiya_javobi,
        MAX(CASE WHEN o.name = 'ekologiya' THEN CASE WHEN c.status = true THEN 'ijobiy' ELSE 'salbiy' END END) AS ekologiya_javob_holati
    FROM claim_organization_reviews c
    LEFT JOIN organizations o ON c.organization_id = o.id
    LEFT JOIN claims cl ON cl.id =  c.claim_id
    LEFT JOIN articles ar ON ar.id = cl.object_id
    GROUP BY cl.guid, ar.name,cl.created_at
");

        $decodedData = collect($data)->map(function ($item) {
            $row = (array)$item;

            foreach (['mchs', 'ses',
                         'nogiron',
                         'nogiron2',
                         'kvartira',
                         'ekologiya'] as $field) {
                if (!empty($row[$field . "_javobi"])) {
                    try {
                        $decoded = @gzuncompress(base64_decode($row[$field . "_javobi"]));
                        $json = json_decode($decoded, true);
                        $row[$field . "_javobi"] = is_array($json) ? json_encode($json[$field . "_conclusion_act"], JSON_UNESCAPED_UNICODE) : $json[$field . "_conclusion_act"];
                    } catch (\Exception $e) {
                        $row[$field . "_javobi"] = 'decode_error';
                    }
                }
            }
            return $row;
        });
//        return $decodedData;
        return Excel::download(new ClaimReviewExport($decodedData), 'claim_review.xlsx');
    }

    public function testClaim()
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', 0);

        $filters = request()->only(['region_id', 'start_date', 'end_date']);

        $query = Claim::query()
            ->leftJoin('articles as a', 'a.id', '=', 'claims.object_id')
            ->leftJoin('article_users as ar', function ($join) {
                $join->on('ar.article_id', '=', 'a.id')
                    ->where('ar.role_id', '=', 3);
            })
            ->leftJoin('users as u', 'u.id', '=', 'ar.user_id')
            ->leftJoin('regions as r', 'r.soato', '=', 'claims.region')
            ->leftJoin('districts as d', 'd.soato', '=', 'claims.district')
            ->when(isset($filters['region_id']), fn($q) => $q->where('r.id', $filters['region_id']))
            ->when(isset($filters['start_date']) || isset($filters['end_date']), function ($q) use ($filters) {
                $startDate = isset($filters['start_date']) ? Carbon::parse($filters['start_date'])->startOfDay() : null;
                $endDate = isset($filters['end_date']) ? Carbon::parse($filters['end_date'])->endOfDay() : null;

                if ($startDate && $endDate) {
                    $q->whereBetween('claims.created_at', [$startDate, $endDate]);
                } elseif ($startDate) {
                    $q->where('claims.created_at', '>=', $startDate);
                } elseif ($endDate) {
                    $q->where('claims.created_at', '<=', $endDate);
                }
            })
            ->where('claims.status', '<>', ClaimStatuses::TASK_STATUS_ANOTHER)
            ->select([
                'claims.guid as ariza_raqami',
                'claims.building_cadastral as cadaster',
                DB::raw("(CASE WHEN claims.user_type = 'J' THEN claims.legal_name ELSE claims.ind_name END) as customer"),
                DB::raw("(CASE WHEN claims.user_type = 'J' THEN claims.legal_tin ELSE claims.ind_pinfl END) as customer_inn"),
                'a.task_id as obyekt_raqami',
                'a.created_at as object_created_at',
                'a.id as article_id',
                'claims.building_name as obyekt_nomi',
                'r.name_uz as region_name',
                'd.name_uz as district_name',
                'claims.end_date as end_date',
                'claims.status',
                'claims.user_type',
                'claims.building_address',
                'claims.created_at',
                'claims.id',
                'a.difficulty_category_id',
                'a.object_type_id',
                'a.program_id',
                DB::raw("COALESCE(concat_ws(' ', u.name, u.surname)) AS user_name")
            ]);

        $claimIdsSubquery = (clone $query)->pluck('claims.id')->toArray();

        $claimHistories = DB::table('claim_histories')
            ->whereIn('gu_id', (clone $query)->pluck('ariza_raqami')->toArray())
            ->get()
            ->groupBy('gu_id');

        $questionAnswers = CheckListAnswer::whereIn('monitoring_id', $claimIdsSubquery)
            ->where('type', 2)
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('monitoring_id');

        $articleIds = (clone $query)->pluck('article_id')->unique()->toArray();
        $blocks = Block::whereIn('article_id', $articleIds)->get()->groupBy('article_id');

        $claimOrganizationReviews = ClaimOrganizationReview::whereIn('claim_id', $claimIdsSubquery)
            ->orderBy('answered_at', 'desc')
            ->get()
            ->groupBy('claim_id');

        $statuses = [
            ClaimStatuses::TASK_STATUS_ACCEPTANCE => 'Yangi',
            ClaimStatuses::TASK_STATUS_CONFIRMED => 'Hulosa berildi',
            ClaimStatuses::TASK_STATUS_ORGANIZATION_REJECTED => 'Tashkilot tomonidan rad etildi',
            ClaimStatuses::TASK_STATUS_DIRECTOR => 'Boshliqda',
            ClaimStatuses::TASK_STATUS_OPERATOR => 'Operatorda',
            ClaimStatuses::TASK_STATUS_REJECTED => 'Inspeksiya tomonidan rad etildi',
            ClaimStatuses::TASK_STATUS_ATTACH_OBJECT => 'Obyekt biriktirish',
            ClaimStatuses::TASK_STATUS_INSPECTOR => 'Inspektorda',
            ClaimStatuses::TASK_STATUS_SENT_ORGANIZATION => 'Tashkilotlarga yuborildi',
            ClaimStatuses::TASK_STATUS_SENT_ANOTHER_ORG => 'Boshqarmaga yuborildi',
            ClaimStatuses::TASK_STATUS_CANCELLED => 'Bekor qilindi'
        ];

        $categories = [
            1 => 'I',
            2 => 'II',
            3 => 'III',
            4 => 'IV'
        ];

        $roles = [
            0 => '-',
            12 => 'Boshliq',
            11 => 'Operator',
            15 => 'Tashkilot tomonidan',
            16 => 'Tashkilot tomonidan',
            17 => 'Tashkilot tomonidan',
            18 => 'Tashkilot tomonidan',
            19 => 'Tashkilot tomonidan',
            34 => 'Tashkilot tomonidan',
            45 => 'Tashkilot tomonidan',
            46 => 'Tashkilot tomonidan',
        ];

        $count = 1;


        return Excel::download(new class($query, $claimHistories, $questionAnswers, $blocks, $claimOrganizationReviews, $statuses, $categories, $roles) implements FromGenerator {

            private $query;
            private $claimHistories;
            private $questionAnswers;
            private $blocks;
            private $claimOrganizationReviews;
            private $statuses;
            private $categories;
            private $roles;
            private $count = 1;

            public function __construct($query, $claimHistories, $questionAnswers, $blocks, $claimOrganizationReviews, $statuses, $categories, $roles)
            {
                $this->query = $query;
                $this->claimHistories = $claimHistories;
                $this->questionAnswers = $questionAnswers;
                $this->blocks = $blocks;
                $this->claimOrganizationReviews = $claimOrganizationReviews;
                $this->statuses = $statuses;
                $this->categories = $categories;
                $this->roles = $roles;
            }

            public function generator(): \Generator
            {
                yield [
                    'Tartib raqami', 'Ariza raqami', 'Obyekt nomi', 'Obyekt raqami', 'Region',
                    'Tuman', 'Kadastr', 'Mijoz', 'Mijoz INN', 'Jami xonadon',
                    'Jami blok', 'Noturar blok', 'Turar blok', 'Yakka blok', 'Count apartments',
                    'Priyomka jami blok', 'Priyomka noturar', 'Priyomka turar', 'Priyomka yakka',
                    'Umumiy maydon', 'Umumiy noturar', 'Umumiy turar', 'Umumiy yakka',
                    'Foydalanish maydon', 'Foydalanish noturar', 'Foydalanish turar', 'Foydalanish yakka',
                    'Yashash maydon', 'Yashash noturar', 'Yashash turar', 'Yashash yakka',
                    'Qurilish osti maydoni', 'Qurilish osti noturar', 'Qurilish osti turar', 'Qurilish osti yakka',
                    'Inspeksiya kelgan vaqt', 'Tashkilotga yuborilgan sana', 'Oxirgi tashkilot sanasi',
                    'Inspektor hulosa vaqti', 'Inspektor', 'Yuridik/Jismoniy', 'Manzil', 'Obyekt turi',
                    'Toifasi', 'Obyekt yaratish sanasi', 'Inspektorga yuborilgan vaqt',
                    'Direktorga yuborilgan vaqt', 'Tugagan sana', 'Status', 'End role name',
                    'Reject reason', 'Dastur nomi', 'Quyosh panel', 'Konstruktiv checklist', 'Seysmik checklist'
                ];

                foreach ($this->query->cursor() as $claim) {
                    $history = $this->claimHistories[$claim->ariza_raqami] ?? collect();
                    $questions = $this->questionAnswers[$claim->id] ?? collect();

                    $inspectorConcDate = '-';
                    $inspectorSentDate = '-';
                    $directorSentDate = '-';
                    $historySentOrg = '-';
                    $rejectReason = '-';
                    $endRoleName = '-';

                    $konstruktiv = '-';
                    $quyosh_panel = '-';
                    $seysmik = '';

                    if (!in_array($claim->status, [
                        ClaimStatuses::TASK_STATUS_ACCEPTANCE,
                        ClaimStatuses::TASK_STATUS_ATTACH_OBJECT,
                        ClaimStatuses::TASK_STATUS_SENT_ORGANIZATION
                    ])) {
                        $historyConc = $history->first(fn($item) => data_get(json_decode($item->content, true), 'role') == 3);

                        if (in_array($claim->status, [ClaimStatuses::TASK_STATUS_CONFIRMED])) {
                            $konstruktiv = $questions->where('question_id', 69)->first()?->comment ?? '-';
                            $quyosh_panel = $questions->where('question_id', 68)->first()?->comment ?? '-';
                            $seysmik = $questions->where('question_id', 71)->first()?->comment ?? '-';
                        }

                        $historyReject = $history->first(fn($item) => in_array(
                            data_get(json_decode($item->content, true), 'status'),
                            [15, 9]
                        ));

                        $historyEnd = $history->first(fn($item) => in_array(
                            data_get(json_decode($item->content, true), 'status'),
                            [15, 9, 20, 10]
                        ));

                        $historySentInspector = $history->first(fn($item) => data_get(json_decode($item->content, true), 'status') == 5);
                        $historySentOrganization = $history->first(fn($item) => data_get(json_decode($item->content, true), 'status') == 4);
                        $historySentDirector = $history->first(fn($item) => data_get(json_decode($item->content, true), 'status') == 13);

                        if ($historyEnd)
                            $endRoleName = $this->roles[(int)json_decode($historyEnd->content, true)['role'] ?? 0];

                        if ($historySentInspector)
                            $inspectorSentDate = $historySentInspector->created_at;

                        if ($historySentDirector)
                            $directorSentDate = $historySentDirector->created_at;

                        if ($historySentOrganization)
                            $historySentOrg = $historySentOrganization->created_at;

                        if ($historyReject)
                            $rejectReason = json_decode($historyReject->content, true)['comment'] ?? '-';

                        if ($historyConc)
                            $inspectorConcDate = $historyConc->created_at;
                    }

                    $lastOrgConcDate = '-';
                    if (!in_array($claim->status, [ClaimStatuses::TASK_STATUS_ACCEPTANCE, ClaimStatuses::TASK_STATUS_ATTACH_OBJECT])) {
                        $concerns = $this->claimOrganizationReviews[$claim->id] ?? collect();
                        $conc = $concerns->first();
                        if ($conc)
                            $lastOrgConcDate = $conc->answered_at;
                    }

                    $claimBlocks = $this->blocks[$claim->article_id] ?? collect();

                    $blockCounts = [
                        'noturar' => $claimBlocks->where('block_type_id', 1)->count(),
                        'turar' => $claimBlocks->whereNotIn('block_type_id', [1, 25])->count(),
                        'yakka' => $claimBlocks->where('block_type_id', 25)->count(),
                    ];

                    $meta = [];
                    $countTurar = 0;
                    $countNoturar = 0;
                    $countYakka = 0;

                    $blocksData = ($claim->monitoring != null) ? json_decode($claim->monitoring->blocks, true) : null;

                    if (is_array($blocksData)) {
                        foreach ($blocksData as $item) {
                            $block = $claimBlocks->where('id', $item)->first();
                            if (!$block) continue;

                            if ($block->block_type_id == 1) $countTurar++;
                            elseif ($block->block_type_id == 25) $countYakka++;
                            else $countNoturar++;

                            $meta[] = [
                                'type' => optional($block->type)->name,
                                'count_apartments' => $block->count_apartments,
                            ];
                        }
                    }

                    $areas = null;

                    if ($claim->monitoring != null && $claim->monitoring->operator_answer != null) {
                        $operator = base64_decode($claim->monitoring->operator_answer);
                        $uncompressed = @gzuncompress($operator) ?: $operator;
                        $areas = json_decode($uncompressed, true);
                    }

                    $areaSum = $total_area = $total_use_area = $living_area = [
                        'total' => 0, 'turar' => 0, 'noturar' => 0, 'yakka' => 0
                    ];

                    if ($areas) {
                        foreach ($areas as $area) {
                            $type = match ($area['type']) {
                                1 => 'turar',
                                0 => 'noturar',
                                default => 'yakka',
                            };

                            $areaSum[$type] += $area['area'];
                            $total_area[$type] += $area['total_area'];
                            $total_use_area[$type] += $area['total_use_area'];
                            $living_area[$type] += $area['living_area'];

                            $areaSum['total'] += $area['area'];
                            $total_area['total'] += $area['total_area'];
                            $total_use_area['total'] += $area['total_use_area'];
                            $living_area['total'] += $area['living_area'];
                        }
                    }

                    yield [
                        $this->count,
                        $claim->ariza_raqami,
                        $claim->obyekt_nomi,
                        $claim->obyekt_raqami,
                        $claim->region_name,
                        $claim->district_name,
                        $claim->cadaster,
                        $claim->customer,
                        $claim->customer_inn,
                        (string)$claimBlocks->sum('count_apartments'),
                        (string)$claimBlocks->count(),
                        (string)$blockCounts['noturar'],
                        (string)$blockCounts['turar'],
                        (string)$blockCounts['yakka'],
                        (string)array_sum(array_column($meta, 'count_apartments')),
                        ($blocksData != null) ? (string)count($blocksData) : '-',
                        (string)$countNoturar,
                        (string)$countTurar,
                        (string)$countYakka,
                        (string)$total_area['total'],
                        (string)$total_area['noturar'],
                        (string)$total_area['turar'],
                        (string)$total_area['yakka'],
                        (string)$total_use_area['total'],
                        (string)$total_use_area['noturar'],
                        (string)$total_use_area['turar'],
                        (string)$total_use_area['yakka'],
                        (string)$living_area['total'],
                        (string)$living_area['noturar'],
                        (string)$living_area['turar'],
                        (string)$living_area['yakka'],
                        (string)$areaSum['total'],
                        (string)$areaSum['noturar'],
                        (string)$areaSum['turar'],
                        (string)$areaSum['yakka'],
                        $claim->created_at,
                        $historySentOrg ?? '-',
                        $lastOrgConcDate,
                        $inspectorConcDate,
                        $claim->user_name,
                        ($claim->user_type == 'J') ? 'Yuridik' : 'Jismoniy',
                        $claim->building_address,
                        ($claim->object_type_id == 1) ? 'Tarmoqli' : 'Bino',
                        ($claim->difficulty_category_id != null) ? $this->categories[$claim->difficulty_category_id] : '-',
                        $claim->object_created_at,
                        $inspectorSentDate,
                        $directorSentDate,
                        $claim->end_date,
                        $this->statuses[$claim->status] ?? '-',
                        $endRoleName,
                        $rejectReason,
                        '-',
                        $quyosh_panel,
                        $konstruktiv,
                        $seysmik,
                    ];

                    $this->count++;
                }
            }
        }, 'statistic.xlsx');

    }

    public function accountReport()
    {
        $filters = ['region_id', 'district_id'];

        $query = DB::table('articles as a')
            ->join('article_histories as ah', function ($join) {
                $join->on('a.id', '=', 'ah.gu_id')
                    ->where('ah.type', 11);
            })
            ->leftJoin('article_users as au', 'a.id', '=', 'au.article_id')
            ->leftJoin('users as u', 'au.user_id', '=', 'u.id')
            ->leftJoin('regions as r', 'a.region_id', '=', 'r.id')
            ->leftJoin('districts as d', 'a.district_id', '=', 'd.id')
            ->leftJoin('object_statuses as os', 'a.object_status_id', '=', 'os.id')
            ->leftJoin('funding_sources as f', 'a.funding_source_id', '=', 'f.id')
            ->select([
                'a.name',

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

                'r.name_uz as region',
                'd.name_uz as district',

                'a.location_building',

                DB::raw("
                MAX(
                    CASE
                        WHEN au.role_id = 3
                        THEN u.surname || ' ' || u.name || ' ' || u.middle_name
                    END
                ) AS inspektor
            "),

                DB::raw("
                COALESCE(a.task_id::text, a.manual_task_id) AS task_id
            "),

                'os.name as object_status',

                'a.created_at',

                'f.description as funding_source',

                'a.construction_cost',
                'a.price_supervision_service',

                DB::raw("
                ah.content->'additionalInfo'->>'amount' AS amount
            "),

                DB::raw("
                ah.content->'additionalInfo'->>'cost' AS cost
            "),
            ])
            ->when(isset($filters['region_id']), function ($query) use ($filters) {
                $query->where('a.region_id', $filters['region_id']);
            })
            ->when(isset($filters['district_id']), function ($query) use ($filters) {
                $query->where('a.district_id', $filters['district_id']);
            })
            ->groupBy([
                'a.id',
                'a.name',
                'r.name_uz',
                'd.name_uz',
                'a.location_building',
                'a.task_id',
                'a.manual_task_id',
                'os.name',
                'a.created_at',
                'f.description',
                'a.construction_cost',
                'a.price_supervision_service',
                'ah.id',
                'ah.content',
            ])
            ->orderByDesc('ah.created_at');

        return (new FastExcel(
            $query->lazy(1000)
        ))->download('articles.xlsx', function ($row) {
            return [
                'Obyekt nomi' => $row->name,
                'Buyurtmachi' => $row->buyurtmachi,
                'Buyurtmachi INN' => $row->buyurtmachi_inn,
                'Viloyat' => $row->region,
                'Tuman' => $row->district,
                'Manzil' => $row->location_building,
                'Inspektor' => $row->inspektor,
                'Task ID' => $row->task_id,
                'Obyekt holati' => $row->object_status,
                'Yaratilgan sana' => $row->created_at,
                'Moliyalashtirish manbasi' => $row->funding_source,
                'Qurilish qiymati' => $row->construction_cost,
                'Qurilish qiymati 0.2%' => $row->price_supervision_service,
                'Umumiy qiymat' => $row->amount,
                'Qolgan qarz' => $row->cost,
            ];
        });
    }
}
