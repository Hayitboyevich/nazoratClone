<?php

namespace App\Services;

use App\Enums\LabTestContractStatusEnum;
use App\Enums\LabInstrumentStatusEnum;
use App\Enums\LabTestResultEnum;
use App\Enums\LabTestStatusEnum;
use App\Enums\UserRoleEnum;
use App\Models\LabTest;
use App\Models\LabTestContractItem;
use App\Repositories\Interfaces\LabTestRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Laboratoriya sinovlari biznes-qoidalari.
 * Yaratish / tahrirlash / bayonnoma shakllantirish - laborant (o'z sinovlari).
 * Bayonnomani tasdiqlash / rad etish / tahrirga qaytarish - LabTest::REVIEWER_ROLES (o'z hududi).
 * Ruxsat yoki holat xatolari HttpException (403 / 422) sifatida tashlanadi.
 */
class LabTestService
{
    /** Sinov yaratadigan rollar */
    private const CREATOR_ROLES = [
        UserRoleEnum::LABORANT->value,
    ];

    private const DETAIL_RELATIONS = ['method:id,code,name', 'method.indicators', 'workType', 'contract', 'block'];

    public function __construct(
        public LabTestRepositoryInterface $repository,
        public LabTestProtocolPdfService $pdfService
    )
    {
    }

    public function dictionaries(): array
    {
        return [
            'methods' => $this->repository->methods(),
            'work_types' => $this->repository->workTypes(),
            'statuses' => array_map(fn($c) => ['id' => $c->value, 'name' => $c->label()], LabTestStatusEnum::cases()),
            'results' => array_map(fn($c) => ['id' => $c->value, 'name' => $c->label()], LabTestResultEnum::cases()),
        ];
    }

    public function all(array $filters, $user, $roleId)
    {
        $this->ensure($this->canView($roleId));

        $data = $this->repository->paginate($this->scope($user, $roleId, $filters), $filters, (int)($filters['per_page'] ?? 15));
        $data->getCollection()->each(fn(LabTest $test) => $this->withAbilities($test, $user, $roleId));

        return $data;
    }

    public function show($id, $user, $roleId): LabTest
    {
        $this->ensure($this->canView($roleId));

        $test = $this->repository->find($this->scope($user, $roleId), $id, [
            'method:id,code,name', 'method.indicators', 'workType', 'contract', 'block', 'region:id,name_uz', 'creator', 'reviewer', 'statusHistories.user',
        ]);

        return $this->withInstruments($this->withAbilities($test, $user, $roleId));
    }

    /**
     * Bayonnoma fayli (PDF, base64). Sinovni ko'ra oladigan har kim ochadi; rahbar tasdiqlamaguncha «NAMUNA»
     * belgisi bilan (laborant yuborishdan oldin ko'rib olishi uchun), tasdiqlangach - tasdiq belgisi bilan.
     */
    public function pdf($id, $user, $roleId): array
    {
        $test = $this->show($id, $user, $roleId);

        return [
            'file_name' => 'bayonnoma-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', $test->protocol_number ?: 'sinov-' . $test->id) . '.pdf',
            'is_draft' => $this->pdfService->isDraft($test),
            'file' => base64_encode($this->pdfService->render($test)),
        ];
    }

    public function create(array $data, $user, $roleId): LabTest
    {
        $this->ensure($this->canCreate($roleId));

        // Sinovni shartnomaning mas'ul laboranti, faqat tasdiqlangan va obyekt biriktirilgan shartnoma bo'yicha o'tkazadi
        $item = $this->repository->contractItem($data['lab_contract_item_id']);
        $contract = $item->contract;
        $this->ensure((int)$contract->employee_id === (int)$user->id);
        if ($contract->statusEnum() !== LabTestContractStatusEnum::CONFIRMED || !$contract->object_id) {
            throw new HttpException(422, 'Shartnoma tasdiqlanmagan yoki unga obyekt biriktirilmagan');
        }

        $data = $this->normalize($data);
        $floor = $data['floor'] ?? null;
        $this->ensureQuantity($item, $floor, (int)$data['quantity']);
        $this->ensureInstruments($data['instrument_ids'] ?? [], (int)$item->lab_method_id, (int)$contract->region_id, $data['test_date'] ?? null);

        $test = $this->repository->create(array_merge($data, [
            'floor' => $floor,
            'lab_contract_id' => $contract->id,
            'lab_contract_block_id' => $item->lab_test_contract_block_id,
            'lab_method_id' => $item->lab_method_id,
            'work_type_id' => $item->work_type_id,
            'region_id' => $contract->region_id,
        ]), $user->id, (int)$roleId);

        return $this->withInstruments($test->load(self::DETAIL_RELATIONS));
    }

    /**
     * Ma'lumotlarni tahrirlash (status o'zgarmaydi) - eski input_data / result_data tarixda saqlanadi.
     */
    public function update($id, array $data, $user, $roleId): LabTest
    {
        $test = $this->editable($id, $user, $roleId);

        $data = $this->normalize($data);
        $data['floor'] = $data['floor'] ?? null;
        $this->ensureQuantity($test->contractItem, $data['floor'], (int)$data['quantity'], $test->id);
        // oldin tanlangan asbob qayta tekshirilmaydi: sinov o'tkazilgan kuni u yaroqli bo'lgan
        $this->ensureInstruments(
            array_diff($data['instrument_ids'] ?? [], $test->instrument_ids ?? []),
            (int)$test->lab_method_id, (int)$test->region_id, $data['test_date'] ?? $test->test_date
        );
        $test->changeStatus(null, $user->id, (int)$roleId, null, $data);

        return $this->withInstruments($test->fresh(self::DETAIL_RELATIONS));
    }

    /**
     * Sinov yakuni: bayonnoma shakllantiriladi va rahbar tasdig'iga yuboriladi.
     */
    public function formProtocol($id, array $data, $user, $roleId): LabTest
    {
        $test = $this->editable($id, $user, $roleId);

        $attributes = array_filter($data, fn($v) => $v !== null);
        if (empty($attributes['result']) && !$test->result) {
            throw new HttpException(422, "Sinov natijasi ko'rsatilishi shart: 1 - ijobiy, 2 - salbiy");
        }

        DB::transaction(function () use ($test, $attributes, $user, $roleId) {
            // raqam bir marta beriladi: tahrirga qaytarilgan bayonnoma qayta shakllantirilganda o'zgarmaydi
            if (!$test->protocol_number) {
                $attributes['protocol_number'] = $this->repository->nextProtocolNumber($test);
            }
            $test->changeStatus(LabTestStatusEnum::PROTOCOL_FORMED, $user->id, (int)$roleId, null, $attributes);
        });

        return $test->fresh(self::DETAIL_RELATIONS);
    }

    /**
     * Rahbar qarori: tasdiqlash, tahrirga qaytarish yoki rad etish.
     */
    public function review($id, LabTestStatusEnum $decision, ?string $comment, $user, $roleId): LabTest
    {
        $this->ensure($this->isReviewer($roleId));

        $test = $this->repository->find($this->scope($user, $roleId), $id);
        if ($test->statusEnum() !== LabTestStatusEnum::PROTOCOL_FORMED) {
            throw new HttpException(422, "Faqat tasdiq kutilayotgan bayonnoma bo'yicha qaror qabul qilinadi");
        }
        $test->changeStatus($decision, $user->id, (int)$roleId, $comment);

        return $test->fresh(self::DETAIL_RELATIONS);
    }

    /**
     * Faqat hali bayonnomasi shakllantirilmagan (Jarayonda) sinovni o'chirish mumkin.
     */
    public function delete($id, $user, $roleId): void
    {
        $test = $this->editable($id, $user, $roleId);
        if ($test->statusEnum() !== LabTestStatusEnum::IN_PROGRESS) {
            throw new HttpException(422, "Bayonnomasi shakllantirilgan sinovni o'chirib bo'lmaydi");
        }

        $this->repository->delete($test);
    }

    public function history($id, $user, $roleId)
    {
        $this->ensure($this->canView($roleId));

        return $this->repository->histories($this->repository->find($this->scope($user, $roleId), $id));
    }

    /**
     * Bitta ish (qavatli ishda - bitta qavat) bo'yicha bir nechta sinov va bayonnoma bo'lishi mumkin,
     * lekin ular qoplagan miqdor yig'indisi shartnomadagi miqdordan oshmaydi (rad etilganlar sanalmaydi).
     */
    private function ensureQuantity(LabTestContractItem $item, ?int $floor, int $quantity, $exceptId = null): void
    {
        $limit = $floor === null ? (int)$item->quantity : $item->floorQuantity($floor);
        $left = $limit - $this->repository->usedQuantity($item->id, $floor, $exceptId);
        if ($quantity > $left) {
            $where = $floor === null ? 'Bu ish' : "Bu ish va $floor-qavat";
            throw new HttpException(422, $left > 0
                ? "$where bo'yicha shartnomada $limit {$item->unit} belgilangan, sinov o'tkazilmagani $left {$item->unit} qoldi"
                : "$where bo'yicha shartnomadagi miqdor ($limit {$item->unit}) to'liq sinovdan o'tkazilgan");
        }
    }

    /**
     * Sinovda faqat shu sinov turiga bog'langan, o'z hududidagi, ishlayotgan va sinov sanasida (berilmasa - bugun)
     * amaldagi sertifikati bor asbob ishlatiladi.
     */
    private function ensureInstruments(array $ids, int $methodId, int $regionId, $date = null): void
    {
        if (!$ids) return;

        $instruments = $this->repository->instruments($ids)->keyBy('id');
        foreach ($ids as $id) {
            $instrument = $instruments->get($id);
            if (!$instrument || $instrument->trashed() || (int)$instrument->region_id !== $regionId) {
                throw new HttpException(422, "Asbob topilmadi (id: $id)");
            }
            $name = "«{$instrument->name}» ({$instrument->code})";
            if (!$instrument->methods->contains('id', $methodId)) {
                throw new HttpException(422, "$name asbobi bu sinov turiga bog'lanmagan");
            }
            if ($instrument->status !== LabInstrumentStatusEnum::WORKING->value) {
                throw new HttpException(422, "$name asbobini ishlatib bo'lmaydi, holati: {$instrument->status_name}");
            }
            if (!$instrument->certificateOn($date)) {
                throw new HttpException(422, $date
                    ? "$name asbobining sinov sanasida amaldagi sertifikati yo'q"
                    : "$name asbobining amaldagi sertifikati yo'q");
            }
        }
    }

    /** Sinovda ishlatilgan asboblar (bayonnoma uchun): sertifikati sinov sanasidagi (bo'lmasa yaratilgan sanadagi) holati bo'yicha */
    private function withInstruments(LabTest $test): LabTest
    {
        $ids = $test->instrument_ids ?? [];
        $test->setAttribute('instruments', $ids ? $this->repository->instruments($ids)->map(function ($instrument) use ($test) {
            $date = $test->test_date ?? $test->created_at;
            $certificate = $instrument->certificateOn($date) ?? $instrument->currentCertificate($date);

            return [
                'id' => $instrument->id,
                'code' => $instrument->code,
                'name' => $instrument->name,
                'type' => $instrument->type,
                'type_name' => $instrument->type_name,
                'serial_number' => $instrument->serial_number,
                'unit_name' => $instrument->unit_name,
                'range_from' => $instrument->range_from,
                'range_to' => $instrument->range_to,
                'resolution' => $instrument->resolution,
                'certificate' => $certificate ? [
                    'id' => $certificate->id,
                    'doc_type_name' => $certificate->doc_type_name,
                    'number' => $certificate->number,
                    'issued_at' => $certificate->issued_at?->toDateString(),
                    'valid_to' => $certificate->valid_to?->toDateString(),
                ] : null,
            ];
        })->values() : []);

        return $test;
    }

    /** Ko'rsatkichlar va asboblar tartiblangan butun sonlar ro'yxati bo'ladi */
    private function normalize(array $data): array
    {
        if (isset($data['floor'])) {
            $data['floor'] = (int)$data['floor'];
        }
        $data['indicator_ids'] = array_values(array_map('intval', $data['indicator_ids']));
        sort($data['indicator_ids']);
        // asboblar yuborilmasa tahrirda avvalgisi qoladi
        if (isset($data['instrument_ids'])) {
            $data['instrument_ids'] = array_values(array_unique(array_map('intval', $data['instrument_ids'])));
            sort($data['instrument_ids']);
        } else {
            unset($data['instrument_ids']);
        }

        return $data;
    }

    /** Sinovni topadi va joriy foydalanuvchi uni tahrirlay olishini tekshiradi */
    private function editable($id, $user, $roleId): LabTest
    {
        $test = $this->repository->find($this->scope($user, $roleId), $id);
        $this->ensure($this->canEdit($test, $user, $roleId));

        return $test;
    }

    /**
     * Ko'rish doirasi: respublika / admin - hammasi (region_id filtri bilan), laborant - o'zi yaratganlari,
     * qolganlar - o'z hududi (hududi yo'q foydalanuvchi hech narsa ko'rmaydi).
     */
    private function scope($user, $roleId, array $filters = []): array
    {
        if ($this->seesAllRegions($roleId)) {
            return ['region_id' => $filters['region_id'] ?? null, 'created_by' => null];
        }
        if ($this->canCreate($roleId)) {
            return ['region_id' => null, 'created_by' => $user->id];
        }

        return ['region_id' => $user->region_id ?? 0, 'created_by' => null];
    }

    /** Frontend tugmalari uchun: joriy foydalanuvchi shu sinov bilan nima qila oladi */
    private function withAbilities(LabTest $test, $user, $roleId): LabTest
    {
        $test->setAttribute('can_edit', $this->canEdit($test, $user, $roleId));
        $test->setAttribute('can_review', $this->isReviewer($roleId) && $test->statusEnum() === LabTestStatusEnum::PROTOCOL_FORMED);

        return $test;
    }

    private function canEdit(LabTest $test, $user, $roleId): bool
    {
        return $this->canCreate($roleId)
            && (int)$test->created_by === (int)$user->id
            && $test->statusEnum()->isEditable();
    }

    private function seesAllRegions($roleId): bool
    {
        return in_array((int)$roleId, [UserRoleEnum::RES_LAB->value, UserRoleEnum::ADMIN->value]);
    }

    private function canCreate($roleId): bool
    {
        return in_array((int)$roleId, self::CREATOR_ROLES);
    }

    private function isReviewer($roleId): bool
    {
        return in_array((int)$roleId, LabTest::REVIEWER_ROLES);
    }

    private function canView($roleId): bool
    {
        return $this->canCreate($roleId) || $this->isReviewer($roleId) || $this->seesAllRegions($roleId);
    }

    private function ensure(bool $allowed): void
    {
        if (!$allowed) {
            throw new HttpException(403, "Ruxsat yo'q");
        }
    }
}
