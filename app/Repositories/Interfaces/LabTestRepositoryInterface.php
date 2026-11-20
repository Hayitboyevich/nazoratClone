<?php

namespace App\Repositories\Interfaces;

use App\Models\LabTest;
use App\Models\LabTestContractItem;

/**
 * $scope - ko'rish doirasi: ['region_id' => ?int, 'created_by' => ?int] (null - cheklov yo'q).
 */
interface LabTestRepositoryInterface
{
    public function methods();

    public function workTypes();

    public function workTypeHasFloors($workTypeId): bool;

    public function contractItem($itemId): LabTestContractItem;

    /** Shu qator va qavat bo'yicha rad etilmagan sinovlar qoplagan miqdor ($exceptId sinovidan tashqari) */
    public function usedQuantity($itemId, $floor, $exceptId = null): int;

    /** Asboblar (o'chirilganlari ham) sertifikatlari va sinov turlari bilan */
    public function instruments(array $ids);

    /** Obyekt bo'yicha navbatdagi bayonnoma raqami: BN-{obyekt}-{tartib} */
    public function nextProtocolNumber(LabTest $test): string;

    public function paginate(array $scope, array $filters, int $perPage = 15);

    public function find(array $scope, $id, array $with = []): LabTest;

    public function create(array $data, int $userId, int $roleId): LabTest;

    public function delete(LabTest $test): void;

    public function histories(LabTest $test);
}
