<?php

namespace App\Repositories\Interfaces;

use App\Models\LabTestContract;

/**
 * $scope - ko'rish doirasi (null - cheklov yo'q):
 * ['region_id' => ?int, 'employee_id' => ?int, 'customer_tin' => ?string, 'min_status' => ?int].
 */
interface LabTestContractRepositoryInterface
{
    public function paginate(array $scope, array $filters, int $perPage = 15);

    public function statusCounts(array $scope): array;

    public function find(array $scope, $id, array $with = []): LabTestContract;

    public function create(array $data, int $userId, int $roleId, ?string $comment = null): LabTestContract;

    /** Qo'shimcha shartnoma: asosiy shartnoma ma'lumotlari va oila bloklari ko'chiriladi */
    public function createAdditional(LabTestContract $root, int $employeeId, int $userId, int $roleId, ?string $comment = null): LabTestContract;

    /** Asosiy shartnoma oilasida (o'zi va qo'shimchalari) hali yakunlanmagan shartnoma bormi */
    public function hasActiveInFamily(int $rootId): bool;

    /** Oilaning boshqa tasdiqlangan shartnomalarida tanlangan sinovlar: ["aslBlok:ishTuri:usul" => [indicator_id => shartnoma raqami]] */
    public function takenIndicators(LabTestContract $contract): array;

    /** Asosiy shartnomaning qo'shimcha shartnomalari bo'yicha sinov boshlanganmi */
    public function hasFamilyTests(int $rootId): bool;

    /** Obyektni asosiy shartnomaning qo'shimcha shartnomalariga ham yozadi */
    public function attachObjectToAdditionals(LabTestContract $root, int $objectId): void;

    public function syncBlocks(LabTestContract $contract, array $blocks): void;

    public function replaceItems(LabTestContract $contract, array $rows): void;

    public function recalculate(LabTestContract $contract): void;

    public function delete(LabTestContract $contract): void;

    public function histories(LabTestContract $contract);

    public function indicators(array $ids);

    public function employees($regionId);

    public function isEmployee($userId, $regionId): bool;

    public function assignedItems(int $employeeId, array $filters, int $perPage = 15);

    /** Shartnoma biriktirilgan obyektlar: shartnomalari va sinovlar yig'masi bilan */
    public function objects(array $scope, array $filters, int $perPage = 15);

    /** Hududiy inspeksiya (shartnomadagi «Ijrochi») */
    public function inspection($regionId);

    /** Laboratoriya to‘lovlari uchun g‘azna hisob raqami: tuman bo‘yicha, topilmasa hudud bo‘yicha */
    public function requisite($regionId, ?string $district);
}
