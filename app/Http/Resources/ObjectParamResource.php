<?php

namespace App\Http\Resources;

use App\Helpers\ClaimStatuses;
use App\Models\ClaimMonitoring;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ObjectParamResource extends JsonResource
{

    public function toArray(Request $request): array
    {
        $start_date = request('start_date');
        $end_date = request('end_date');

        $confirmedMonitorings = ClaimMonitoring::query()
            ->where('object_id', $this->id)
            ->where('director_answer', ClaimStatuses::TASK_STATUS_CONFIRMED)
            ->when($start_date && $end_date, function ($query) use ($start_date, $end_date) {
                $query->whereHas('claim', function ($q) use ($start_date, $end_date) {
                    $q->whereBetween('end_date', [
                        $start_date . ' 00:00:00',
                        $end_date . ' 23:59:59'
                    ]);
                });
            })
            ->get();


        return [
            'id' => $this->id,
            'task_id' => $this->task_id ?? $this->manual_task_id,
            'closed_at' => $this->closed_at,
            'name' => $this->name,
            'address' => $this->address,
            'region' => $this->region?->soato,
            'district' => $this->district?->soato,
            'block_count' => $this->blocks()->where('block_type_id', 1)->count(),
            'status' => $this->objectStatus ? ['id' => $this->object_status_id, 'name' => $this->objectStatus->name] : null,
            'unstable_blocks' => $this->blocks()->whereNot('block_type_id', 1)->count(),
            'construction_work' => $this->construction_works,
            'all_block_count' => $this->blocks()->where('accepted', true)->count(),
            'blocks' => $this->blocks
                ? $this->blocks->where('accepted', true)->map(function ($block) {
                    return [
                        'id' => $block->id,
                        'name' => $block->name,
                        'floor' => $block->floor,
                        'type' => $block->type? BlockTypeResource::make($block->type) : null,
                        'area' => $block->construction_area,
                        'apartment' => $block->count_apartments,
                    ];
                })->values() : null,
            'claims' => $confirmedMonitorings->map(function ($claim) {
                $items = collect($claim->operator_field);
                return [
                    'guid' => $claim->claim->guid,
                    'end_date' => $claim->claim->end_date,
                    'area' => $items->sum(fn($item) => (float)($item['area'] ?? 0)),
                    'total_area' => $items->sum(fn($item) => (float)($item['total_area'] ?? 0)),
                    'total_use_area' => $items->sum(fn($item) => (float)($item['total_use_area'] ?? 0)),
                    'living_area' => $items->sum(fn($item) => (float)($item['living_area'] ?? 0)),
                    'apartment_count' => $items->sum(fn($item) => (float)($item['apartment_count'] ?? 0)),
                ];
            })->values(),
            'area' => $confirmedMonitorings->sum(function ($monitoring) {
                return collect($monitoring->operator_field)->sum(fn($item) => floatval($item['area'] ?? 0));
            }),
            'total_area' => $confirmedMonitorings->sum(function ($monitoring) {
                return collect($monitoring->operator_field)->sum(fn($item) => floatval($item['total_area'] ?? 0));
            }),
            'total_use_area' => $confirmedMonitorings->sum(function ($monitoring) {
                return collect($monitoring->operator_field)->sum(fn($item) => floatval($item['total_use_area'] ?? 0));
            }),
            'living_area' => $confirmedMonitorings->sum(function ($monitoring) {
                return collect($monitoring->operator_field)->sum(fn($item) => floatval($item['living_area'] ?? 0));
            }),
            'apartment_count' => $confirmedMonitorings->sum(function ($monitoring) {
                return collect($monitoring->operator_field)->sum(fn($item) => floatval($item['apartment_count'] ?? 0));
            }),
        ];
    }
}
