<?php

namespace App\Services;

use App\Enums\LabTestContractStatusEnum as Status;
use App\Enums\LabTestTypeEnum;
use App\Enums\ObjectTypeEnum;
use App\Enums\UserRoleEnum;
use App\Models\LabTestContract;
use App\Repositories\Interfaces\LabTestContractRepositoryInterface;
use App\Repositories\Interfaces\LabTestRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Laboratoriya arizasi / shartnomasi biznes-qoidalari («Laboratoriya kabinetlari» oqimi).
 *
 * Laboratoriya boshlig'i: ariza yaratadi (obyekt, buyurtmachi, sinov usuli) va laborantga biriktiradi (LABORANT).
 * Mas'ul laborant: blok, qavat va ishlarni kiritadi, buxgalterga yuboradi (ACCOUNTANT).
 * Buxgalter -> DIRECTOR, inspeksiya boshlig'i -> CUSTOMER; ikkalasi ham laborantga qaytarishi mumkin (LABORANT).
 * Buyurtmachi (STIR / JShShIR bo'yicha): tasdiqlaydi (CONFIRMED) yoki bekor qiladi (CANCELLED), hech narsa to'ldirmaydi.
 * Ruxsat yoki holat xatolari HttpException (403 / 422) sifatida tashlanadi.
 */
class LabTestContractService
{
    private const LAB_HEAD_ROLES = [UserRoleEnum::REG_LAB->value];
    private const LABORANT_ROLES = [UserRoleEnum::LABORANT->value];
    private const ACCOUNTANT_ROLES = [UserRoleEnum::BUXGALTER->value];
    private const DIRECTOR_ROLES = [UserRoleEnum::INSPEKSIYA->value];
    private const CUSTOMER_ROLES = [UserRoleEnum::BUYURTMACHI->value];
    private const ALL_REGION_ROLES = [UserRoleEnum::RES_LAB->value, UserRoleEnum::ADMIN->value];

    public const RETURN_REASONS = ["Bloklar ma'lumoti", 'Tanlangan ishlar', 'Miqdorlar', 'Narx va summa', 'Boshqa'];
    public const CONSTRUCTION_TYPES = ['Yangi qurilish', 'Rekonstruksiya', "Kapital ta'mirlash"];

    private const DETAIL_RELATIONS = [
        'blocks', 'items.method', 'items.method.indicators', 'items.workType',
        'region:id,name_uz', 'employee', 'creator', 'object:id,name',
        'parent', 'additionals:id,parent_id,contract_number,status,total_with_vat,confirmed_at,employee_id',
    ];

    public function __construct(
        public LabTestContractRepositoryInterface $repository,
        public LabTestRepositoryInterface $testRepository,
        public LabTestContractPdfService $pdfService,
    )
    {
    }

    public function dictionaries($user): array
    {
        $map = fn(array $cases) => array_map(fn($c) => ['id' => $c->value, 'name' => $c->label()], $cases);

        return [
            'statuses' => $map(Status::cases()),
            'test_types' => $map(LabTestTypeEnum::cases()),
            'object_types' => [
                ['id' => ObjectTypeEnum::BUILDING->value, 'name' => 'Bino'],
                ['id' => ObjectTypeEnum::LINEAR->value, 'name' => 'Tarmoq'],
            ],
            'customer_types' => [
                ['id' => LabTestContract::CUSTOMER_LEGAL, 'name' => 'Yuridik shaxs', 'tin_length' => 9],
                ['id' => LabTestContract::CUSTOMER_INDIVIDUAL, 'name' => 'Jismoniy shaxs', 'tin_length' => 14],
            ],
            'construction_types' => self::CONSTRUCTION_TYPES,
            'return_reasons' => self::RETURN_REASONS,
            'work_types' => $this->testRepository->workTypes(),
            'methods' => $this->testRepository->methods(),
            'employees' => $user->region_id ? $this->repository->employees($user->region_id) : [],
            'vat_percent' => 12,
        ];
    }

    /** Ro'yxat ustidagi kartochkalar: jami va holatlar bo'yicha arizalar soni */
    public function statistics($user, $roleId): array
    {
        $counts = $this->repository->statusCounts($this->scope($user, $roleId));

        return [
            'all' => array_sum($counts),
            'statuses' => array_map(fn($c) => [
                'id' => $c->value, 'name' => $c->label(), 'count' => $counts[$c->value] ?? 0,
            ], Status::cases()),
        ];
    }

    /** Laborantlar ro'yxati (boshliq kabineti): faol va jami arizalar soni bilan */
    public function employees($user, $roleId)
    {
        $this->ensure($this->is($roleId, self::LAB_HEAD_ROLES));

        return $this->repository->employees($user->region_id ?? 0);
    }

    public function all(array $filters, $user, $roleId)
    {
        if (($filters['filter'] ?? null) === 'waiting') {
            // joriy rol qarorini kutayotgan arizalar; bunday bosqichi yo'q rolga bo'sh ro'yxat
            $filters['waiting_status'] = $this->waitingStatus($roleId)?->value ?? -1;
        }

        $data = $this->repository->paginate($this->scope($user, $roleId, $filters), $filters, (int)($filters['per_page'] ?? 15));
        $data->getCollection()->each(fn(LabTestContract $c) => $this->present($c, $user, $roleId));

        return $data;
    }

    public function show($id, $user, $roleId): LabTestContract
    {
        $contract = $this->repository->find($this->scope($user, $roleId), $id, array_merge(self::DETAIL_RELATIONS, ['histories.user']));

        return $this->withTaken($this->present($contract, $user, $roleId));
    }

    /**
     * Qo'shimcha shartnomada: oldingi shartnomalarda tanlab bo'lingan sinovlar (frontend ularni o'chirib ko'rsatadi).
     * block_id - shu shartnomadagi (ko'chirilgan) blok id si.
     */
    private function withTaken(LabTestContract $contract): LabTestContract
    {
        $rows = [];
        if ($contract->parent_id) {
            $taken = $this->repository->takenIndicators($contract);
            foreach ($contract->blocks->whereNotNull('parent_block_id') as $block) {
                foreach ($taken as $key => $floors) {
                    [$rootBlock, $workType, $method] = array_map('intval', explode(':', $key));
                    if ($rootBlock !== $block->rootId()) {
                        continue;
                    }
                    // floor: qavatli ishda qavat raqami, qavatsiz ishda null
                    foreach ($floors as $floor => $indicators) {
                        $rows[] = [
                            'block_id' => $block->id, 'work_type_id' => $workType, 'lab_method_id' => $method,
                            'floor' => $floor ?: null, 'indicator_ids' => array_keys($indicators),
                        ];
                    }
                }
            }
        }

        return $contract->setAttribute('taken_indicators', $rows);
    }

    /**
     * Shartnoma fayli (PDF, base64). Buxgalterga yuborilgandan boshlab ko'rinadi: buyurtmachi tasdiqlamaguncha
     * «NAMUNA» belgisi bilan, tasdiqlagach - tasdiqlangan holatda. Buyurtmachi uni shartnoma o'ziga yetib kelgach ko'radi.
     */
    public function pdf($id, $user, $roleId): array
    {
        $contract = $this->repository->find($this->scope($user, $roleId), $id, array_merge(self::DETAIL_RELATIONS, ['histories.user']));
        if (!$this->pdfVisible($contract, $roleId)) {
            throw new HttpException(422, $this->is($roleId, self::CUSTOMER_ROLES)
                ? 'Shartnoma hali tayyor emas'
                : "Shartnoma fayli buxgalterga yuborilgandan keyin shakllanadi");
        }

        return [
            'file_name' => 'shartnoma-' . str_replace('/', '-', $contract->contract_number) . '.pdf',
            'is_draft' => $this->pdfService->isDraft($contract),
            'file' => base64_encode($this->pdfService->render($contract)),
        ];
    }

    private function pdfVisible(LabTestContract $contract, $roleId): bool
    {
        $min = $this->is($roleId, self::CUSTOMER_ROLES) ? Status::CUSTOMER : Status::ACCOUNTANT;

        return $contract->status >= $min->value;
    }

    public function history($id, $user, $roleId)
    {
        return $this->repository->histories($this->repository->find($this->scope($user, $roleId), $id));
    }

    /** Laboratoriya boshlig'i ariza yaratadi va laborantga biriktiradi */
    public function create(array $data, $user, $roleId): LabTestContract
    {
        $this->ensure($this->is($roleId, self::LAB_HEAD_ROLES));
        if (!$user->region_id) {
            throw new HttpException(422, 'Foydalanuvchiga viloyat biriktirilmagan');
        }

        // ariza boshliqning hududida yaratiladi; obyekt boshqa hududda bo'lsa ham laboratoriya shu hududniki
        $attributes = $this->attributes($data, $user->region_id);
        $attributes['region_id'] = $user->region_id;
        $attributes['documents'] = $this->documents($data);

        $contract = $this->repository->create($attributes, $user->id, (int)$roleId, $attributes['comment']);

        return $this->show($contract->id, $user, $roleId);
    }

    /**
     * Qo'shimcha shartnoma: tasdiqlangan asosiy shartnomaga yangi blok yoki oldin tanlanmagan sinovlar qo'shiladi.
     * Asosiy shartnoma tasdiqlangan bo'lishi va oilada (asosiy + qo'shimchalar) jarayondagi shartnoma bo'lmasligi kerak.
     * $id - asosiy shartnoma yoki uning istalgan qo'shimchasi.
     */
    public function createAdditional($id, array $data, $user, $roleId): LabTestContract
    {
        $this->ensure($this->is($roleId, self::LAB_HEAD_ROLES));
        $contract = $this->repository->find($this->scope($user, $roleId), $id);
        $root = $contract->parent_id ? $this->repository->find($this->scope($user, $roleId), $contract->parent_id) : $contract;

        if ($root->statusEnum() !== Status::CONFIRMED) {
            throw new HttpException(422, "Qo'shimcha shartnoma faqat tasdiqlangan shartnomaga tuziladi");
        }
        if ($this->repository->hasActiveInFamily($root->id)) {
            throw new HttpException(422, "Bu shartnoma bo'yicha jarayondagi qo'shimcha shartnoma bor - avval u tasdiqlanishi yoki bekor qilinishi kerak");
        }
        $employeeId = (int)($data['employee_id'] ?? $root->employee_id);
        if (!$this->repository->isEmployee($employeeId, $root->region_id)) {
            throw new HttpException(422, 'Tanlangan xodim shu hudud laboranti emas');
        }

        $additional = $this->repository->createAdditional($root, $employeeId, $user->id, (int)$roleId, $data['comment'] ?? null);

        return $this->show($additional->id, $user, $roleId);
    }

    /** Boshliq arizani hali laborantda turganida tahrirlaydi (masalan, laborantni almashtiradi) */
    public function update($id, array $data, $user, $roleId): LabTestContract
    {
        $contract = $this->headContract($id, $user, $roleId, Status::LABORANT);

        // qo'shimcha shartnomada obyekt va buyurtmachi asosiy shartnomadan olingan - faqat laborant va izoh o'zgaradi
        if ($contract->parent_id) {
            $employeeId = (int)($data['employee_id'] ?? $contract->employee_id);
            if (!$this->repository->isEmployee($employeeId, $contract->region_id)) {
                throw new HttpException(422, 'Tanlangan xodim shu hudud laboranti emas');
            }
            $contract->changeStatus(null, $user->id, (int)$roleId, null, [
                'employee_id' => $employeeId,
                'comment' => array_key_exists('comment', $data) ? $data['comment'] : $contract->comment,
            ], ['action' => 'update', 'old_employee_id' => $contract->employee_id, 'employee_id' => $employeeId]);

            return $this->show($id, $user, $roleId);
        }

        $attributes = $this->attributes($data, $contract->region_id);
        $new = $this->documents($data);
        if ($new) {
            $attributes['documents'] = $new;
        }
        if ((int)$attributes['test_type'] !== (int)$contract->test_type && $contract->items()->exists()) {
            throw new HttpException(422, "Ishlar tanlangan arizaning sinov usulini o'zgartirib bo'lmaydi");
        }

        $contract->changeStatus(null, $user->id, (int)$roleId, null, $attributes, [
            'action' => 'update',
            'old_employee_id' => $contract->employee_id,
            'employee_id' => $attributes['employee_id'],
        ]);

        return $this->show($id, $user, $roleId);
    }

    /** Laborant bloklarni kiritadi: nomi, qavatlar soni, GPS, fundament va har bir qavat ma'lumotlari */
    public function saveBlocks($id, array $blocks, $user, $roleId): LabTestContract
    {
        $contract = $this->employeeContract($id, $user, $roleId);

        // ko'chirilgan (oldingi shartnomalardagi) bloklar o'zgarmaydi va o'chirilmaydi: so'rovda nima kelishidan
        // qat'i nazar bazadagi holicha qoladi, yangi bloklar ulardan keyin qo'shiladi
        $inherited = $contract->blocks()->whereNotNull('parent_block_id')->get();
        if ($inherited->isNotEmpty()) {
            $ids = $inherited->modelKeys();
            $new = array_filter($blocks, fn($block) => empty($block['id']) || !in_array((int)$block['id'], $ids, true));
            $blocks = array_merge(
                $inherited->map(fn($b) => ['id' => $b->id, 'name' => $b->name, 'floors_count' => $b->floors_count, 'data' => $b->data])->all(),
                array_values($new)
            );
        }

        $own = $contract->blocks()->pluck('id')->all();
        $names = [];
        foreach (array_values($blocks) as $i => $block) {
            if (!empty($block['id']) && !in_array((int)$block['id'], $own, true)) {
                throw new HttpException(422, ($i + 1) . '-blok bu arizaga tegishli emas');
            }
            $name = mb_strtolower(trim($block['name']));
            if (isset($names[$name])) {
                throw new HttpException(422, "«{$block['name']}» nomi bir necha blokda takrorlangan");
            }
            $names[$name] = true;
        }

        $this->repository->syncBlocks($contract, $blocks);

        return $this->show($id, $user, $roleId);
    }

    /**
     * Laborant ishlarni tanlaydi: ro'yxat to'liq almashtiriladi, narx va summalar serverda hisoblanadi.
     */
    public function saveItems($id, array $items, $user, $roleId): LabTestContract
    {
        $contract = $this->employeeContract($id, $user, $roleId);

        $blocks = $contract->blocks()->get()->keyBy('id');
        $indicators = $this->repository->indicators(collect($items)
            ->flatMap(fn($item) => array_merge($item['indicator_ids'] ?? [], ...array_values($item['floor_indicator_ids'] ?? [])))
            ->unique()->all());
        $methods = $this->testRepository->methods()->keyBy('id');
        // qo'shimcha shartnomada: oldingi shartnomalarda shu blok, ish turi va usul bo'yicha tanlab bo'lingan sinovlar
        $taken = $contract->parent_id ? $this->repository->takenIndicators($contract) : [];
        $seen = [];
        $rows = [];

        foreach (array_values($items) as $i => $item) {
            $n = $i + 1;
            $block = $blocks->get((int)$item['block_id']);
            if (!$block) {
                throw new HttpException(422, "$n-qator: blok bu arizaga tegishli emas");
            }
            $key = $block->id . ':' . $item['work_type_id'] . ':' . $item['lab_method_id'];
            if (isset($seen[$key])) {
                throw new HttpException(422, "$n-qator: bu blokda shu ish turi va usul allaqachon tanlangan");
            }
            $seen[$key] = true;

            // sinovlar ro'yxatini tekshiradi va narxlari yig'indisini qaytaradi; $floor - qavatli ishda qavat raqami
            $price = function (array $ids, ?int $floor) use ($indicators, $item, $contract, $taken, $block, $n): int {
                $where = $floor === null ? "$n-qator" : "$n-qator, $floor-qavat";
                $sum = 0;
                foreach ($ids as $indicatorId) {
                    $indicator = $indicators->get($indicatorId);
                    if (!$indicator || (int)$indicator->lab_method_id !== (int)$item['lab_method_id']) {
                        throw new HttpException(422, "$where: tanlangan sinov shu usulga tegishli emas");
                    }
                    if (!$indicator->is_available) {
                        throw new HttpException(422, "$where: «{$indicator->name}» sinovi laboratoriyada mavjud emas");
                    }
                    // laboratoriya usulida barcha sinovlar, qolgan usullarda faqat shu turdagilari tanlanadi
                    if ($contract->test_type !== LabTestTypeEnum::LABORATORY->value && $indicator->test_type !== $contract->test_type) {
                        throw new HttpException(422, "$where: «{$indicator->name}» sinovi «{$contract->test_type_name}» usuliga kirmaydi");
                    }
                    $key = $block->rootId() . ':' . (int)$item['work_type_id'] . ':' . (int)$item['lab_method_id'];
                    $takenIn = $taken[$key][$floor ?? 0][$indicatorId] ?? null;
                    if ($takenIn) {
                        throw new HttpException(422, "$where: «{$indicator->name}» sinovi bu blokda {$takenIn} shartnomada allaqachon belgilangan");
                    }
                    $sum += (int)$indicator->price;
                }

                return $sum;
            };
            $clean = function ($ids): array {
                $ids = array_values(array_unique(array_map('intval', (array)$ids)));
                sort($ids);

                return $ids;
            };

            $floorQuantities = $proposedFloors = $floorIndicators = $floorPrices = null;
            if ($this->testRepository->workTypeHasFloors($item['work_type_id'])) {
                $floorQuantities = $this->floorQuantities($item['floor_quantities'] ?? [], $block->floors_count, $n);
                $proposedFloors = isset($item['proposed_floor_quantities'])
                    ? $this->floorQuantities($item['proposed_floor_quantities'], $block->floors_count, $n)
                    : null;
                $quantity = array_sum($floorQuantities);
                $proposed = $proposedFloors === null ? null : array_sum($proposedFloors);

                // sinovlar har qavat uchun alohida; qavat ko'rsatilmagan bo'lsa - umumiy indicator_ids
                $floorIndicators = $floorPrices = [];
                $total = 0;
                foreach ($floorQuantities as $floor => $floorQuantity) {
                    $floorIds = $clean($item['floor_indicator_ids'][$floor] ?? $item['indicator_ids'] ?? []);
                    if (!$floorIds) {
                        throw new HttpException(422, "$n-qator, $floor-qavat: sinov tanlanmagan");
                    }
                    $floorIndicators[$floor] = $floorIds;
                    $floorPrices[$floor] = $price($floorIds, (int)$floor);
                    $total += $floorQuantity * $floorPrices[$floor];
                }
                $ids = $clean(array_merge(...array_values($floorIndicators ?: [[]])));
                // qavatlar narxi har xil bo'lishi mumkin - unit_price o'rtacha (ma'lumot uchun), hisob floor_unit_prices bo'yicha
                $unitPrice = $quantity > 0 ? (int)round($total / $quantity) : 0;
            } else {
                $ids = $clean($item['indicator_ids'] ?? []);
                if (!$ids) {
                    throw new HttpException(422, "$n-qator: sinov tanlanmagan");
                }
                $unitPrice = $price($ids, null);
                $quantity = (int)($item['quantity'] ?? 0);
                $proposed = $item['proposed_quantity'] ?? null;
                $total = $quantity * $unitPrice;
            }
            if ($quantity <= 0) {
                throw new HttpException(422, "$n-qator: miqdor kiritilmagan");
            }

            $vat = (int)round($total * $contract->vat_percent / 100);
            $rows[] = [
                'lab_test_contract_block_id' => $block->id,
                'work_type_id' => (int)$item['work_type_id'],
                'lab_method_id' => (int)$item['lab_method_id'],
                'indicator_ids' => $ids,
                'quantity' => $quantity,
                'proposed_quantity' => $proposed,
                'floor_quantities' => $floorQuantities,
                'proposed_floor_quantities' => $proposedFloors,
                'floor_indicators' => $floorIndicators,
                'floor_unit_prices' => $floorPrices,
                'unit' => $methods->get((int)$item['lab_method_id'])?->unit,
                'unit_price' => $unitPrice,
                'total_price' => $total,
                'total_vat' => $vat,
                'total_with_vat' => $total + $vat,
            ];
        }

        $this->repository->replaceItems($contract, $rows);

        return $this->show($id, $user, $roleId);
    }

    /** Laborant shartnomani buxgalterga yuboradi */
    public function send($id, $user, $roleId): LabTestContract
    {
        $contract = $this->employeeContract($id, $user, $roleId);

        $blocks = $contract->blocks()->get();
        if ($blocks->isEmpty()) {
            throw new HttpException(422, "Bloklar ma'lumoti kiritilmagan");
        }
        if ($contract->test_type === LabTestTypeEnum::LABORATORY->value) {
            foreach ($blocks as $block) {
                if ((float)data_get($block->data, 'fund.V', 0) <= 0) {
                    throw new HttpException(422, "{$block->name}: fundament beton hajmi kiritilmagan");
                }
            }
        }
        if ($contract->total_price <= 0) {
            throw new HttpException(422, "Tanlangan ishlar yo'q yoki summa 0");
        }

        $contract->changeStatus(Status::ACCOUNTANT, $user->id, (int)$roleId, null, ['return_data' => null]);

        return $this->show($id, $user, $roleId);
    }

    /**
     * Tasdiqlash: buxgalter -> inspeksiya boshlig'iga, inspeksiya boshlig'i -> buyurtmachiga, buyurtmachi -> tasdiqlangan.
     */
    public function approve($id, $user, $roleId): LabTestContract
    {
        $contract = $this->repository->find($this->scope($user, $roleId), $id);
        $this->ensureDecider($contract, $user, $roleId);

        $next = match ($contract->statusEnum()) {
            Status::ACCOUNTANT => Status::DIRECTOR,
            Status::DIRECTOR => Status::CUSTOMER,
            default => Status::CONFIRMED,
        };
        $contract->changeStatus($next, $user->id, (int)$roleId);

        return $this->show($id, $user, $roleId);
    }

    /** Buxgalter yoki inspeksiya boshlig'i arizani laborantga qaytaradi */
    public function returnToEmployee($id, string $comment, array $reasons, $user, $roleId): LabTestContract
    {
        $contract = $this->repository->find($this->scope($user, $roleId), $id);
        if (!in_array($contract->statusEnum(), [Status::ACCOUNTANT, Status::DIRECTOR], true)) {
            throw new HttpException(422, "Ariza «{$contract->status_name}» holatida - laborantga qaytarib bo'lmaydi");
        }
        $this->ensureDecider($contract, $user, $roleId);

        $returnData = [
            'by_role' => (int)$roleId,
            'by_name' => $contract->statusEnum() === Status::ACCOUNTANT ? 'Buxgalter' : "Inspeksiya boshlig'i",
            'user_id' => $user->id,
            'at' => now()->toJSON(),
            'comment' => $comment,
            'reasons' => array_values($reasons),
        ];
        $contract->changeStatus(Status::LABORANT, $user->id, (int)$roleId, $comment, ['return_data' => $returnData], ['reasons' => array_values($reasons)]);

        // qaytarilgan ariza buxgalter / inspeksiya boshlig'ining ko'rish doirasidan chiqib ketishi mumkin,
        // shuning uchun javob doira tekshiruvisiz qaytariladi
        return $this->present($contract->fresh(self::DETAIL_RELATIONS), $user, $roleId);
    }

    /** Buyurtmachi shartnomani bekor qiladi - ariza bekor bo'ladi, bu amal qaytarilmaydi */
    public function cancel($id, ?string $comment, $user, $roleId): LabTestContract
    {
        $contract = $this->repository->find($this->scope($user, $roleId), $id);
        $this->ensureStatus($contract, Status::CUSTOMER);
        $this->ensureDecider($contract, $user, $roleId);

        $contract->changeStatus(Status::CANCELLED, $user->id, (int)$roleId, $comment);

        return $this->show($id, $user, $roleId);
    }

    /** Tasdiqlangan shartnomaga obyekt biriktirish - shundan so'ng laborant sinov o'tkaza oladi */
    public function attachObject($id, int $objectId, $user, $roleId): LabTestContract
    {
        $contract = $this->headContract($id, $user, $roleId, Status::CONFIRMED);
        if ($contract->parent_id) {
            throw new HttpException(422, "Obyekt asosiy shartnomaga biriktiriladi - qo'shimcha shartnoma uni o'zi oladi");
        }
        if ($contract->tests()->exists() || $this->repository->hasFamilyTests($contract->id)) {
            throw new HttpException(422, "Sinovlar boshlangan shartnomaning obyektini o'zgartirib bo'lmaydi");
        }

        $contract->changeStatus(null, $user->id, (int)$roleId, null, ['object_id' => $objectId], [
            'action' => 'attach_object',
            'object_id' => $objectId,
            'old_object_id' => $contract->object_id,
        ]);
        $this->repository->attachObjectToAdditionals($contract, $objectId);

        return $this->show($id, $user, $roleId);
    }

    /** Boshliq hali laborantda turgan arizani o'chira oladi */
    public function delete($id, $user, $roleId): void
    {
        $this->repository->delete($this->headContract($id, $user, $roleId, Status::LABORANT));
    }

    /** Laborant sinov o'tkazadigan ishlar: o'ziga biriktirilgan, tasdiqlangan, obyekt biriktirilgan shartnomalardan */
    public function assignedItems(array $filters, $user, $roleId)
    {
        $this->ensure($this->is($roleId, self::LABORANT_ROLES));

        return $this->repository->assignedItems($user->id, $filters, (int)($filters['per_page'] ?? 15));
    }

    /** Shartnoma biriktirilgan obyektlar: har kim o'z doirasidagi (laborant - o'ziga biriktirilgan) shartnomalar bo'yicha */
    public function objects(array $filters, $user, $roleId)
    {
        return $this->repository->objects($this->scope($user, $roleId, $filters), $filters, (int)($filters['per_page'] ?? 15));
    }

    /** So'rovdagi maydonlardan ariza ustunlari; mas'ul laborant shu hudud laboranti bo'lishi kerak */
    private function attributes(array $data, $regionId): array
    {
        if (!$this->repository->isEmployee($data['employee_id'], $regionId)) {
            throw new HttpException(422, 'Tanlangan xodim shu hudud laboranti emas');
        }
        $hasExpertise = (bool)$data['expertise_exists'];

        return [
            'object_type_id' => (int)$data['object_type_id'],
            'object_name' => trim($data['object_name']),
            'construction_type' => $data['construction_type'],
            'district' => trim($data['district']),
            'address' => trim($data['address']),
            'cadastral_number' => $data['cadastral_number'] ?? null,
            'owner_name' => $data['owner_name'] ?? null,
            'object_id' => $data['object_id'] ?? null,
            'expertise_exists' => $hasExpertise,
            'expertise_number' => $hasExpertise ? $data['expertise_number'] : null,
            'customer_type' => (int)$data['customer_type'],
            'customer_tin' => (string)$data['customer_tin'],
            'customer_name' => trim($data['customer_name']),
            'customer_phone' => trim($data['customer_phone']),
            'test_type' => (int)$data['test_type'],
            'employee_id' => (int)$data['employee_id'],
            'comment' => $data['comment'] ?? null,
        ];
    }

    /** Hujjatlar: yuklangan fayllar va (xulosa bo'lsa) ekspertiza tizimidan olingan hujjatlar ro'yxati */
    private function documents(array $data): array
    {
        $uploaded = collect($data['documents'] ?? [])->map(fn($file) => [
            'name' => $file->getClientOriginalName(),
            'path' => $file->store('lab-test-contracts', 'public'),
            'url' => null,
        ]);
        $external = collect($data['expertise_documents'] ?? [])->map(fn($doc) => [
            'name' => $doc['name'],
            'path' => null,
            'url' => $doc['url'] ?? null,
        ]);

        return $external->concat($uploaded)->values()->all();
    }

    /** Qavat => miqdor: qavat raqami blok qavatlari doirasida bo'lishi kerak, nol miqdorlar tashlab yuboriladi */
    private function floorQuantities(array $quantities, int $floorsCount, int $n): array
    {
        $result = [];
        foreach ($quantities as $floor => $quantity) {
            if (!ctype_digit((string)$floor) || (int)$floor < 1 || (int)$floor > $floorsCount) {
                throw new HttpException(422, "$n-qator: $floor-qavat blokda yo'q (qavatlar soni: $floorsCount)");
            }
            if ((int)$quantity > 0) {
                $result[(int)$floor] = (int)$quantity;
            }
        }
        ksort($result);

        return $result;
    }

    private function headContract($id, $user, $roleId, Status $status): LabTestContract
    {
        $this->ensure($this->is($roleId, self::LAB_HEAD_ROLES));
        $contract = $this->repository->find($this->scope($user, $roleId), $id);
        $this->ensureStatus($contract, $status);

        return $contract;
    }

    /** Arizani faqat unga biriktirilgan laborant, faqat «Laborantda» holatida to'ldiradi */
    private function employeeContract($id, $user, $roleId): LabTestContract
    {
        $this->ensure($this->is($roleId, self::LABORANT_ROLES));
        $contract = $this->repository->find($this->scope($user, $roleId), $id);
        $this->ensureStatus($contract, Status::LABORANT);

        return $contract;
    }

    /** Joriy holatda qaror qabul qiladigan rol (buyurtmachi - faqat o'z arizasida) */
    private function ensureDecider(LabTestContract $contract, $user, $roleId): void
    {
        $waiting = $this->waitingStatus($roleId);
        $this->ensure($waiting !== null
            && $waiting !== Status::LABORANT
            && $waiting === $contract->statusEnum()
            && (!$this->is($roleId, self::CUSTOMER_ROLES) || $this->isCustomer($contract, $user)));
    }

    /** Rol qaysi holatdagi arizalar bo'yicha harakat qiladi */
    private function waitingStatus($roleId): ?Status
    {
        return match (true) {
            $this->is($roleId, self::LABORANT_ROLES) => Status::LABORANT,
            $this->is($roleId, self::ACCOUNTANT_ROLES) => Status::ACCOUNTANT,
            $this->is($roleId, self::DIRECTOR_ROLES) => Status::DIRECTOR,
            $this->is($roleId, self::CUSTOMER_ROLES) => Status::CUSTOMER,
            default => null,
        };
    }

    /**
     * Ko'rish doirasi: respublika / admin - hammasi; laboratoriya boshlig'i - o'z hududi; laborant - o'ziga
     * biriktirilganlari; buxgalter - hududidagi buxgalterga yetib kelganlari; inspeksiya boshlig'i - o'ziga
     * yetib kelganlari; buyurtmachi - STIR / JShShIR i mos keladiganlari.
     */
    private function scope($user, $roleId, array $filters = []): array
    {
        $scope = ['region_id' => null, 'employee_id' => null, 'customer_tin' => null, 'min_status' => null];
        $region = $user->region_id ?? 0;

        if ($this->is($roleId, self::ALL_REGION_ROLES)) {
            $scope['region_id'] = $filters['region_id'] ?? null;
        } elseif ($this->is($roleId, self::LAB_HEAD_ROLES)) {
            $scope['region_id'] = $region;
        } elseif ($this->is($roleId, self::LABORANT_ROLES)) {
            $scope['employee_id'] = $user->id;
        } elseif ($this->is($roleId, self::ACCOUNTANT_ROLES)) {
            $scope['region_id'] = $region;
            $scope['min_status'] = Status::ACCOUNTANT->value;
        } elseif ($this->is($roleId, self::DIRECTOR_ROLES)) {
            $scope['region_id'] = $region;
            $scope['min_status'] = Status::DIRECTOR->value;
        } else {
            // buyurtmachi va boshqa rollar: faqat o'z STIR / JShShIR i bo'yicha (bo'sh bo'lsa hech narsa)
            $scope['customer_tin'] = (string)($user->pinfl ?: '-');
        }

        return $scope;
    }

    /** Hujjat havolalari va joriy foydalanuvchi bajara oladigan amallar (frontend tugmalari uchun) */
    private function present(LabTestContract $contract, $user, $roleId): LabTestContract
    {
        $status = $contract->statusEnum();
        $head = $this->is($roleId, self::LAB_HEAD_ROLES);
        $employee = $this->is($roleId, self::LABORANT_ROLES) && (int)$contract->employee_id === (int)$user->id;
        $customer = $this->is($roleId, self::CUSTOMER_ROLES);
        $decider = $this->waitingStatus($roleId) === $status
            && $status !== Status::LABORANT
            && (!$customer || $this->isCustomer($contract, $user));

        $contract->setAttribute('actions', [
            'update' => $head && $status === Status::LABORANT,
            'delete' => $head && $status === Status::LABORANT,
            'save_blocks' => $employee && $status === Status::LABORANT,
            'save_items' => $employee && $status === Status::LABORANT,
            'send' => $employee && $status === Status::LABORANT,
            'approve' => $decider,
            'return' => $decider && in_array($status, [Status::ACCOUNTANT, Status::DIRECTOR], true),
            'cancel' => $decider && $status === Status::CUSTOMER,
            'attach_object' => $head && $status === Status::CONFIRMED && !$contract->parent_id,
            // qo'shimcha shartnoma: asosiy shartnoma tasdiqlangan va oilada jarayondagi shartnoma yo'q
            'create_additional' => $head && $status === Status::CONFIRMED
                && (!$contract->parent_id || $contract->parent?->status === Status::CONFIRMED->value)
                && !$this->repository->hasActiveInFamily($contract->parent_id ?? $contract->id),
            'pdf' => $this->pdfVisible($contract, $roleId),
        ]);
        // shartnoma fayli tasdiqlanmagan (namuna) holatdami
        $contract->setAttribute('pdf_is_draft', $status !== Status::CONFIRMED);
        $contract->setAttribute('documents', collect($contract->documents ?? [])
            ->map(fn($doc) => array_merge($doc, [
                'url' => !empty($doc['path']) ? asset('storage/' . $doc['path']) : ($doc['url'] ?? null),
            ]))
            ->all());

        // buyurtmachi shartnomani faqat o'ziga yetib kelgach ko'radi - ungacha blok, ish va summalar yashiriladi
        if ($customer && $status->value < Status::CUSTOMER->value) {
            $contract->setRelation('blocks', collect());
            $contract->setRelation('items', collect());
            $contract->setAttribute('total_price', 0);
            $contract->setAttribute('total_vat', 0);
            $contract->setAttribute('total_with_vat', 0);
            $contract->setAttribute('return_data', null);
        }

        return $contract;
    }

    private function isCustomer(LabTestContract $contract, $user): bool
    {
        return $contract->customer_tin !== null && (string)$contract->customer_tin === (string)$user->pinfl;
    }

    private function is($roleId, array $roles): bool
    {
        return in_array((int)$roleId, $roles, true);
    }

    private function ensure(bool $allowed): void
    {
        if (!$allowed) {
            throw new HttpException(403, "Ruxsat yo'q");
        }
    }

    private function ensureStatus(LabTestContract $contract, Status $status): void
    {
        if ($contract->statusEnum() !== $status) {
            throw new HttpException(422, "Bu amal faqat «{$status->label()}» holatida bajariladi");
        }
    }
}
