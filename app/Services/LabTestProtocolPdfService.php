<?php

namespace App\Services;

use App\Enums\LabTestStatusEnum;
use App\Models\Article;
use App\Models\LabTest;
use App\Models\LabTestContract;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

/**
 * Sinov bayonnomasi fayli. Shablon barcha sinov turlari uchun bitta (lab-pdf.test-protocol):
 *  - sarlavha, buyurtmachi, obyekt, asboblar, imzolar - serverdagi ma'lumotdan;
 *  - natijalar jadvali - result_data.groups dan (hisobni frontend qiladi): har qatorda me'yor, haqiqiy qiymat, muvofiqlik;
 *  - sinov turiga xos qatorlar (qabul dalolatnomasi, sharoit, ko'rsatkich nomlari) - shu yerdagi layout metodlaridan.
 * Yangi sinov turi layoutsiz ham chiqadi: ko'rsatkich nomlarini va qo'shimcha qatorlarni result_data da o'zi beradi.
 * Rahbar tasdiqlamaguncha fayl «NAMUNA» belgisi bilan chiqadi.
 */
class LabTestProtocolPdfService
{
    /** G'isht (OʻzMSt 705:2025): kod => [nomi, birlik, talab bandi, sinov usuli] */
    private const GISHT = [
        't1' => ["Geometrik o'lchamlar (uzunlik, kenglik, qalinlik)", 'mm', 'OʻzMSt 705:2025, 5.2.1, 5.2.2 (2-, 3-jadval)', '8.3'],
        't2' => ["Shaklning to'g'riligi (perpendikulyarlik, tekislikdan og'ish)", 'mm', 'OʻzMSt 705:2025, 5.2.2 (3-jadval), 5.2.3', '8.4.1, 8.4.2'],
        't3' => ["Tashqi ko'rinishdagi nuqsonlar", 'dona; mm', 'OʻzMSt 705:2025, 5.3.3 (4-jadval)', '8.3.4, 8.4.4'],
        't4' => ['Suv shimuvchanlik', '%', 'OʻzMSt 705:2025, 5.4.4', "8.9; GOST 7025-91, 2-bo'lim"],
        't5' => ["O'rtacha zichlik va zichlik sinfi", 'kg/m³', 'OʻzMSt 705:2025, 5.4.1 (5-jadval)', "8.9; GOST 7025-91, 5-bo'lim"],
        't6' => ['Siqilishdagi mustahkamlik chegarasi (marka)', 'MPa', 'OʻzMSt 705:2025, 5.4.3 (6-jadval)', '8.8; GOST 8462-85, 3.2'],
        't7' => ['Egilishdagi mustahkamlik chegarasi', 'MPa', 'OʻzMSt 705:2025, 5.4.3 (6-jadval)', '8.8; GOST 8462-85, 3.3'],
        't8' => ["Bo'shliqlik", '%', 'OʻzMSt 705:2025, 5.2.6', '8.7'],
        't9' => ['Sovuqqa chidamlilik', 'davr', 'OʻzMSt 705:2025, 5.4.7', "8.9; GOST 7025-91, 4-bo'lim"],
        't10' => ['Tabiiy radionuklidlar (Asam)', 'Bq/kg', 'OʻzMSt 705:2025, 5.4.9', 'GOST 30108-94'],
    ];

    private const GISHT_TYPES = [
        'G‘-BQ' => "Qator qilib terilgan bir qatlamli g'isht",
        'G‘-Q' => "Qator qilib terilgan qalinlashtirilgan g'isht",
        'T' => 'Tosh',
        'T-Y' => 'Yiriklashtirilgan tosh',
        'Boshqa' => "Boshqa o'lchamdagi mahsulot",
    ];

    /** Armatura (GOST 12004-81 va boshq.): kod => [nomi, birlik, talab (grade bo'yicha - frontenddan), sinov usuli] */
    private const ARMATURA = [
        's1' => ["Boshlang'ich ko'ndalang kesim yuzasi (F₀)", 'mm²', null, 'GOST 12004-81, 1.4-band'],
        's2' => ['Uzilgandan keyingi nisbiy uzayish (δ5)', '%', null, 'GOST 12004-81, 3.1-band'],
        's3' => ['Tekis nisbiy uzayish (δр)', '%', null, 'GOST 12004-81, 3.2-band'],
        's4' => ['Oquvchanlik chegarasi (σт)', 'N/mm²', null, 'GOST 12004-81, 3.6-band'],
        's5' => ['Vaqtinchalik qarshilik (σв)', 'N/mm²', null, 'GOST 12004-81, 3.5-band'],
        's6' => ['Egishga bardoshlilik (plastik deformatsiya)', '—', null, 'GOST 14019-2003'],
        's7' => ['Profilning geometrik parametrlari (h, t, Σe)', 'mm', null, 'GOST 34028-2016, A ilova'],
        's8' => ["Ko'ndalang qovurg'alarning nisbiy ezilish yuzasi (fR)", '—', null, "O'zDSt 3025:2015, V ilova"],
    ];

    /** PDF mazmuni (binar satr). $test->instruments oldindan to'ldirilgan bo'lishi kerak (LabTestService::pdf) */
    public function render(LabTest $test): string
    {
        return Pdf::loadView('lab-pdf.test-protocol', $this->data($test))
            ->setPaper('a4')
            // shriftning faqat ishlatilgan belgilari qo'shiladi - fayl ~10 barobar kichrayadi
            ->setOption('isFontSubsettingEnabled', true)
            ->output();
    }

    public function isDraft(LabTest $test): bool
    {
        return $test->statusEnum() !== LabTestStatusEnum::CONFIRMED;
    }

    public function data(LabTest $test): array
    {
        $test->loadMissing(['method:id,code,name', 'workType', 'block', 'creator', 'reviewer']);
        $contract = LabTestContract::query()->with('parent')->find($test->lab_contract_id);
        $object = $contract?->object_id
            ? Article::query()->with(['region:id,name_uz', 'district:id,name_uz'])->find($contract->object_id, ['id', 'name', 'location_building', 'address', 'region_id', 'district_id'])
            : null;

        $code = (string)$test->method?->code;
        $in = $test->input_data ?? [];
        $out = $test->result_data ?? [];
        $status = $test->statusEnum();

        $common = [
            'customer' => $contract?->customer_name ?: '—',
            'object' => $object?->name ?: ($contract?->object_name ?: '—'),
            // murojaat - asosiy shartnoma (ariza) raqami va sanasi
            'appeal' => $contract ? '№ ' . $contract->contract_number . ', ' . $this->date($contract->created_at) : '—',
            'address' => $object
                ? implode(', ', array_filter([$object->region?->name_uz, $object->district?->name_uz, $object->location_building ?: $object->address]))
                : '',
        ];

        $layout = match ($code) {
            'gisht' => $this->gisht($in, $out, $common),
            'armatura' => $this->armatura($in, $out, $common),
            default => $this->generic($test, $in, $out, $common),
        };

        return array_merge($layout, [
            'test' => $test,
            'number' => $test->protocol_number ?: '—',
            'draft' => $this->isDraft($test),
            'rejected' => $status === LabTestStatusEnum::REJECTED,
            'formedAt' => $this->date($test->protocol_formed_at),
            'confirmedAt' => $status === LabTestStatusEnum::CONFIRMED ? $this->date($test->reviewed_at) : null,
            'laborant' => $this->userName($test->creator),
            'reviewer' => $status === LabTestStatusEnum::CONFIRMED ? $this->userName($test->reviewer) : null,
            'resultName' => $test->result_name,
            'summary' => $this->table($out['summary'] ?? null),
            'notes' => array_values(array_filter((array)($out['notes'] ?? $layout['notes'] ?? []), 'is_string')),
            'instruments' => collect($test->getAttribute('instruments') ?? [])->all(),
        ]);
    }

    /** G'isht: bitta bayonnomada bir nechta partiya, har biri natijalar jadvalida alohida guruh */
    private function gisht(array $in, array $out, array $common): array
    {
        $parts = collect($in['parts'] ?? [])->filter(fn($p) => is_array($p))->values();
        $tests = $parts->flatMap(fn($p) => array_keys(array_filter((array)($p['tests'] ?? []))))->unique()
            ->filter(fn($k) => isset(self::GISHT[$k]))->sortBy(fn($k) => (int)substr($k, 1))->values();
        $purpose = $tests->map(fn($k) => mb_strtolower(self::GISHT[$k][0]))->implode(', ');

        $titles = $parts->mapWithKeys(function ($p) {
            $type = (string)($p['type'] ?? '');
            $title = 'Partiya №' . ($p['no'] ?? '') . (empty($p['retestOf']) ? '' : " (partiya №{$p['retestOf']} bo'yicha takroriy sinov, 7.9)") . ': '
                . (self::GISHT_TYPES[$type] ?? $type) . " ($type)"
                . (empty($p['hol']) ? '' : ', ' . $p['hol'])
                . (($p['klink'] ?? '') === 'Ha' ? ', klinker' : '')
                . (str_starts_with((string)($p['use'] ?? ''), 'fasad') ? ', fasad' : '')
                . '; deklaratsiya: M' . ($p['marka'] ?? '?')
                . (isset($p['dclass']) ? ', zichlik sinfi ' . $this->num($p['dclass'], 1) : '')
                . (isset($p['fdecl']) ? ', F' . $p['fdecl'] : '')
                . ' · identifikatsiya: ' . (($p['ident'] ?? '') ?: '—')
                . " · to'p: " . (($p['lot'] ?? '') ?: '—')
                . ' · ishlab chiqaruvchi: ' . (($p['mfr'] ?? '') ?: '—');
            return [(string)($p['no'] ?? '') => $title];
        });

        return [
            'style' => 'gisht',
            'labName' => ($in['lab'] ?? '') ?: 'Qurilish sinov laboratoriyasi',
            'labAddress' => ($in['addr'] ?? '') ?: $common['address'],
            'info' => [
                ['Buyurtmachi', $common['customer']],
                ['Qurilish obyekti', $common['object']],
                ['Sinov obyekti', "Sopol g'isht va toshlar — {$parts->count()} partiya (pastdagi jadvalda)"],
                ['Namunalarni qabul qilish', 'Dalolatnoma № ' . (($in['act'] ?? '') ?: '—') . ', ' . ($this->date($in['actDate'] ?? null) ?: '—')
                    . '; qabul qilingan: ' . ($this->date($in['recv'] ?? null) ?: '—') . '; qabul qildi: ' . (($in['receiver'] ?? '') ?: '—')],
                ['Sinov maqsadi', "Namunalarning $purpose bo'yicha OʻzMSt 705:2025 talablariga muvofiqligini aniqlash"],
                ['Talablarni belgilovchi NH', "OʻzMSt 705:2025 «Sopol g'isht va toshlar. Texnik shartlar»"],
                ["Sinov usullari bo'yicha NH", "OʻzMSt 705:2025, 8-bo'lim; GOST 8462-85; GOST 7025-91" . ($tests->contains('t10') ? '; GOST 30108-94' : '')],
                ['Sinov davri', ($this->date($in['date'] ?? null) ?: '—') . ' — ' . ($this->date($in['dateEnd'] ?? null) ?: '—')],
                ['Harorat va nisbiy namlik', $this->num($in['T'] ?? null, 1) . ' °C; ' . $this->num($in['phi'] ?? null, 1) . ' %'],
                ["Sinov usuliga qo'shimchalar va og'ishlar", ($in['note'] ?? '') ?: "Yo'q"],
            ],
            'groups' => $this->groups($out, self::GISHT, fn($g) => $titles[(string)($g['no'] ?? '')] ?? null, true),
            'notes' => ["Qaror qabul qilish qoidasi: ILAC-G8:09/2019 (1-jadval: xato qabul qilish ehtimoli 5 % dan kam; himoya oralig'i w = "
                . $this->num($in['set']['kw'] ?? 0.83, 2) . "·U). O'lcham, shakl va tashqi ko'rinish OʻzMSt 705:2025, 7.8 va 7.14-bandlari bo'yicha nomuvofiq mahsulotlar soni orqali baholanadi"
                . " (ruxsat: o'lcham va shakl — " . ($in['set']['agen'] ?? 1) . ", tashqi ko'rinish — " . ($in['set']['avis'] ?? 3) . ")."
                . " Fizik-mexanik ko'rsatkichlar bo'yicha nomuvofiqlikda 7.9-band bo'yicha takroriy sinov o'tkaziladi."],
            'summaryTitle' => "Partiyalar bo'yicha xulosa",
            'signers' => ['Sinovni bajardi', 'Bayonnomani rasmiylashtirdi', "Metrologiya bo'yicha mas'ul"],
        ];
    }

    /** Armatura: har bir namuna (marka + diametr) natijalar jadvalida alohida guruh */
    private function armatura(array $in, array $out, array $common): array
    {
        $samples = collect($in['samples'] ?? [])->filter(fn($s) => is_array($s))->values();
        $acc = (array)($in['acceptance'] ?? []);
        $join = fn(callable $fn) => $samples->map($fn)->filter()->unique()->implode('; ') ?: '—';

        return [
            'style' => 'armatura',
            'info' => [
                ['Murojaat', $common['appeal']],
                ['Buyurtmachi', $common['customer']],
                ['Obyekt', $common['object']],
                ['Mahsulot nomi', 'Temir-beton konstruksiyalar uchun armatura prokati'],
                ['Marka (sinf), diametr', $join(fn($s) => trim(($s['grade'] ?? '') . (isset($s['diameter']) ? ', Ø' . $s['diameter'] . ' mm' : ''), ', '))],
                ['Markirovka / partiya', $join(fn($s) => (($s['marking'] ?? '') ?: '—') . ' / ' . (($s['batch'] ?? '') ?: '—'))],
                ['Namunani qabul qilish', 'Dalolatnoma № ' . (($acc['act_number'] ?? '') ?: '—') . ', ' . ($this->date($acc['act_date'] ?? null) ?: '—')
                    . '; qabul qilingan: ' . ($this->date($acc['received_at'] ?? null) ?: '—') . '; qabul qildi: ' . (($acc['received_by'] ?? '') ?: '—')],
                ["Sinov o'tkazilgan davr", ($this->date($acc['started_at'] ?? ($in['test_date'] ?? null)) ?: '—') . ' – ' . ($this->date($acc['finished_at'] ?? null) ?: '—')],
                ['Sharoit', 'Harorat ' . $this->num($in['temperature'] ?? null, 1) . ' °C, namlik ' . $this->num($in['humidity'] ?? null, 1) . ' %'],
                ['Namunalar (nusxalar) soni', (string)($samples->sum(fn($s) => (int)($s['copies'] ?? 0)) ?: '—')],
                ["Sinov usuliga qo'shimchalar va og'ishlar", ($acc['note'] ?? '') ?: "Yo'q"],
            ],
            'groups' => $this->groups($out, self::ARMATURA, null, $samples->count() > 1),
            'notes' => ["Qaror qabul qilish qoidasi ILAC-G8:09/2019 ga muvofiq (himoya oralig'i w = 0,83·U). Natijalar har bir nusxa bo'yicha keltirilgan"
                . " (GOST 12004-81, 3.9-band); ko'rsatkich barcha nusxalar muvofiq bo'lganda muvofiq hisoblanadi."],
            'summaryTitle' => 'Xulosa',
            'signers' => ['Sinovni bajardi', 'Bayonnomani rasmiylashtirdi', "Metrologiya bo'yicha mas'ul"],
        ];
    }

    /** Layouti yozilmagan sinov turi: umumiy qatorlar + result_data.info dagi qo'shimcha qatorlar */
    private function generic(LabTest $test, array $in, array $out, array $common): array
    {
        $extra = collect($out['info'] ?? [])
            ->filter(fn($row) => is_array($row) && count($row) >= 2)
            ->map(fn($row) => [(string)array_values($row)[0], (string)array_values($row)[1]])
            ->values()->all();

        return [
            'style' => 'generic',
            'info' => array_merge([
                ['Murojaat', $common['appeal']],
                ['Buyurtmachi', $common['customer']],
                ['Obyekt', $common['object']],
                ['Sinov turi', (string)$test->method?->name],
                ['Blok, bosqich', implode(' · ', array_filter([$test->block?->name, $test->workType?->name, $test->floor ? $test->floor . '-qavat' : null]))],
                ['Sinov sanasi', $this->date($test->test_date) ?: '—'],
            ], $extra),
            'groups' => $this->groups($out, [], null, true),
            'notes' => [],
            'summaryTitle' => 'Xulosa',
            'signers' => ['Sinovni bajardi', 'Bayonnomani rasmiylashtirdi', "Metrologiya bo'yicha mas'ul"],
        ];
    }

    /**
     * result_data.groups -> natijalar jadvali. Qator: code, name, unit, requirement, method, norm, fact, verdict.
     * name / unit / requirement / method berilmasa - sinov turi katalogidan (code bo'yicha) olinadi.
     * $numbered: qatorlar «guruh.qator» (1.1, 1.2) ko'rinishida raqamlanadi, aks holda 1, 2, 3.
     */
    private function groups(array $out, array $catalog, ?callable $title, bool $numbered): array
    {
        $groups = collect($out['groups'] ?? [])->filter(fn($g) => is_array($g))->values();

        return $groups->map(function ($group, $gi) use ($catalog, $title, $numbered) {
            $rows = collect($group['rows'] ?? [])->filter(fn($r) => is_array($r))->values();

            return [
                'no' => $group['no'] ?? ($gi + 1),
                'title' => ($group['title'] ?? null) ?: ($title ? $title($group) : null),
                'rows' => $rows->map(function ($row, $ri) use ($catalog, $numbered, $gi, $group) {
                    $meta = $catalog[$row['code'] ?? ''] ?? [null, null, null, null];
                    return [
                        'no' => $numbered ? ($group['no'] ?? ($gi + 1)) . '.' . ($ri + 1) : (string)($ri + 1),
                        'name' => (string)(($row['name'] ?? null) ?: ($meta[0] ?? ($row['code'] ?? ''))),
                        'unit' => (string)(($row['unit'] ?? null) ?: ($meta[1] ?? '')),
                        'requirement' => (string)(($row['requirement'] ?? null) ?: ($meta[2] ?? '')),
                        'method' => (string)(($row['method'] ?? null) ?: ($meta[3] ?? '')),
                        'norm' => (string)($row['norm'] ?? ''),
                        'fact' => (string)($row['fact'] ?? ''),
                        'verdict' => (string)($row['verdict'] ?? ''),
                    ];
                })->all(),
            ];
        })->all();
    }

    /** Ixtiyoriy jadval: {heads: [...], rows: [[...], ...]} */
    private function table($table): ?array
    {
        if (!is_array($table) || empty($table['rows']) || !is_array($table['rows'])) {
            return null;
        }

        return [
            'heads' => array_map('strval', array_values((array)($table['heads'] ?? []))),
            'rows' => array_map(fn($r) => array_map(fn($c) => is_scalar($c) ? (string)$c : '', array_values((array)$r)), $table['rows']),
        ];
    }

    private function date($value): ?string
    {
        if (!$value) return null;
        try {
            return Carbon::parse($value)->format('d.m.Y');
        } catch (\Throwable) {
            return (string)$value;
        }
    }

    private function num($value, int $decimals): string
    {
        return is_numeric($value) ? number_format((float)$value, $decimals, ',', '') : '___';
    }

    private function userName($user): ?string
    {
        if (!$user) return null;
        $initials = collect([$user->name, $user->middle_name])->filter()->map(fn($p) => mb_substr($p, 0, 1) . '.')->implode('');

        return trim(($user->surname ?? '') . ' ' . $initials) ?: null;
    }
}
