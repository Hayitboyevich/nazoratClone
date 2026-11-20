<?php

namespace App\Repositories;

use App\Enums\LabTestStatusEnum;
use App\Models\Article;
use App\Models\LabInstrument;
use App\Models\LabMethod;
use App\Models\LabTest;
use App\Models\LabTestContractItem;
use App\Models\WorkType;
use App\Repositories\Interfaces\LabTestRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class LabTestRepository implements LabTestRepositoryInterface
{
    private const LIST_RELATIONS = ['method:id,code,name', 'method.indicators', 'workType', 'contract', 'block', 'region:id,name_uz', 'creator', 'reviewer'];

    public function methods()
    {
        return LabMethod::query()
            ->where('status', true)
            ->orderBy('sort')
            ->orderBy('id')
            ->with('indicators')
            ->get(['id', 'code', 'name', 'unit', 'description']);
    }

    /** Laboratoriyaga tegishli ish turlari (bosqichlar): Kotlovan, Fundament, Konstruksiya elementlari ... */
    public function workTypes()
    {
        return WorkType::query()
            ->where('is_laboratoriya', true)
            ->where('status', true)
            ->orderBy('object_type_id')
            ->orderBy('sort')
            ->orderBy('id')
            ->get(['id', 'name', 'object_type_id', 'is_multiple_floor']);
    }

    public function workTypeHasFloors($workTypeId): bool
    {
        return (bool)WorkType::query()->whereKey($workTypeId)->value('is_multiple_floor');
    }

    /** Shartnoma ilovasi qatori (shartnomasi bilan) - sinov shu qator bo'yicha o'tkaziladi */
    public function contractItem($itemId): LabTestContractItem
    {
        return LabTestContractItem::query()->with('contract')->findOrFail($itemId);
    }

    /**
     * Shu qator va qavat bo'yicha sinovlar qoplagan miqdor. Rad etilgan sinov hisobga olinmaydi -
     * uning miqdori bo'yicha sinov qaytadan o'tkaziladi. $exceptId - tahrirlanayotgan sinovning o'zi.
     */
    public function usedQuantity($itemId, $floor, $exceptId = null): int
    {
        return (int)LabTest::query()
            ->where('lab_contract_item_id', $itemId)
            ->where('status', '!=', LabTestStatusEnum::REJECTED->value)
            ->when($floor === null, fn($q) => $q->whereNull('floor'), fn($q) => $q->where('floor', $floor))
            ->when($exceptId, fn($q) => $q->whereKeyNot($exceptId))
            ->sum('quantity');
    }

    public function instruments(array $ids)
    {
        return LabInstrument::withTrashed()
            ->with(['certificates', 'methods'])
            ->whereIn('id', $ids)
            ->orderBy('code')
            ->get();
    }

    /**
     * Raqam obyekt ichida ketma-ket: asosiy va qo'shimcha shartnomalarning bayonnomalari bitta sanoqda.
     * Tranzaksiya ichida chaqiriladi - obyekt qatori qulflanadi, ikki bayonnoma bir xil raqam olmaydi.
     */
    public function nextProtocolNumber(LabTest $test): string
    {
        $objectId = (int)$test->contract->object_id;
        Article::query()->whereKey($objectId)->lockForUpdate()->value('id');

        $prefix = "BN-$objectId-";
        $last = LabTest::withTrashed()
            ->where('protocol_number', 'like', $prefix . '%')
            ->pluck('protocol_number')
            ->map(fn($number) => (int)substr($number, strlen($prefix)))
            ->max();

        return $prefix . ((int)$last + 1);
    }

    public function paginate(array $scope, array $filters, int $perPage = 15)
    {
        return $this->scoped($scope)
            ->with(self::LIST_RELATIONS)
            ->when($filters['status'] ?? null, fn($q, $v) => $q->where('status', $v))
            ->when($filters['result'] ?? null, fn($q, $v) => $q->where('result', $v))
            ->when($filters['lab_method_id'] ?? null, fn($q, $v) => $q->where('lab_method_id', $v))
            ->when($filters['lab_contract_id'] ?? null, fn($q, $v) => $q->where('lab_contract_id', $v))
            ->when($filters['lab_contract_item_id'] ?? null, fn($q, $v) => $q->where('lab_contract_item_id', $v))
            ->when($filters['lab_contract_block_id'] ?? null, fn($q, $v) => $q->where('lab_contract_block_id', $v))
            ->when($filters['work_type_id'] ?? null, fn($q, $v) => $q->where('work_type_id', $v))
            // 0-qavat ham to'g'ri filtr qiymati, shuning uchun null bilan solishtiriladi
            ->when(isset($filters['floor']), fn($q) => $q->where('floor', (int)$filters['floor']))
            ->when($filters['search'] ?? null, fn($q, $v) => $q->where('protocol_number', 'ilike', "%$v%"))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function find(array $scope, $id, array $with = []): LabTest
    {
        return $this->scoped($scope)->with($with)->findOrFail($id);
    }

    public function create(array $data, int $userId, int $roleId): LabTest
    {
        return DB::transaction(function () use ($data, $userId, $roleId) {
            $test = LabTest::query()->create(array_merge($data, [
                'status' => LabTestStatusEnum::IN_PROGRESS->value,
                'created_by' => $userId,
            ]));
            $test->statusHistories()->create([
                'from_status' => null,
                'to_status' => LabTestStatusEnum::IN_PROGRESS->value,
                'user_id' => $userId,
                'role_id' => $roleId,
            ]);
            return $test;
        });
    }

    public function delete(LabTest $test): void
    {
        $test->delete();
    }

    public function histories(LabTest $test)
    {
        return $test->statusHistories()->with('user')->get();
    }

    private function scoped(array $scope): Builder
    {
        return LabTest::query()
            ->when(($scope['region_id'] ?? null) !== null, fn($q) => $q->where('region_id', $scope['region_id']))
            ->when(($scope['created_by'] ?? null) !== null, fn($q) => $q->where('created_by', $scope['created_by']));
    }
}
