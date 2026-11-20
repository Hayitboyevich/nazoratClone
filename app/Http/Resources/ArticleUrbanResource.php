<?php

namespace App\Http\Resources;

use App\Enums\LogType;
use App\Models\ObjectStatus;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ArticleUrbanResource extends JsonResource
{
    public function toArray(Request $request): array
    {

        $dxaResponse = $this->response;

        $oldTaskIds = $dxaResponse
            ? $dxaResponse->getOldTaskIds($this->task_id)
            : null;


        $histories = $this->histories;

        $historyUserIds = $histories
            ->pluck('content.user')
            ->filter()
            ->unique()
            ->values();

        $historyRoleIds = $histories
            ->pluck('content.role')
            ->filter()
            ->unique()
            ->values();

        $historyStatusIds = $histories
            ->pluck('content.status')
            ->filter()
            ->unique()
            ->values();


        $historyUsers = $historyUserIds->isNotEmpty()
            ? User::query()
                ->whereIn('id', $historyUserIds)
                ->get([
                    'id',
                    'name',
                    'surname',
                    'middle_name',
                ])
                ->keyBy('id')
            : collect();


        $historyRoles = $historyRoleIds->isNotEmpty()
            ? Role::query()
                ->whereIn('id', $historyRoleIds)
                ->get([
                    'id',
                    'name',
                    'description',
                ])
                ->keyBy('id')
            : collect();


        $historyStatuses = $historyStatusIds->isNotEmpty()
            ? ObjectStatus::query()
                ->whereIn('id', $historyStatusIds)
                ->get([
                    'id',
                    'name',
                ])
                ->keyBy('id')
            : collect();


        $roleIds = $this->users
            ->pluck('pivot.role_id')
            ->filter()
            ->unique()
            ->values();

        $roles = $roleIds->isNotEmpty()
            ? Role::query()
                ->whereIn('id', $roleIds)
                ->get([
                    'id',
                    'name',
                    'description',
                ])
                ->keyBy('id')
            : collect();

        return [
            'id' => $this->id,

            'name' => $this->name,

            'task_ids' => $oldTaskIds,

            'region' => RegionResource::make($this->region),

            'district' => DistrictResource::make($this->district),

            'task_id' => $this->task_id ?? $this->manual_task_id,

            'address' => $this->address,

            'linear_type' => $this->linear_type,

            'sphere' => SphereResource::make($this->sphere),

            'program' => ProgramResource::make($this->program),

            'construction_works' => $this->construction_works,

            'object_type' => ObjectTypeResource::make($this->objectType),

            'location_building' => $this->location_building,

            'organization_name' => $this->organization_name,

            'cadastral_number' => $this->cadastral_number,

            'name_expertise' => $this->name_expertise,

            'status' => ObjectStatusResource::make($this->objectStatus),

            'difficulty_category' => DifficultyCategoryResource::make(
                $this->difficulty
            ),

            'total_price' => $this->construction_cost,

            'price_supervision_service' => $this->price_supervision_service,

            'paid' => $this->totalAmount(),

            'lat' => $this->lat,

            'long' => $this->long,

            'number_protocol' => $this->number_protocol,

            'date_protocol' => $this->date_protocol,

            'funding_source' => FundingSourceResource::make(
                $this->fundingSource
            ),

            'payment_deadline' => $this->payment_deadline,

            'closed_at' => $this->closed_at,

            'closed_type' => $this->closed_type,

            'closed_file' => $this->closed_file,

            'deadline' => $this->deadline,

            'reestr_number' => $this->reestr_number,

            'gnk_id' => $this->gnk_id,

            'created_at' => $this->created_at,

            'organization_projects' => $this->organization_projects,

            'specialists_certificates' => $this->specialists_certificates,

            'contract_file' => $this->contract_file,

            'confirming_laboratory' => $this->confirming_laboratory,

            'file_energy_efficiency' => $this->file_energy_efficiency,


            'users' => $this->users($roles),


            'payment_logs' => ArticlePaymentLogResource::collection(
                $this->paymentLogs
            ),

            'payment_laboratory' => ArticlePaymentLogResource::collection(
                $this->paymentLaboratories
            ),

            'rating' => json_decode($this->rating),

            'is_old' => !is_null($this->old_id),

            'cost' => $this->cost,


            'monitoring' => $this->monitorings(),

            'internal_control' => $this->internal_control,

            'laboratory_cost' => $this->laboratory_cost,

            'lab_cost' => $this->lab_cost,

            'lab_id' => $this->lab_contract_id,

            'lab_contract' => $this->labContract,

            'laboratory_type' => $this->laboratory_type,

            'author_control' => $this->author_control,

            'technic_control' => $this->technic_control,

            'is_manual' => $this->task_id ? false : true,


            'files' => $this->filesToArray($this->files),

            'expertise_files' => $this->filesToArray(
                $this->expertise_files
            ),

            'user_files' => $this->filesToArray(
                $this->user_files
            ),


            'histories' => $histories
                ->map(function ($history) use (
                    $historyUsers,
                    $historyRoles,
                    $historyStatuses
                ) {
                    $userId = $history->content->user ?? null;
                    $roleId = $history->content->role ?? null;
                    $statusId = $history->content->status ?? null;

                    $user = $userId
                        ? $historyUsers->get($userId)
                        : null;

                    $role = $roleId
                        ? $historyRoles->get($roleId)
                        : null;

                    $status = $statusId
                        ? $historyStatuses->get($statusId)
                        : null;

                    return [
                        'id' => $history->id,

                        'user' => $user,

                        'role' => $role,

                        'status' => $status,

                        'type' => $history->type,

                        'is_change' => $history->type
                            ? LogType::getLabel($history->type)
                            : null,

                        'file' => $history->documents
                            ->map(function ($document) {
                                return [
                                    'id' => $document->id,

                                    'path' => Storage::disk('public')
                                        ->url($document->url),
                                ];
                            })
                            ->values(),

                        'created_at' => $history->created_at,
                    ];
                })
                ->sortByDesc('created_at')
                ->values(),


            'object_images' => $this->monitorings
                ->flatMap(
                    fn($monitoring) => $monitoring->images
                )
                ->sortByDesc('created_at')
                ->map(function ($image) {
                    return [
                        'id' => $image->id,

                        'url' => Storage::disk('public')
                            ->url($image->url),

                        'created_at' => $image->created_at,
                    ];
                })
                ->groupBy(
                    fn($image) => Carbon::parse(
                        $image['created_at']
                    )->toDateString()
                )
                ->toArray(),
        ];
    }


    public function monitorings()
    {
        return $this->monitorings
            ->map(function ($monitoring) {
                return [
                    'id' => $monitoring->id,

                    'object_name' => $this->name,

                    'task_id' => $this->task_id
                        ?? $this->manual_task_id,

                    'work_in_progress' => $monitoring->work_in_progress,

                    'created_at' => $monitoring->created_at,

                    'role' => [
                        'id' => $monitoring->role?->id,

                        'name' => $monitoring->role?->name,
                    ],

                    'user' => [
                        'id' => $monitoring->user?->id,

                        'name' => $monitoring->user
                            ? trim(
                                "{$monitoring->user->surname} " .
                                "{$monitoring->user->name} " .
                                "{$monitoring->user->middle_name}"
                            )
                            : null,

                        'phone' => $monitoring->user?->phone,
                    ],
                ];
            })
            ->values();
    }


    public function users($roles)
    {
        return $this->users
            ->map(function ($user) use ($roles) {
                $role = $roles->get(
                    $user->pivot?->role_id
                );

                return [
                    'id' => $user->id,

                    'name' => $user->name,

                    'surname' => $user->surname,

                    'middle_name' => $user->middle_name,

                    'phone' => $user->phone,

                    'pinfl' => $user->pinfl,

                    'organization_name' => $user->organization_name,

                    'role' => $role
                        ? RoleResource::make($role)
                        : null,
                ];
            })
            ->values();
    }


    private function filesToArray(?string $files): array
    {
        if (empty($files)) {
            return [];
        }

        $files = json_decode($files, true);

        if (!is_array($files)) {
            return [];
        }

        return collect($files)
            ->filter()
            ->map(function ($file) {
                return [
                    'url' => url('storage/' . $file),
                ];
            })
            ->values()
            ->toArray();
    }
}
