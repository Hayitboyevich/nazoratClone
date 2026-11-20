<?php

namespace App\Services;

use App\Enums\LabTestContractStatusEnum as Status;
use App\Models\LabTestContract;
use App\Repositories\Interfaces\LabTestContractRepositoryInterface;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Laboratoriya shartnomasi fayli (shartnoma matni + 1-ilova: ishlar ro'yxati va narxi + 2-ilova).
 * Buyurtmachi tasdiqlamaguncha fayl «NAMUNA» belgisi bilan, tasdiqlangach sana va tasdiq belgilari bilan chiqadi.
 */
class LabTestContractPdfService
{
    private const MONTHS = [
        1 => 'yanvar', 'fevral', 'mart', 'aprel', 'may', 'iyun',
        'iyul', 'avgust', 'sentabr', 'oktabr', 'noyabr', 'dekabr',
    ];

    private const ONES = ['', 'bir', 'ikki', 'uch', 'to‘rt', 'besh', 'olti', 'yetti', 'sakkiz', 'to‘qqiz'];
    private const TENS = ['', 'o‘n', 'yigirma', 'o‘ttiz', 'qirq', 'ellik', 'oltmish', 'yetmish', 'sakson', 'to‘qson'];
    private const SCALES = ['', 'ming', 'million', 'milliard', 'trillion'];

    public function __construct(
        public LabTestContractRepositoryInterface $repository
    )
    {
    }

    /** PDF mazmuni (binar satr) */
    public function render(LabTestContract $contract): string
    {
        return Pdf::loadView('lab-pdf.test-contract', $this->data($contract))
            ->setPaper('a4')
            ->output();
    }

    public function isDraft(LabTestContract $contract): bool
    {
        return $contract->statusEnum() !== Status::CONFIRMED;
    }

    public function data(LabTestContract $contract): array
    {
        $contract->loadMissing(['blocks', 'items.method.indicators', 'items.workType', 'region:id,name_uz', 'histories.user']);

        $draft = $this->isDraft($contract);
        $inspection = $this->repository->inspection($contract->region_id);
        $requisite = $this->repository->requisite($contract->region_id, $contract->district);
        $blocks = $this->blocks($contract);

        // tasdiqlagan shaxslar: inspeksiya boshlig'i (3 -> 4) va buyurtmachi (4 -> 5)
        $approval = fn(Status $to) => $contract->histories
            ->first(fn($h) => (int)$h->to_status === $to->value && (int)$h->from_status === $to->value - 1);
        $director = $approval(Status::CUSTOMER);
        $customer = $approval(Status::CONFIRMED);

        $total = (float)$contract->total_with_vat;

        return [
            'contract' => $contract,
            'draft' => $draft,
            'cancelled' => $contract->statusEnum() === Status::CANCELLED,
            'inspection' => $inspection,
            'requisite' => $requisite,
            'regionName' => $contract->region?->name_uz,
            'directorName' => $inspection?->full_name ?: $this->userName($director?->user),
            'date' => $draft ? null : $this->date($contract->confirmed_at),
            'directorSignedAt' => $draft ? null : $this->date($director?->created_at, true),
            'customerSignedAt' => $draft ? null : $this->date($customer?->created_at, true),
            'isLegal' => (int)$contract->customer_type !== LabTestContract::CUSTOMER_INDIVIDUAL,
            'objectTitle' => $this->objectTitle($contract),
            'blocks' => $blocks,
            'blockNames' => implode(', ', array_column($blocks, 'name')),
            'totalText' => $this->money($total),
            'totalWords' => $this->moneyWords($total),
        ];
    }

    /**
     * 1-ilova qatorlari: blok -> bosqich (Kotlovan, Fundament, N-qavat) -> ishlar.
     * Qavatli ish turida bitta shartnoma qatori har bir qavat uchun alohida qatorga yoyiladi.
     */
    private function blocks(LabTestContract $contract): array
    {
        $number = 0;
        $result = [];

        foreach ($contract->blocks as $block) {
            $items = $contract->items->where('lab_test_contract_block_id', $block->id);
            $stages = [];

            $plain = $items->filter(fn($i) => !$i->workType?->is_multiple_floor)->groupBy('work_type_id')->sortKeys();
            foreach ($plain as $group) {
                $title = Str::before((string)$group->first()->workType?->name, ' va ');
                $stages[] = $this->stage($block->name . ' · ' . $title, $group->map(fn($i) => [$i, (int)$i->quantity, null]), $number);
            }

            $floored = $items->filter(fn($i) => (bool)$i->workType?->is_multiple_floor);
            for ($floor = 1; $floor <= (int)$block->floors_count; $floor++) {
                $rows = $floored
                    ->map(fn($i) => [$i, (int)(($i->floor_quantities ?? [])[$floor] ?? 0), $floor])
                    ->filter(fn($row) => $row[1] > 0);
                if ($rows->isNotEmpty()) {
                    $stages[] = $this->stage($block->name . ' · ' . $floor . '-qavat', $rows, $number);
                }
            }

            // qo'shimcha shartnomada ish tanlanmagan (faqat ko'chirilgan) bloklar ilovaga chiqmaydi
            if (!$stages && $contract->parent_id) {
                continue;
            }

            $result[] = [
                'name' => $block->name,
                'stages' => $stages,
                'sum' => array_sum(array_column($stages, 'sum')),
            ];
        }

        return $result;
    }

    private function stage(string $title, $rows, int &$number): array
    {
        $lines = [];
        foreach ($rows as [$item, $quantity, $floor]) {
            // qavatli ishda sinovlar va narx shu qavatniki
            $selected = collect($item->indicatorIdsFor($floor));
            $unitPrice = (float)$item->unitPriceFor($floor);
            $lines[] = [
                'n' => ++$number,
                'name' => $item->method?->name,
                'tests' => ($item->method?->indicators ?? collect())
                    ->filter(fn($ind) => $selected->contains($ind->id))
                    ->map(fn($ind) => trim($ind->name . ($ind->standard ? " ({$ind->standard})" : '')) . ' — ' . $this->money($ind->price, 0))
                    ->values()->all(),
                'quantity' => $quantity,
                'unit' => $item->unit,
                'unit_price' => $unitPrice,
                'sum' => $quantity * $unitPrice,
            ];
        }

        return ['title' => $title, 'rows' => $lines, 'sum' => array_sum(array_column($lines, 'sum'))];
    }

    private function objectTitle(LabTestContract $contract): string
    {
        $place = collect([$contract->region?->name_uz, $contract->district, $contract->address])->filter()->implode(', ');
        $withItems = $contract->items->pluck('lab_test_contract_block_id')->unique();
        $blocks = $contract->blocks
            ->filter(fn($b) => !$contract->parent_id || $withItems->contains($b->id))
            ->pluck('name')->implode(', ');

        return trim($place . ' hududida ' . $contract->object_name) . ($blocks ? " ({$blocks})" : '');
    }

    private function userName($user): ?string
    {
        return $user ? trim(implode(' ', array_filter([$user->surname, $user->name, $user->middle_name]))) : null;
    }

    /** «2026-yil 11-noyabr» */
    private function date($value, bool $withTime = false): ?string
    {
        if (!$value) {
            return null;
        }
        $date = Carbon::parse($value);

        return $date->format('Y') . '-yil ' . $date->format('j') . '-' . self::MONTHS[(int)$date->format('n')]
            . ($withTime ? ' ' . $date->format('H:i') : '');
    }

    /** «73 785 600.00» */
    public function money($value, int $decimals = 2): string
    {
        return number_format((float)$value, $decimals, '.', ' ');
    }

    /** «yetmish uch million ... olti yuz so‘m 00 tiyin» */
    public function moneyWords(float $value): string
    {
        $sum = (int)floor(round($value, 2));
        $tiyin = (int)round((round($value, 2) - $sum) * 100);

        return $this->words($sum) . ' so‘m ' . sprintf('%02d', $tiyin) . ' tiyin';
    }

    private function words(int $number): string
    {
        if ($number === 0) {
            return 'nol';
        }

        $parts = [];
        for ($scale = 0; $number > 0; $scale++, $number = intdiv($number, 1000)) {
            $chunk = $number % 1000;
            if ($chunk === 0) {
                continue;
            }
            $words = array_filter([
                intdiv($chunk, 100) ? self::ONES[intdiv($chunk, 100)] . ' yuz' : '',
                self::TENS[intdiv($chunk % 100, 10)],
                self::ONES[$chunk % 10],
                self::SCALES[$scale] ?? '',
            ]);
            array_unshift($parts, implode(' ', $words));
        }

        return implode(' ', $parts);
    }
}
