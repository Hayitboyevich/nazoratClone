<?php

namespace App\Http\Resources;

use App\Enums\LogType;
use App\Models\ObjectStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ObjectElasticResource extends JsonResource
{

    public function toArray(Request $request): array
    {
        $dxaResponse = $this->response;
        $oldTaskIds = $dxaResponse ? $dxaResponse->getOldTaskIds($this->task_id) : null;

        $taskIds = collect($oldTaskIds)
            ->pluck('task_id')
            ->filter()
            ->values()
            ->implode(',');
        return [
            'id' => $this->id,
            'name' =>$this->name,
            'task_ids' => $taskIds,
            'region' => RegionResource::make($this->region),
            'district' => DistrictResource::make($this->district),
            'task_id' => $this->task_id ? (string)$this->task_id : '',
            'manual_task_id' => $this->manual_task_id,
            'address' => $this->address,
            'linear_type' => $this->linear_type,
            'sphere' => $this->sphere ? SphereResource::make($this->sphere) : null,
            'program' => $this->program ? ProgramResource::make($this->program) : null,
            'construction_works' => $this->construction_works,
            'object_type' => $this->objectType ? ObjectTypeResource::make($this->objectType) : null,
            'location_building' => $this->location_building,
            'organization_name' => $this->organization_name ?? null,
            'cadastral_number' => $this->cadastral_number,
            'name_expertise' => $this->name_expertise,
            'status' => $this->objectStatus ? ObjectStatusResource::make($this->objectStatus) : null,
            'difficulty_category' => $this->difficulty ? DifficultyCategoryResource::make($this->difficulty) : null,
            'total_price' => (string)$this->construction_cost,
            'price_supervision_service' => (string)$this->price_supervision_service,
            'paid' => (string)$this->totalAmount(),
            'lat' => (string)$this->lat,
            'long' => (string)$this->long,
            'number_protocol' => (string)$this->number_protocol,
            'date_protocol' => (string)$this->date_protocol,
            'funding_source' => $this->fundingSource ? FundingSourceResource::make($this->fundingSource) : null,
            'payment_deadline' => optional($this->payment_deadline)->format('Y-m-d\TH:i:s'),
            'deadline' => optional($this->deadline)->format('Y-m-d\TH:i:s'),
            'created_at' => optional($this->created_at)->format('Y-m-d\TH:i:s'),
            'closed_at' => optional($this->closed_at)->format('Y-m-d\TH:i:s'),
            'closed_type' => (string)$this->closed_type,
            'closed_file' => (string)$this->closed_file,
            'object_sector' => $this->objectSector ? ObjectSectorResource::make($this->objectSector) : null,
            'reestr_number' => (string)$this->reestr_number,
            'gnk_id' => (string)$this->gnk_id,
            'organization_projects' => $this->organization_projects,
            'specialists_certificates' => $this->specialists_certificates,
            'contract_file' => $this->contract_file,
            'confirming_laboratory' => $this->confirming_laboratory,
            'file_energy_efficiency' => $this->file_energy_efficiency,
            'blocks' => $this->blocks ? ResponseBlockResource::collection($this->blocks) : null,
            'users' => ArticleUserResource::collection($this->users),
            'payment_logs' => ArticlePaymentLogResource::collection($this->paymentLogs),
            'payment_laboratory' => ArticlePaymentLogResource::collection($this->paymentLaboratories),
            'rating' => $this->rating ? json_decode($this->rating) : null,
            'is_old' => !is_null($this->old_id),
            'cost' => (string)$this->cost,
            'statistics' => $this->countMonitorings(),
            'internal_control' => $this->internal_control,
            'laboratory_cost' => $this->laboratory_cost,
            'lab_cost' => (string)$this->lab_cost,
            'laboratory_type' => $this->laboratory_type,
            'author_control' => $this->author_control,
            'technic_control' => $this->technic_control,
            'is_manual' => $this->manual_task_id ? true : false,
            'history' => $this->histories->map(function ($history) {
                return [
                    'id' => $history->id,
                    'user' => $history->content->user ? User::query()->find($history->content->user, ['name', 'surname', 'middle_name']) : null,
                    'role' => $history->content->role ? Role::query()->find($history->content->role, ['name', 'description']) : null,
                    'status' => $history->content->status ? ObjectStatus::query()->find($history->content->status, ['id', 'name']) : null,
                    'type' => $history->type,
                    'is_change' => $history->type ? LogType::getLabel($history->type) : null,
                    'created_at' => $history->created_at,
                ];
            })->sortByDesc('created_at')->values(),
            'files' => collect(json_decode($this->files))->map(function ($file) {
                return [
                    'url' => url('storage/'.$file),
                ];
            }),
            'expertise_files' => collect(json_decode($this->expertise_files))->map(function ($file) {
                return [
                    'url' => url('storage/'.$file),
                ];
            }),
            'user_files' => collect(json_decode($this->user_files))->map(function ($file) {
                return [
                    'url' => url('storage/'.$file),
                ];
            }),

        ];
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
