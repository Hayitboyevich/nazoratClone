<?php

namespace App\Repositories;

use App\Enums\LabTestContractStatusEnum as Status;
use App\Enums\LabTestResultEnum;
use App\Enums\LabTestStatusEnum;
use App\Enums\UserRoleEnum;
use App\Models\Article;
use App\Models\District;
use App\Models\Inspection;
use App\Models\LabMethodIndicator;
use App\Models\LabRekvizit;
use App\Models\LabTest;
use App\Models\LabTestContract;
use App\Models\LabTestContractBlock;
use App\Models\LabTestContractItem;
use App\Models\User;
use App\Repositories\Interfaces\LabTestContractRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class LabTestContractRepository implements LabTestContractRepositoryInterface
{
    /** Obyekt sahifasidagi «Qurilish ishtirokchilari»: article_users.role_id => nomi */
    private const PARTICIPANT_ROLES = [
        UserRoleEnum::INSPECTOR->value => 'Inspektor',
        UserRoleEnum::TEXNIK->value => 'Texnik nazoratchi',
        UserRoleEnum::MUALLIF->value => 'Muallif nazorati',
        UserRoleEnum::ICHKI->value => 'Ichki nazoratchi',
    ];

    /** Hali yakunlanmagan (tasdiqlanmagan va bekor qilinmagan) holatlar */
    private const ACTIVE_STATUSES = [
        Status::LABORANT->value, Status::ACCOUNTANT->value, Status::DIRECTOR->value, Status::CUSTOMER->value,
    ];

    public function paginate(array $scope, array $filters, int $perPage = 15)
    {
        return $this->scoped($scope)
            ->with(['region:id,name_uz', 'employee', 'parent'])
            ->withCount('blocks')
            ->when($filters['parent_id'] ?? null, fn($q, $v) => $q->where('parent_id', $v))
            ->when($filters['status'] ?? null, fn($q, $v) => $q->where('status', $v))
            ->when($filters['test_type'] ?? null, fn($q, $v) => $q->where('test_type', $v))
            ->when($filters['employee_id'] ?? null, fn($q, $v) => $q->where('employee_id', $v))
            ->when($filters['object_id'] ?? null, fn($q, $v) => $q->where('object_id', $v))
            ->when($filters['waiting_status'] ?? null, fn($q, $v) => $q->where('status', $v))
            ->when(($filters['filter'] ?? null) === 'returned', fn($q) => $q
                ->where('status', Status::LABORANT->value)
                ->whereNotNull('return_data'))
            ->when($filters['search'] ?? null, fn($q, $v) => $q->where(fn($q) => $q
                ->where('contract_number', 'ilike', "%$v%")
                ->orWhere('object_name', 'ilike', "%$v%")
                ->orWhere('customer_name', 'ilike', "%$v%")
                ->orWhere('customer_tin', 'ilike', "%$v%")
                ->orWhere('address', 'ilike', "%$v%")))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /** Ro'yxat kartochkalari uchun: holatlar bo'yicha arizalar soni */
    public function statusCounts(array $scope): array
    {
        return $this->scoped($scope)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn($v) => (int)$v)
            ->all();
    }

    public function find(array $scope, $id, array $with = []): LabTestContract
    {
        return $this->scoped($scope)->with($with)->findOrFail($id);
    }

    public function create(array $data, int $userId, int $roleId, ?string $comment = null): LabTestContract
    {
        return DB::transaction(function () use ($data, $userId, $roleId, $comment) {
            $contract = LabTestContract::query()->create(array_merge($data, [
                'status' => Status::LABORANT->value,
                'created_by' => $userId,
            ]));
            // ariza raqami shartnoma raqami sifatida ham ishlatiladi
            $contract->update(['contract_number' => sprintf('L-%s-%06d', now()->format('Y'), $contract->id)]);
            $contract->histories()->create([
                'from_status' => null,
                'to_status' => Status::LABORANT->value,
                'comment' => $comment,
                'data' => ['employee_id' => $contract->employee_id, 'test_type' => $contract->test_type],
                'user_id' => $userId,
                'role_id' => $roleId,
            ]);
            return $contract;
        });
    }

    /**
     * Qo'shimcha shartnoma: obyekt, buyurtmachi, sinov usuli va obyekt biriktiruvi asosiy shartnomadan olinadi.
     * Oilaning barcha asl bloklari ko'chiriladi (parent_block_id bilan) - laborant ularga yangi sinov qo'shadi
     * yoki yangi blok kiritadi. Raqami: asosiy raqam / tartib (L-2026-000001/1).
     */
    public function createAdditional(LabTestContract $root, int $employeeId, int $userId, int $roleId, ?string $comment = null): LabTestContract
    {
        return DB::transaction(function () use ($root, $employeeId, $userId, $roleId, $comment) {
            $sequence = LabTestContract::withTrashed()->where('parent_id', $root->id)->count() + 1;
            $contract = LabTestContract::query()->create(array_merge(
                $root->only([
                    'region_id', 'object_type_id', 'object_name', 'construction_type', 'district', 'address',
                    'cadastral_number', 'owner_name', 'object_id', 'expertise_exists', 'expertise_number',
                    'customer_type', 'customer_tin', 'customer_name', 'customer_phone', 'test_type', 'documents', 'vat_percent',
                ]),
                [
                    'parent_id' => $root->id,
                    'contract_number' => $root->contract_number . '/' . $sequence,
                    'employee_id' => $employeeId,
                    'comment' => $comment,
                    'status' => Status::LABORANT->value,
                    'created_by' => $userId,
                ]
            ));

            $family = LabTestContract::query()
                ->where(fn($q) => $q->whereKey($root->id)->orWhere('parent_id', $root->id))
                ->where('status', Status::CONFIRMED->value)
                ->pluck('id');
            $blocks = LabTestContractBlock::query()
                ->whereIn('lab_test_contract_id', $family)
                ->whereNull('parent_block_id')
                ->orderBy('lab_test_contract_id')->orderBy('sort')->orderBy('id')
                ->get();
            foreach ($blocks as $sort => $block) {
                $contract->blocks()->create([
                    'parent_block_id' => $block->id,
                    'name' => $block->name,
                    'floors_count' => $block->floors_count,
                    'data' => $block->data,
                    'sort' => $sort,
                ]);
            }

            $contract->histories()->create([
                'from_status' => null,
                'to_status' => Status::LABORANT->value,
                'comment' => $comment,
                'data' => ['action' => 'create_additional', 'parent_id' => $root->id, 'employee_id' => $employeeId, 'test_type' => $contract->test_type],
                'user_id' => $userId,
                'role_id' => $roleId,
            ]);

            return $contract;
        });
    }

    public function hasActiveInFamily(int $rootId): bool
    {
        return LabTestContract::query()
            ->where(fn($q) => $q->whereKey($rootId)->orWhere('parent_id', $rootId))
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->exists();
    }

    public function takenIndicators(LabTestContract $contract): array
    {
        $rootId = $contract->parent_id ?? $contract->id;
        $items = LabTestContractItem::query()
            ->whereHas('contract', fn($q) => $q
                ->where(fn($q) => $q->whereKey($rootId)->orWhere('parent_id', $rootId))
                ->whereKeyNot($contract->id)
                ->where('status', Status::CONFIRMED->value))
            ->with(['contract:id,contract_number', 'block:id,parent_block_id'])
            ->get();

        $taken = [];
        foreach ($items as $item) {
            if (!$item->block) {
                continue;
            }
            $key = $item->block->rootId() . ':' . $item->work_type_id . ':' . $item->lab_method_id;
            // qavatli ishda sinovlar har qavat bo'yicha alohida band bo'ladi; qavatsiz ishda kalit 0
            $floors = $item->floor_quantities === null ? [0] : array_keys($item->floor_quantities);
            foreach ($floors as $floor) {
                foreach ($item->indicatorIdsFor($floor ? (int)$floor : null) as $indicatorId) {
                    $taken[$key][(int)$floor][$indicatorId] ??= $item->contract->contract_number;
                }
            }
        }

        return $taken;
    }

    public function hasFamilyTests(int $rootId): bool
    {
        return LabTest::query()->whereHas('contract', fn($q) => $q->where('parent_id', $rootId))->exists();
    }

    public function attachObjectToAdditionals(LabTestContract $root, int $objectId): void
    {
        LabTestContract::query()->where('parent_id', $root->id)->update(['object_id' => $objectId]);
    }

    /**
     * Bloklarni sinxronlash: id si borlari yangilanadi, yangilari yaratiladi, yuborilmaganlari
     * (ishlari bilan birga) o'chiriladi. So'ng summalar qayta hisoblanadi.
     */
    public function syncBlocks(LabTestContract $contract, array $blocks): void
    {
        DB::transaction(function () use ($contract, $blocks) {
            $keep = [];
            foreach (array_values($blocks) as $sort => $block) {
                $attributes = [
                    'name' => trim($block['name']),
                    'floors_count' => (int)$block['floors_count'],
                    'data' => $block['data'] ?? null,
                    'sort' => $sort,
                ];
                if (!empty($block['id'])) {
                    $contract->blocks()->findOrFail($block['id'])->update($attributes);
                    $keep[] = (int)$block['id'];
                } else {
                    $keep[] = $contract->blocks()->create($attributes)->id;
                }
            }
            $contract->blocks()->whereNotIn('id', $keep)->delete();
            $this->recalculate($contract);
        });
    }

    /**
     * Ilova qatorlarini to'liq almashtiradi va shartnoma summalarini qayta hisoblaydi.
     */
    public function replaceItems(LabTestContract $contract, array $rows): void
    {
        DB::transaction(function () use ($contract, $rows) {
            $contract->items()->delete();
            $contract->items()->createMany($rows);
            $this->recalculate($contract);
        });
    }

    /**
     * Qavatlar soni kamaygan bloklarda ortiqcha qavat miqdorlarini olib tashlaydi va
     * qator hamda shartnoma summalarini qayta hisoblaydi.
     */
    public function recalculate(LabTestContract $contract): void
    {
        $floors = $contract->blocks()->pluck('floors_count', 'id');
        $totals = ['total_price' => 0, 'total_vat' => 0, 'total_with_vat' => 0];

        foreach ($contract->items()->get() as $item) {
            if ($item->floor_quantities !== null) {
                $max = (int)($floors[$item->lab_test_contract_block_id] ?? 0);
                $kept = array_filter($item->floor_quantities, fn($floor) => (int)$floor <= $max, ARRAY_FILTER_USE_KEY);
                $item->floor_quantities = $kept;
                $item->quantity = array_sum($kept);
                if (is_array($item->floor_indicators)) {
                    $item->floor_indicators = array_intersect_key($item->floor_indicators, $kept);
                    $item->floor_unit_prices = array_intersect_key($item->floor_unit_prices ?? [], $kept);
                    $ids = array_values(array_unique(array_merge([], ...array_values($item->floor_indicators))));
                    sort($ids);
                    $item->indicator_ids = $ids;
                }
            }
            $item->total_price = $item->calculateTotal();
            if ($item->floor_quantities !== null && $item->quantity > 0) {
                $item->unit_price = (int)round($item->total_price / $item->quantity);
            }
            $item->total_vat = (int)round($item->total_price * $contract->vat_percent / 100);
            $item->total_with_vat = $item->total_price + $item->total_vat;
            $item->save();

            $totals['total_price'] += $item->total_price;
            $totals['total_vat'] += $item->total_vat;
            $totals['total_with_vat'] += $item->total_with_vat;
        }

        $contract->update($totals);
    }

    public function delete(LabTestContract $contract): void
    {
        $contract->delete();
    }

    public function histories(LabTestContract $contract)
    {
        return $contract->histories()->with('user')->get();
    }

    public function indicators(array $ids)
    {
        return LabMethodIndicator::query()->whereIn('id', $ids)->where('status', true)->get()->keyBy('id');
    }

    /**
     * Hududdagi laborantlar: faol (yakunlanmagan) va jami arizalari soni bilan.
     */
    public function employees($regionId)
    {
        $counts = LabTestContract::query()
            ->where('region_id', $regionId)
            ->whereNotNull('employee_id')
            ->selectRaw('employee_id, count(*) as total, sum(case when status in (' . implode(',', self::ACTIVE_STATUSES) . ') then 1 else 0 end) as active')
            ->groupBy('employee_id')
            ->get()
            ->keyBy('employee_id');

        return $this->employeeQuery($regionId)
            ->orderBy('surname')
            ->get(['id', 'name', 'surname', 'middle_name'])
            ->map(function (User $user) use ($counts) {
                $user->setAttribute('active_contracts', (int)($counts[$user->id]->active ?? 0));
                $user->setAttribute('total_contracts', (int)($counts[$user->id]->total ?? 0));
                return $user;
            });
    }

    public function isEmployee($userId, $regionId): bool
    {
        return $this->employeeQuery($regionId)->whereKey($userId)->exists();
    }

    /**
     * Laborant sinov o'tkazadigan ishlar: o'ziga biriktirilgan, tasdiqlangan va obyekt biriktirilgan shartnomalardan.
     */
    public function assignedItems(int $employeeId, array $filters, int $perPage = 15)
    {
        $items = LabTestContractItem::query()
            ->whereHas('contract', fn($q) => $q
                ->where('employee_id', $employeeId)
                ->where('status', Status::CONFIRMED->value)
                ->whereNotNull('object_id')
                ->when($filters['object_id'] ?? null, fn($q, $v) => $q->where('object_id', $v))
                ->when($filters['contract_id'] ?? null, fn($q, $v) => $q->whereKey($v)))
            ->with(['contract:id,contract_number,parent_id,object_name,object_id,region_id', 'block:id,name,floors_count,parent_block_id', 'method', 'method.indicators', 'workType'])
            // rad etilgan sinov hisobga olinmaydi - o'sha qavat uchun sinov qaytadan o'tkaziladi
            ->withCount(['tests' => fn($q) => $q->where('status', '!=', LabTestStatusEnum::REJECTED->value)])
            ->orderBy('lab_test_contract_id')
            ->orderBy('lab_test_contract_block_id')
            ->orderBy('work_type_id')
            ->orderBy('id')
            ->paginate($perPage);

        $used = $this->testQuantities($items->getCollection()->modelKeys());
        $items->getCollection()->each(function (LabTestContractItem $item) use ($used) {
            $rows = $used[$item->id] ?? collect();
            $active = $rows->where('status', '!=', LabTestStatusEnum::REJECTED->value);
            $tested = (int)$active->sum('total');
            $item->setAttribute('tested_quantity', $tested);
            $item->setAttribute('confirmed_quantity', (int)$rows->where('status', LabTestStatusEnum::CONFIRMED->value)->sum('total'));
            $item->setAttribute('remaining_quantity', max(0, $item->quantity - $tested));
            // qavatli ishda har qavat bo'yicha: shartnomadagi, sinovdan o'tgan va qolgan miqdor
            $item->setAttribute('floors', $item->floor_quantities === null ? null : collect($item->floor_quantities)
                ->map(function ($quantity, $floor) use ($active, $item) {
                    $done = (int)$active->where('floor', (int)$floor)->sum('total');
                    return [
                        'floor' => (int)$floor, 'quantity' => (int)$quantity, 'tested_quantity' => $done,
                        'remaining_quantity' => max(0, (int)$quantity - $done),
                        // shu qavatda shartnomada tanlangan sinovlar va bitta birlik narxi
                        'indicator_ids' => $item->indicatorIdsFor((int)$floor),
                        'unit_price' => $item->unitPriceFor((int)$floor),
                    ];
                })->values());
        });

        return $items;
    }

    /** Qatorlar bo'yicha sinovlar qoplagan miqdor: [item_id => [{floor, status, total}]] */
    private function testQuantities($itemIds)
    {
        return LabTest::query()
            ->whereIn('lab_contract_item_id', $itemIds)
            ->selectRaw('lab_contract_item_id, floor, status, sum(quantity) as total, count(*) as tests')
            ->groupBy('lab_contract_item_id', 'floor', 'status')
            ->get()
            ->groupBy('lab_contract_item_id');
    }

    /**
     * Shartnoma biriktirilgan obyektlar: har birida tasdiqlangan shartnomalari va sinovlar yig'masi.
     * Ish bajarilgan hisoblanadi, agar tasdiqlangan bayonnomalar shartnomadagi miqdorni to'liq qoplasa.
     */
    public function objects(array $scope, array $filters, int $perPage = 15)
    {
        $contracts = fn() => $this->scoped($scope)
            ->where('status', Status::CONFIRMED->value)
            ->whereNotNull('object_id')
            ->when($filters['employee_id'] ?? null, fn($q, $v) => $q->where('employee_id', $v));

        $objects = Article::query()
            ->select('id', 'name', 'task_id', 'address', 'location_building', 'region_id', 'district_id', 'object_status_id', 'object_type_id',
                'construction_works', 'cadastral_number', 'lat', 'long', 'organization_name', 'org_stir', 'created_at')
            ->whereIn('id', $contracts()->select('object_id'))
            ->when($filters['object_id'] ?? null, fn($q, $v) => $q->whereKey($v))
            ->when($filters['search'] ?? null, fn($q, $v) => $q->where(fn($q) => $q
                ->where('name', 'ilike', "%$v%")
                ->orWhere('address', 'ilike', "%$v%")
                ->orWhereRaw('task_id::text ilike ?', ["%$v%"])
                ->orWhereIn('id', $contracts()->where('contract_number', 'ilike', "%$v%")->select('object_id'))))
            ->with(['region:id,name_uz', 'district:id,name_uz', 'objectStatus:id,name', 'objectType:id,name',
                'users' => fn($q) => $q->select('users.id', 'users.name', 'users.surname', 'users.middle_name', 'users.phone')
                    ->wherePivotIn('role_id', array_merge(array_keys(self::PARTICIPANT_ROLES), [UserRoleEnum::BUYURTMACHI->value]))])
            ->orderByDesc('id')
            ->paginate($perPage);

        $byObject = $contracts()
            ->whereIn('object_id', $objects->getCollection()->modelKeys())
            ->with(['employee', 'items:id,lab_test_contract_id,quantity', 'blocks:id,lab_test_contract_id,parent_block_id,name,floors_count,data'])
            ->orderBy('id')
            ->get(['id', 'contract_number', 'parent_id', 'object_id', 'employee_id', 'test_type', 'status', 'confirmed_at', 'customer_name', 'customer_tin', 'customer_phone'])
            ->groupBy('object_id');

        $used = $this->testQuantities($byObject->flatten()->pluck('items')->flatten()->pluck('id'));

        // bayonnomalar natijasi (ijobiy / salbiy) - rad etilgan bayonnomalar sanalmaydi
        $results = LabTest::query()
            ->whereIn('lab_contract_id', $byObject->flatten()->pluck('id'))
            ->whereNotNull('result')
            ->where('status', '!=', LabTestStatusEnum::REJECTED->value)
            ->selectRaw('lab_contract_id, result, count(*) as total')
            ->groupBy('lab_contract_id', 'result')
            ->get();

        $objects->getCollection()->transform(function (Article $object) use ($byObject, $used, $results) {
            $list = $byObject[$object->id] ?? collect();
            $items = $list->pluck('items')->flatten();
            $root = $list->firstWhere('parent_id', null) ?? $list->first();
            $byResult = fn(LabTestResultEnum $r) => (int)$results->whereIn('lab_contract_id', $list->pluck('id'))->where('result', $r->value)->sum('total');
            // bloklar: asosiy va qo'shimcha shartnomalardagi asl bloklar (ko'chirilganlari takrorlanmaydi)
            $blocks = $list->pluck('blocks')->flatten()->whereNull('parent_block_id')->values();
            $person = fn($u) => $u ? ['id' => $u->id, 'name' => $u->name, 'surname' => $u->surname, 'middle_name' => $u->middle_name, 'phone' => $u->phone] : null;
            $participants = collect(self::PARTICIPANT_ROLES)->map(fn($label, $roleId) => [
                'role_id' => $roleId,
                'role_name' => $label,
                'user' => $person($object->users->first(fn($u) => (int)$u->pivot->role_id === $roleId)),
            ])->values();
            $customerUser = $object->users->first(fn($u) => (int)$u->pivot->role_id === UserRoleEnum::BUYURTMACHI->value);
            $rows = $items->flatMap(fn($i) => $used[$i->id] ?? []);
            $tests = fn(LabTestStatusEnum $status) => (int)$rows->where('status', $status->value)->sum('tests');
            $confirmed = fn($item) => (int)($used[$item->id] ?? collect())->where('status', LabTestStatusEnum::CONFIRMED->value)->sum('total');
            // har bir ish teng vaznda: birliklar har xil (partiya, namuna, uchastka), shuning uchun miqdorlar qo'shilmaydi
            $progress = $items->avg(fn($i) => $i->quantity > 0 ? min(1, $confirmed($i) / $i->quantity) : 0);

            return [
                'id' => $object->id,
                'name' => $object->name,
                'task_id' => $object->task_id,
                // obyekt joylashgan manzil (articles.address - buyurtmachi manzili)
                'address' => $object->location_building ?: $object->address,
                'region' => $object->region,
                'district' => $object->district,
                'object_status' => $object->objectStatus,
                'object_type' => $object->objectType,
                'construction_type' => $object->construction_works,
                'cadastral_number' => $object->cadastral_number,
                'lat' => $object->lat,
                'long' => $object->long,
                'registered_at' => $object->created_at,
                // sinov usuli va laborant - asosiy shartnomadan
                'test_type' => $root?->test_type,
                'test_type_name' => $root?->test_type_name,
                'laborant' => $root?->employee,
                'customer' => [
                    'name' => $object->organization_name ?: $root?->customer_name,
                    'tin' => $object->org_stir ?: $root?->customer_tin,
                    'phone' => $customerUser?->phone ?: $root?->customer_phone,
                ],
                'blocks_count' => $blocks->count(),
                'blocks' => $blocks->map(fn($b) => [
                    'id' => $b->id,
                    'name' => $b->name,
                    'floors_count' => $b->floors_count,
                    'foundation' => $b->data['fund'] ?? null,
                    'foundation_name' => trim(($b->data['fund']['tur'] ?? '') . ' ' . ($b->data['fund']['B'] ?? '')) ?: null,
                ]),
                // qurilish ishtirokchilari: biriktirilmagan rolda user = null
                'participants' => $participants,
                'participants_missing' => $participants->whereNull('user')->count(),
                'contracts' => $list->map(fn($c) => [
                    'id' => $c->id,
                    'contract_number' => $c->contract_number,
                    'parent_id' => $c->parent_id,
                    'is_additional' => $c->is_additional,
                    'test_type' => $c->test_type,
                    'test_type_name' => $c->test_type_name,
                    'confirmed_at' => $c->confirmed_at,
                    'employee' => $c->employee,
                ])->values(),
                'works_count' => $items->count(),
                // shartnomadagi miqdori to'liq tasdiqlangan bayonnomalar bilan qoplangan ishlar
                'works_done' => $items->filter(fn($i) => $i->quantity > 0 && $confirmed($i) >= $i->quantity)->count(),
                // bayonnomalar (sinovlar) soni holatlar bo'yicha
                'tests_count' => (int)$rows->sum('tests'),
                'tests' => [
                    'in_progress' => $tests(LabTestStatusEnum::IN_PROGRESS),
                    'waiting' => $tests(LabTestStatusEnum::PROTOCOL_FORMED),
                    'returned' => $tests(LabTestStatusEnum::RETURNED),
                    'confirmed' => $tests(LabTestStatusEnum::CONFIRMED),
                    'rejected' => $tests(LabTestStatusEnum::REJECTED),
                ],
                'results' => [
                    'positive' => $byResult(LabTestResultEnum::POSITIVE),
                    'negative' => $byResult(LabTestResultEnum::NEGATIVE),
                ],
                'progress' => (int)round(($progress ?? 0) * 100),
            ];
        });
        return $objects;
    }

    public function inspection($regionId)
    {
        return Inspection::query()->where('region_id', $regionId)->first();
    }

    public function requisite($regionId, ?string $district)
    {
        $districtId = $district
            ? District::query()->where('region_id', $regionId)->where('name_uz', 'ilike', trim($district))->value('id')
            : null;
        $query = fn() => LabRekvizit::query()->where('region_id', $regionId)->orderBy('id');

        return ($districtId ? $query()->where('district_id', $districtId)->first() : null) ?? $query()->first();
    }

    private function employeeQuery($regionId): Builder
    {
        return User::query()
            ->whereHas('roles', fn($q) => $q->where('roles.id', UserRoleEnum::LABORANT->value))
            ->where('region_id', $regionId);
    }

    private function scoped(array $scope): Builder
    {
        return LabTestContract::query()
            ->when(($scope['region_id'] ?? null) !== null, fn($q) => $q->where('region_id', $scope['region_id']))
            ->when(($scope['employee_id'] ?? null) !== null, fn($q) => $q->where('employee_id', $scope['employee_id']))
            ->when(($scope['customer_tin'] ?? null) !== null, fn($q) => $q->where('customer_tin', $scope['customer_tin']))
            // min_status: ariza shu bosqichda yoki undan keyin turibdi, yoki shu bosqichga bir marta yetib kelgan
            // (masalan buxgalter laborantga qaytargan ariza buxgalter ro'yxatidan yo'qolmasligi uchun)
            ->when(($scope['min_status'] ?? null) !== null, fn($q) => $q->where(fn($q) => $q
                ->where('status', '>=', $scope['min_status'])
                ->orWhereHas('histories', fn($h) => $h->where('to_status', $scope['min_status']))));
    }
}
