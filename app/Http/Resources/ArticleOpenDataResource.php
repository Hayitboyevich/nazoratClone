<?php

namespace App\Http\Resources;

use App\Helpers\ClaimStatuses;
use App\Models\ClaimMonitoring;
use App\Models\Role;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ArticleOpenDataResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dxaResponse = $this->response;
        $oldTaskIds = $dxaResponse ? $dxaResponse->getOldTaskIds($this->task_id) : null;
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
            'program' => ProgramResource::make($this->program) ?? null,
            'construction_works' => $this->construction_works,
            'object_type' => ObjectTypeResource::make($this->objectType),
            'location_building' => $this->location_building,
            'organization_name' => $this->organization_name ?? null,
            'org_stir' => $this->org_stir ?? null,
            'cadastral_number' => $this->cadastral_number,
            'name_expertise' => $this->name_expertise,
            'status' => ObjectStatusResource::make($this->objectStatus),
            'difficulty_category' => DifficultyCategoryResource::make($this->difficulty),
            'total_price' => $this->construction_cost,
            'price_supervision_service' => $this->price_supervision_service,
            'paid' => $this->totalAmount(),
            'lat' => $this->lat,
            'long' => $this->long,
            'number_protocol' => $this->number_protocol,
            'date_protocol' => $this->date_protocol,
            'funding_source' => FundingSourceResource::make($this->fundingSource),
            'payment_deadline' => $this->payment_deadline,
            'closed_at' => $this->closed_at,
            'closed_type' => $this->closed_type,
            'closed_file' => $this->closed_file,
            'object_sector' => ObjectSectorResource::make($this->objectSector),
            'deadline' => $this->deadline,
            'reestr_number' => $this->reestr_number,
            'gnk_id' => $this->gnk_id,
            'created_at' => $this->created_at,
            'organization_projects' => $this->organization_projects,
            'specialists_certificates' => $this->specialists_certificates,
            'contract_file' => $this->contract_file,
            'confirming_laboratory' => $this->confirming_laboratory,
            'file_energy_efficiency' => $this->file_energy_efficiency,
            'blocks' => BlockOpenDataResource::collection($this->blocks),
            'users' => $this->users
                ? $this->users->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'fish' => $user->full_name,
                        'image' => $user->image ? Storage::disk('public')->url($user->image) : null,
                        'phone_number' => $user->phone,
                        'pinfl' => strlen($user->pinfl ?? '') === 14
                            ? substr($user->pinfl, 0, 4) . '******' . substr($user->pinfl, -4)
                            : $user->pinfl,

                        'identification_number' => strlen($user->identification_number ?? '') === 14
                            ? substr($user->identification_number, 0, 4) . '******' . substr($user->identification_number, -4)
                            : $user->identification_number,
                        'organization_name' => $user->organization_name,
                        'role' => $user->pivot && $user->pivot->role_id
                            ? RoleResource::make(Role::find($user->pivot->role_id))
                            : null,
                    ];
                })
                : collect(),
            'payment_logs' => ArticlePaymentLogResource::collection($this->paymentLogs),
            'payment_laboratory' => ArticlePaymentLogResource::collection($this->paymentLaboratories),
            'rating' => json_decode($this->rating),
            'is_old' => !is_null($this->old_id),
            'cost' => $this->cost,
            'statistics' => $this->countMonitorings(),
            'internal_control' => $this->internal_control,
            'laboratory_cost' => $this->laboratory_cost,
            'lab_cost' => $this->lab_cost,
            'laboratory_type' => $this->laboratory_type,
            'author_control' => $this->author_control,
            'technic_control' => $this->technic_control,
            'claim_fields' => $this->getClaims(),
            'is_manual' => $this->task_id ? false : true,
            'files' => collect(json_decode($this->files))->map(function ($file) {
                return [
                    'url' => url('storage/' . $file),
                ];
            }),
            'expertise_files' => collect(json_decode($this->expertise_files))->map(function ($file) {
                return [
                    'url' => url('storage/' . $file),
                ];
            }),
            'user_files' => collect(json_decode($this->user_files))->map(function ($file) {
                return [
                    'url' => url('storage/' . $file),
                ];
            }),
            'images' => $this->monitorings
                ->flatMap(fn($monitoring) => $monitoring->images)
                ->map(fn($image) => [
                    'id' => $image->id,
                    'url' => Storage::disk('public')->url($image->url),
                    'created_at' => $image->created_at
                ])
                ->groupBy(fn($image) => Carbon::parse($image['created_at'])->toDateString())
                ->toArray()
        ];
    }

    private function getClaims()
    {
        return ClaimMonitoring::query()
            ->where('object_id', $this->id)
            ->where('director_answer', ClaimStatuses::TASK_STATUS_CONFIRMED)
            ->get()
            ->map(function ($claim) {
                return $claim->operator_field;
            });
    }

    private function countMonitorings(): array
    {
        $regulations = $this->regulations;

        return [
            'monitoring_count' => $this->monitorings()->count(),
            'regulation_count' => $regulations->count(),
            'violation_count' => $this->regulations->flatMap(function ($regulation) {
                return $regulation->violations;
            })->unique('id')->count(),
            'finished_regulation_count' => $this->regulations->flatMap(function ($regulation) {
                return $regulation->where('');
            })->unique('id')->count(),
        ];
    }
}
