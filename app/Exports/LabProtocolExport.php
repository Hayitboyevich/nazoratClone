<?php

namespace App\Exports;

use Illuminate\Support\Arr;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LabProtocolExport implements FromArray, WithEvents, WithTitle
{
    public const SCHEME_V = 'V';
    public const SCHEME_G = 'G';

    private string $scheme;
    private array $data;

    public function __construct(string $scheme, array $data = [])
    {
        $this->scheme = $scheme === self::SCHEME_G ? self::SCHEME_G : self::SCHEME_V;
        $this->data = $data;
    }

    public function array(): array
    {
        return [];
    }

    public function title(): string
    {
        return $this->scheme === self::SCHEME_G
            ? 'Sinov bayonnoma - Г sxema'
            : 'Sinov bayonnoma - В sxema';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                if ($this->scheme === self::SCHEME_G) {
                    $this->buildSchemeG($sheet);
                } else {
                    $this->buildSchemeV($sheet);
                }
            },
        ];
    }

    // ------------------------------------------------------------------
    // Request data accessors
    // ------------------------------------------------------------------

    private function field(string $key, string $default = ''): string
    {
        return (string) Arr::get($this->data, $key, $default);
    }

    private function equipmentList(): array
    {
        return array_values(Arr::get($this->data, 'equipment', []));
    }

    private function equipmentLine(array $item): string
    {
        $line = trim((string) ($item['name'] ?? ''));

        if (!empty($item['serial_number'])) {
            $line .= '  №' . $item['serial_number'];
        }

        if (!empty($item['certificate_number']) || !empty($item['valid_until'])) {
            $cert = trim(
                (!empty($item['certificate_number']) ? '№' . $item['certificate_number'] : '')
                . ' ' . ($item['valid_until'] ?? '')
            );
            $line .= "  ({$cert} yil)";
        }

        return $line . ',';
    }

    private function designClass($default = null)
    {
        return Arr::get($this->data, 'design_class', $default);
    }

    private function avgVf($default = null)
    {
        return Arr::get($this->data, 'avg_vf', $default);
    }

    private function items(): array
    {
        $items = Arr::get($this->data, 'items', []);
        if (empty($items)) {
            return [];
        }

        return array_is_list($items) ? $items : [$items];
    }

    private function meetsNormLabel($meetsNorm): string
    {
        if ($meetsNorm === null || $meetsNorm === '') {
            return '';
        }

        return $meetsNorm ? 'Mos keladi' : 'Mos kelmaydi';
    }

    private function groups(): array
    {
        $count = max(1, count($this->items()));

        $groups = [];
        for ($i = 0; $i < $count; $i++) {
            $groups[] = ['top' => 42 + $i * 6];
        }

        return $groups;
    }

    private function lastGroupRow(): int
    {
        $groups = $this->groups();
        $group = end($groups);

        return $group['top'] + 5;
    }

    // ------------------------------------------------------------------
    // Common helpers
    // ------------------------------------------------------------------

    private function set(Worksheet $sheet, string $coord, $value): void
    {
        $sheet->setCellValue($coord, $value);
    }

    private function merge(Worksheet $sheet, string $range): void
    {
        $sheet->mergeCells($range);
    }

    private function bold(Worksheet $sheet, string $range, int $size = 9): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true)->setSize($size)->setName('Arial');
    }

    private function centerWrap(Worksheet $sheet, string $range, string $vertical = Alignment::VERTICAL_CENTER): void
    {
        $sheet->getStyle($range)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical($vertical)
            ->setWrapText(true);
    }

    private function box(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    private function baseFont(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setName('Arial')->setSize(9);
    }

    private function drawLogo(Worksheet $sheet, string $coordinate): void
    {
        $path = public_path('images/icon/gerb.png');
        if (!file_exists($path)) {
            return;
        }

        $drawing = new Drawing();
        $drawing->setName('Gerb');
        $drawing->setDescription('Gerb');
        $drawing->setPath($path);
        $drawing->setHeight(70);
        $drawing->setOffsetX(8);
        $drawing->setOffsetY(4);
        $drawing->setCoordinates($coordinate);
        $drawing->setWorksheet($sheet);
    }

    // ------------------------------------------------------------------
    // Scheme В
    // ------------------------------------------------------------------

    private function buildSchemeV(Worksheet $sheet): void
    {
        $lastGroupRow = $this->lastGroupRow();
        $footerStart = $lastGroupRow + 2;
        $footnotesStart = $lastGroupRow + 10;
        $lastRow = $lastGroupRow + 12;

        $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT);
        $this->baseFont($sheet, "A1:AA{$lastRow}");

        $this->writeLetterheadV($sheet, 1);
        $this->writeInfoTableV($sheet);
        $this->writeFootnotesV($sheet, 33);
        $this->writeLetterheadV($sheet, 36);

        $this->merge($sheet, 'A40:AA40');
        $this->set($sheet, 'A40', 'SINOV NATIJALARI');
        $this->bold($sheet, 'A40:AA40', 11);
        $this->centerWrap($sheet, 'A40:AA40');

        $this->writeResultsHeaderV($sheet);
        $this->writeResultsBodyV($sheet);

        $this->writeFooter($sheet, 'V', $footerStart);
        $this->writeFootnotesV($sheet, $footnotesStart);

        $sheet->getColumnDimension('A')->setWidth(16);
        foreach (['E', 'G', 'R', 'X', 'Z'] as $col) {
            $sheet->getColumnDimension($col)->setWidth(10);
        }
        $sheet->getColumnDimension('I')->setWidth(12);
        $sheet->getColumnDimension('Q')->setWidth(12);
        $sheet->getColumnDimension('T')->setWidth(12);
    }

    private function writeLetterheadV(Worksheet $sheet, int $row): void
    {
        $r = $row;
        $this->merge($sheet, "A{$r}:D" . ($r + 3));
        $this->drawLogo($sheet, "A{$r}");

        $this->merge($sheet, "E{$r}:Y{$r}");
        $this->set($sheet, "E{$r}", "Qashqadaryo viloyat  hududiy Qurilish va uy-joy kommunal xo'jaligi nazorati inspeksiyasi");
        $this->bold($sheet, "E{$r}:Y{$r}", 11);
        $this->centerWrap($sheet, "E{$r}:Y{$r}");

        $this->merge($sheet, "Z{$r}:AA" . ($r + 3));
        $this->set($sheet, "Z{$r}", "Hujjat raqami                                     Ф-03/01");
        $this->centerWrap($sheet, "Z{$r}:AA" . ($r + 3));

        $this->merge($sheet, "E" . ($r + 1) . ":Y" . ($r + 1));
        $this->set($sheet, "E" . ($r + 1), "Qurilish sinov laboratoriyasi va bino-inshoatlarning texnik holatini o'rganish bo'limi");
        $this->centerWrap($sheet, "E" . ($r + 1) . ":Y" . ($r + 1));

        $this->merge($sheet, "E" . ($r + 2) . ":Y" . ($r + 3));
        $this->set($sheet, "E" . ($r + 2), "(O'zbekiston Respublikasi Vazirlar Mahkamasining 2025-yil 22-sentyabrdagi 602-sonli qarorining 2-ilovasi.)\nManzil: Qarshi shahar, Jayhun ko'chasi №78-а uy. Indeks: 180100. Tel. (075) 230-03-42");
        $this->centerWrap($sheet, "E" . ($r + 2) . ":Y" . ($r + 3));
        $sheet->getRowDimension($r + 2)->setRowHeight(20);
        $sheet->getRowDimension($r + 3)->setRowHeight(20);

        if ($row === 1) {
            $this->merge($sheet, 'X5:Z5');
            $this->set($sheet, 'X5', '"Tasdiqlayman" ');
            $this->bold($sheet, 'X5:Z5');
            $this->centerWrap($sheet, 'X5:Z5');

            $this->merge($sheet, 'W6:AA6');
            $this->set($sheet, 'W6', "Qashqadaryo viloyati hududiy Qurilish va uy-joy kommunal xo'jaligi");
            $this->merge($sheet, 'W7:AA7');
            $this->set($sheet, 'W7', "nazorati inspeksiyasi Qurilish sinov laboratoriyasi va Bino-inshootlarning");
            $this->merge($sheet, 'W8:AA8');
            $this->set($sheet, 'W8', " texnik holatini o'rganish  bo'limi boshlig'i:");
            $this->merge($sheet, 'W9:Y9');
            $this->set($sheet, 'W9', '___________________');
            $this->merge($sheet, 'W10:AA10');
            $this->set($sheet, 'W10', '"____"_____________ 2025-yil ');
            $this->centerWrap($sheet, 'W6:AA10');

            $this->set($sheet, 'L11', ' 00123-2025/Бк22690-sonli sinov bayonnomasi');
            $this->bold($sheet, 'L11');
        }
    }

    private function writeInfoTableV(Worksheet $sheet): void
    {
        $rows = [
            // requestdan keladi (lab_gradirovka_main_infos.object_id orqali):
            13 => ['BUYURTMACHINING  NOMI', $this->field('customer_name', '')],
            14 => ["BUYURTMACHI BILAN BOG'LANISH MA'LUMOTLARI", null],
            // requestdan keladi (lab_gradirovka_main_infos.object_id orqali):
            15 => ["OB'EKTNING NOMI", $this->field('object_name', "Qarshi shahridagi ko'p qavatli turar-joy binosi qurilishi")],
            // requestdan keladi (lab_gradirovka_main_infos.lab_contract_id -> LabClaim.reestr_num orqali):
            16 => ['MUROJAAT RAQAMI', $this->field('application_number', '')],
            // requestdan keladi (lab_gradirovka_main_infos.work_type_id orqali):
            17 => ["SINOV OB'EKTINI NOMLANISHI", $this->field('work_type_name', 'Quyma temir beton kostruksiyalar')],
            18 => ['SINOVNING MAQSADI', "Quyma temir beton konstruksiyalarning amaldagi beton mustahkamlik sinfini aniqlash. "],
            19 => ["SINOV USULLARI BO'YICHA NORMATIV HUJJAT", "ГОСТ 22690-2015. Бетоны. Определение прочности механическими методами неразрушающего контроля п. 7,2 метод упругого отскока.\nГОСТ 18105-2018 Бетоны. Правила контроля и оценки прочности."],
            20 => ["SINOV OB'YEKTIGA TALABLARNI BELGILOVCHI NORMATIV HUJJAT", "ГОСТ 26633-2015. Бетоны тяжелые и мелкозернистые. Технические условия.\nPROJECT-TODAY x/k tomonidan ishlab chiqilgan loyiha hujjati (Albom II, KP bo'limi)"],
            // requestdan keladi:
            21 => ["SINOV O'TKAZILADIGAN JOY", $this->field('test_place', "Qurilish ob'yekti. ")],
            22 => ["GRADUIROVKA BO'GLIQLIGI BO'YICHA YO'RIQNOMA", 'Приложение - Е ГОСТ 22690-2015'],
            23 => ['BETON MUSTAHKAMLIGINI BAHOLASH USULI', 'ГОСТ 18105-2018 Бетоны. Правила контроля и оценки прочности. п. 8.4.4 Схема В'],
            24 => ["SINOV OB'YEKTINING HOLATI", "Sinov ob'yektining holati va miqdori normativ hujjat talablariga muvofiq keladi"],
            // requestdan keladi:
            25 => ["SINOV USULLARIDAN CHETLANISHLAR VA OG'ISHLAR", $this->field('deviations', "Sinov usullaridan chetlanishlar va og'ishlar mavjud emas")],
            26 => ['YORDAMCHI PUDRAT LABORATORIYALARI TOMONIDAN BAJARILGAN SINOV ISHLARI', "Qo'shimcha sinov ishlari bajarilmadi"],
            // requestdan keladi (temperature + humidity):
            27 => ['HARORAT VA BOSHQA ATROF-MUHIT SHAROITLARI', $this->field('temperature', '') . ' ᵒC, namlik ' . $this->field('humidity', '') . '%'],
        ];

        foreach ($rows as $r => [$label, $value]) {
            $this->merge($sheet, "A{$r}:J{$r}");
            $this->set($sheet, "A{$r}", $label);
            $this->bold($sheet, "A{$r}:J{$r}");

            if ($r === 14) {
                $this->merge($sheet, 'K14:O14');
                $this->set($sheet, 'K14', 'INN: ' . $this->field('customer_inn', '305123456'));
                $this->merge($sheet, 'P14:S14');
                $this->set($sheet, 'P14', 'Tel: ' . $this->field('customer_phone', '+998 90 123-45-67'));
                $this->merge($sheet, 'T14:AA14');
            } else {
                $this->merge($sheet, "K{$r}:AA{$r}");
                $this->set($sheet, "K{$r}", $value);
            }
        }
        $sheet->getRowDimension(19)->setRowHeight(30);
        $sheet->getRowDimension(20)->setRowHeight(30);

        $this->merge($sheet, 'A28:J31');
        $this->set($sheet, 'A28', "QO'LLANILGAN SINOV USKUNALARI   VA O'LCHOV VOSITALARI (Sertifikati raqami, amal qilish muddati)");
        $this->bold($sheet, 'A28:J31');

        // requestdan keladi: equipment[] -> K28..K31 (4 tagacha)
        $equipmentRows = [28, 29, 30, 31];
        foreach ($equipmentRows as $i => $r) {
            $item = $this->equipmentList()[$i] ?? null;
            $this->merge($sheet, "K{$r}:AA{$r}");
            if ($item) {
                $this->set($sheet, "K{$r}", $this->equipmentLine($item));
            }
        }

        $this->box($sheet, 'A13:AA31');
        $this->centerWrap($sheet, 'A13:AA31', Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A13:AA31')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    }

    private function writeFootnotesV(Worksheet $sheet, int $row): void
    {
        $lines = $row === 33
            ? [
                $row => '«03»noyabr 2025 yildagi 00123-2025-sonli sinov bayonnomasining 1-beti.',
                $row + 1 => "Sinov natijalari faqat buyurtmachi tomonidan taqdim etilgan namunalarga va sinovdan o'tkazilgan ob'ektlarga nisbatan qo'llaniladi.",
                $row + 2 => "Sinov bayyonnomasini laboratoriya bo'limi ruxsatisiz to'liq yoki qisman o'zgartirib tarqatish taqiqlanadi.",
            ]
            : [
                $row => '«03»noyabr 2025 yildagi 00123-2025-sonli sinov bayonnomasining 2-beti.',
                $row + 1 => "Sinov natijalari faqat buyurtmachi tomonidan taqdim etilgan namunalarga va sinovdan o'tkazilgan ob'ektlarga nisbatan qo'llaniladi.",
                $row + 2 => "Sinov bayyonnomasini laboratoriya bo'limi ruxsatisiz to'liq yoki qisman o'zgartirib tarqatish taqiqlanadi.",
            ];

        foreach ($lines as $r => $text) {
            $this->merge($sheet, "A{$r}:AA{$r}");
            $this->set($sheet, "A{$r}", $text);
            $sheet->getStyle("A{$r}:AA{$r}")->getFont()->setSize(8)->setItalic(true);
        }
    }

    private function writeResultsHeaderV(Worksheet $sheet): void
    {
        $headers = [
            'A41:D41' => "Konstruksiya (guruhi) nomlanishi (joylashuv o'rni)",
            'E41:F41' => "Uchastka raqami",
            'G41:H41' => "Betonning  yoshi, sutka",
            'I41:M41' => "Uskuna (molotok Schmidt) o'rtacha ko'rsatkichi,\n Н",
            'N41:P41' => "R=a*H+b\n Mpa",
            'Q41' => "Betonning o'rtacha mustahkamligi (Rm), Mpa",
            'R41:S41' => "ГОСТ 18105-2018 табл. А.1. прил. А. Koeffisiyent Kt",
            'T41:V41' => "Betonning amaldagi mustahkamlik sinfi , Вф = Rm / Kt",
            'W41' => "Betonning amaldagi o'rtacha mustahkamlik sinfi",
            'X41:Y41' => "Betonnning loyihaviy mustahkamlik sinfi",
            'Z41' => "Kengaytirilgan noaniqlik (P=0,95 k=2,0).",
            'AA41' => "Вф≥Внорм",
        ];
        foreach ($headers as $range => $text) {
            $coord = explode(':', $range)[0];
            if (str_contains($range, ':')) {
                $this->merge($sheet, $range);
            }
            $this->set($sheet, $coord, $text);
        }
        $this->bold($sheet, 'A41:AA41');
        $this->centerWrap($sheet, 'A41:AA41');
        $sheet->getRowDimension(41)->setRowHeight(45);
    }

    private function writeResultsBodyV(Worksheet $sheet): void
    {
        $items = $this->items();

        foreach ($this->groups() as $gi => $group) {
            $top = $group['top'];
            $bottom = $top + 5;
            $item = $items[$gi] ?? [];

            // items[].construction_name -> A42/A48/A54/A60
            $this->merge($sheet, "A{$top}:D" . ($top + 2));
            $this->set($sheet, "A{$top}", $item['construction_name'] ?? '');

            // items[].location -> A45:D47 (bitta ramka, "Б x/a 8-o'q" / "3-qavat" alohida emas)
            $this->merge($sheet, "A" . ($top + 3) . ":D{$bottom}");
            $this->set($sheet, "A" . ($top + 3), $item['location'] ?? '');

            // items[].concrete_age -> G42:H47
            $this->merge($sheet, "G{$top}:H{$bottom}");
            $this->set($sheet, "G{$top}", $item['concrete_age'] ?? null);

            // items[].measurements[] -> E (place), I (h), N (mark)
            $measurements = $item['measurements'] ?? [];
            foreach (array_slice($measurements, 0, 6) as $mi => $m) {
                $row = $top + $mi;
                $this->merge($sheet, "E{$row}:F{$row}");
                $this->set($sheet, "E{$row}", $m['place'] ?? ($mi + 1));
                $this->merge($sheet, "I{$row}:M{$row}");
                $this->set($sheet, "I{$row}", $m['h'] ?? null);
                $this->merge($sheet, "N{$row}:P{$row}");
                $this->set($sheet, "N{$row}", $m['mark'] ?? null);
            }

            // items[].rm -> Q42:Q47
            $this->merge($sheet, "Q{$top}:Q{$bottom}");
            $this->set($sheet, "Q{$top}", $item['rm'] ?? null);

            // items[].kt_kc -> R42:S47 (har guruh uchun alohida koeffitsiyent)
            $this->merge($sheet, "R{$top}:S{$bottom}");
            $this->set($sheet, "R{$top}", $item['kt_kc'] ?? null);

            // items[].vf -> T42:V47
            $this->merge($sheet, "T{$top}:V{$bottom}");
            $this->set($sheet, "T{$top}", $item['vf'] ?? null);

            // requestda mos maydon yo'q -> bo'sh qoladi
            $this->merge($sheet, "Z{$top}:Z{$bottom}");
        }

        $lastGroupRow = $this->lastGroupRow();

        // avg_vf -> W42:W<lastGroupRow> (butun jadval bo'yicha umumiy o'rtacha sinf)
        $this->merge($sheet, "W42:W{$lastGroupRow}");
        $this->set($sheet, 'W42', $this->avgVf());

        // design_class -> X42:Y<lastGroupRow> (butun jadval uchun bitta umumiy katak)
        $this->merge($sheet, "X42:Y{$lastGroupRow}");
        $this->set($sheet, 'X42', $this->designClass());

        // items[0].meets_norm -> AA42:AA<lastGroupRow> (original faylda bitta umumiy katak)
        $this->merge($sheet, "AA42:AA{$lastGroupRow}");
        $this->set($sheet, 'AA42', $this->meetsNormLabel($items[0]['meets_norm'] ?? null));

        $this->centerWrap($sheet, "A42:AA{$lastGroupRow}");
        $this->box($sheet, "A41:AA{$lastGroupRow}");
        $this->clearGroupInnerBorders($sheet);
    }

    private function clearGroupInnerBorders(Worksheet $sheet): void
    {
        foreach ($this->groups() as $group) {
            $nameLastRow = $group['top'] + 2;
            $axisRow = $group['top'] + 3;
            $floorRow = $group['top'] + 4;
            $lastRow = $group['top'] + 5;

            $sheet->getStyle("A{$nameLastRow}:D{$nameLastRow}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_NONE);
            $sheet->getStyle("A{$axisRow}:D{$axisRow}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_NONE);
            $sheet->getStyle("A{$axisRow}:D{$axisRow}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_NONE);
            $sheet->getStyle("A{$floorRow}:D{$floorRow}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_NONE);
            $sheet->getStyle("A{$floorRow}:D{$floorRow}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_NONE);
            $sheet->getStyle("A{$lastRow}:D{$lastRow}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_NONE);

            $sheet->getStyle("A{$group['top']}:D{$lastRow}")->getBorders()->applyFromArray([
                'outline' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF000000']],
            ]);
        }
    }

    // ------------------------------------------------------------------
    // Scheme Г
    // ------------------------------------------------------------------

    private function buildSchemeG(Worksheet $sheet): void
    {
        $lastGroupRow = $this->lastGroupRow();
        $footerStart = $lastGroupRow + 2;
        $footnotesStart = $lastGroupRow + 10;
        $lastRow = $lastGroupRow + 12;

        $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT);
        $this->baseFont($sheet, "A1:W{$lastRow}");

        $this->writeLetterheadG($sheet, 1);
        $this->writeInfoTableG($sheet);
        $this->writeFootnotesG($sheet, 33);
        $this->writeLetterheadG($sheet, 36);

        $this->merge($sheet, 'A40:W40');
        $this->set($sheet, 'A40', 'SINOV NATIJALARI');
        $this->bold($sheet, 'A40:W40', 11);
        $this->centerWrap($sheet, 'A40:W40');

        $this->writeResultsHeaderG($sheet);
        $this->writeResultsBodyG($sheet);

        $this->writeFooter($sheet, 'G', $footerStart);
        $this->writeFootnotesG($sheet, $footnotesStart);

        $sheet->getColumnDimension('A')->setWidth(16);
        foreach (['E', 'G', 'N'] as $col) {
            $sheet->getColumnDimension($col)->setWidth(10);
        }
        $sheet->getColumnDimension('I')->setWidth(12);
        $sheet->getColumnDimension('O')->setWidth(11);
        $sheet->getColumnDimension('P')->setWidth(11);
        $sheet->getColumnDimension('Q')->setWidth(14);
    }

    private function writeLetterheadG(Worksheet $sheet, int $row): void
    {
        $r = $row;
        $this->merge($sheet, "A{$r}:D" . ($r + 3));
        $this->drawLogo($sheet, "A{$r}");

        $this->merge($sheet, "E{$r}:U{$r}");
        $this->set($sheet, "E{$r}", "Qashqadaryo viloyat  hududiy Qurilish va uy-joy kommunal xo'jaligi nazorati inspeksiyasi");
        $this->bold($sheet, "E{$r}:U{$r}", 11);
        $this->centerWrap($sheet, "E{$r}:U{$r}");

        $this->merge($sheet, "V{$r}:W" . ($r + 3));
        $this->set($sheet, "V{$r}", "Hujjat raqami                                     Ф-03/01");
        $this->centerWrap($sheet, "V{$r}:W" . ($r + 3));

        $this->merge($sheet, "E" . ($r + 1) . ":U" . ($r + 1));
        $this->set($sheet, "E" . ($r + 1), "Qurilish sinov laboratoriyasi va bino-inshoatlarning texnik holatini o'rganish bo'limi");
        $this->centerWrap($sheet, "E" . ($r + 1) . ":U" . ($r + 1));

        $this->merge($sheet, "E" . ($r + 2) . ":U" . ($r + 3));
        $this->set($sheet, "E" . ($r + 2), "(O'zbekiston Respublikasi Vazirlar Mahkamasining 2025-yil 22-sentyabrdagi 602-sonli qarorining 2-ilovasi.)\nManzil: Qarshi shahar, Jayhun ko'chasi №78-а uy. Indeks: 180100. Tel. (075) 230-03-42");
        $this->centerWrap($sheet, "E" . ($r + 2) . ":U" . ($r + 3));
        $sheet->getRowDimension($r + 2)->setRowHeight(20);
        $sheet->getRowDimension($r + 3)->setRowHeight(20);

        if ($row === 1) {
            $this->merge($sheet, 'X5:Y5');
            $this->set($sheet, 'X5', '"Tasdiqlayman" ');
            $this->bold($sheet, 'X5:Y5');
            $this->centerWrap($sheet, 'X5:Y5');

            $this->merge($sheet, 'R6:W6');
            $this->set($sheet, 'R6', "Qashqadaryo viloyati hududiy Qurilish va uy-joy kommunal xo'jaligi");
            $this->merge($sheet, 'R7:W7');
            $this->set($sheet, 'R7', "nazorati inspeksiyasi Qurilish sinov laboratoriyasi va Bino-inshootlarning");
            $this->merge($sheet, 'R8:W8');
            $this->set($sheet, 'R8', " texnik holatini o'rganish  bo'limi boshlig'i:");
            $this->set($sheet, 'S9', '___________________');
            $this->merge($sheet, 'R10:W10');
            $this->set($sheet, 'R10', '"____"_____________ 2025-yil ');
            $this->centerWrap($sheet, 'R6:W10');

            $this->set($sheet, 'L11', ' 00124-2025/Бк22690-sonli sinov bayonnomasi');
            $this->bold($sheet, 'L11');
        }
    }

    private function writeInfoTableG(Worksheet $sheet): void
    {
        $rows = [
            // requestdan keladi (lab_gradirovka_main_infos.object_id orqali):
            13 => ['BUYURTMACHINING  NOMI', $this->field('customer_name', '«PROJECT-TODAY» Mas\'uliyati Cheklangan Jamiyati')],
            14 => ["BUYURTMACHI BILAN BOG'LANISH MA'LUMOTLARI", null],
            // requestdan keladi (lab_gradirovka_main_infos.object_id orqali):
            15 => ["OB'EKTNING NOMI", $this->field('object_name', "Qarshi shahridagi ko'p qavatli turar-joy binosi qurilishi")],
            // requestdan keladi (lab_gradirovka_main_infos.lab_contract_id -> LabClaim.reestr_num orqali):
            16 => ['MUROJAAT RAQAMI', $this->field('application_number', '')],
            // requestdan keladi (lab_gradirovka_main_infos.work_type_id orqali):
            17 => ["SINOV OB'EKTINI NOMLANISHI", $this->field('work_type_name', 'Quyma temir beton kostruksiyalar')],
            18 => ['SINOVNING MAQSADI', "Quyma temir beton konstruksiyalarning amaldagi beton mustahkamlik sinfini aniqlash. "],
            19 => ["SINOV USULLARI BO'YICHA NORMATIV HUJJAT", "ГОСТ 22690-2015. Бетоны. Определение прочности механическими методами неразрушающего контроля п. 7,2 метод упругого отскока.\nГОСТ 18105-2018 Бетоны. Правила контроля и оценки прочности."],
            20 => ["SINOV OB'YEKTIGA TALABLARNI BELGILOVCHI NORMATIV HUJJAT", "ГОСТ 26633-2015. Бетоны тяжелые и мелкозернистые. Технические условия.\nPROJECT-TODAY x/k tomonidan ishlab chiqilgan loyiha hujjati (Albom II, KP bo'limi)"],
            21 => ["SINOV O'TKAZILADIGAN JOY", $this->field('test_place', "Qurilish ob'yekti. ")],
            22 => ["GRADUIROVKA BO'GLIQLIGI BO'YICHA YO'RIQNOMA", 'Приложение - Е ГОСТ 22690-2015'],
            23 => ['BETON MUSTAHKAMLIGINI BAHOLASH USULI', 'ГОСТ 18105-2018 Бетоны. Правила контроля и оценки прочности. п. 8.4.4 Схема Г'],
            24 => ["SINOV OB'YEKTINING HOLATI", "Sinov ob'yektining holati va miqdori normativ hujjat talablariga muvofiq keladi"],
            25 => ["SINOV USULLARIDAN CHETLANISHLAR VA OG'ISHLAR", $this->field('deviations', "Sinov usullaridan chetlanishlar va og'ishlar mavjud emas")],
            26 => ['YORDAMCHI PUDRAT LABORATORIYALARI TOMONIDAN BAJARILGAN SINOV ISHLARI', "Qo'shimcha sinov ishlari bajarilmadi"],
            27 => ['HARORAT VA BOSHQA ATROF-MUHIT SHAROITLARI', $this->field('temperature', '+24') . ' ᵒC, namlik ' . $this->field('humidity', '45') . '%'],
        ];

        foreach ($rows as $r => [$label, $value]) {
            $this->merge($sheet, "A{$r}:J{$r}");
            $this->set($sheet, "A{$r}", $label);
            $this->bold($sheet, "A{$r}:J{$r}");

            if ($r === 14) {
                $this->merge($sheet, 'K14:M14');
                $this->set($sheet, 'K14', 'INN: ' . $this->field('customer_inn', '305123456'));
                $this->merge($sheet, 'N14:R14');
                $this->set($sheet, 'N14', 'Tel: ' . $this->field('customer_phone', '+998 90 123-45-67'));
                $this->merge($sheet, 'S14:W14');
            } else {
                $this->merge($sheet, "K{$r}:W{$r}");
                $this->set($sheet, "K{$r}", $value);
            }
        }
        $sheet->getRowDimension(19)->setRowHeight(40);
        $sheet->getRowDimension(20)->setRowHeight(40);

        $this->merge($sheet, 'A28:J31');
        $this->set($sheet, 'A28', "QO'LLANILGAN SINOV USKUNALARI   VA O'LCHOV VOSITALARI (Sertifikati raqami, amal qilish muddati)");
        $this->bold($sheet, 'A28:J31');

        $equipmentRows = [28, 29, 30, 31];
        foreach ($equipmentRows as $i => $r) {
            $item = $this->equipmentList()[$i] ?? null;
            $this->merge($sheet, "K{$r}:W{$r}");
            if ($item) {
                $this->set($sheet, "K{$r}", $this->equipmentLine($item));
            }
        }

        $this->box($sheet, 'A13:W31');
        $this->centerWrap($sheet, 'A13:W31', Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A13:W31')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    }

    private function writeFootnotesG(Worksheet $sheet, int $row): void
    {
        $lines = $row === 33
            ? [
                $row => '«03»noyabr 2025 yildagi 00124-2025-sonli sinov bayonnomasining 1-beti.',
                $row + 1 => "Sinov natijalari faqat buyurtmachi tomonidan taqdim etilgan namunalarga va sinovdan o'tkazilgan ob'ektlarga nisbatan qo'llaniladi.",
                $row + 2 => "Sinov bayyonnomasini laboratoriya bo'limi ruxsatisiz to'liq yoki qisman o'zgartirib tarqatish taqiqlanadi.",
            ]
            : [
                $row => '«03»noyabr 2025 yildagi 00124-2025-sonli sinov bayonnomasining 2-beti.',
                $row + 1 => "Sinov natijalari faqat buyurtmachi tomonidan taqdim etilgan namunalarga va sinovdan o'tkazilgan ob'ektlarga nisbatan qo'llaniladi.",
                $row + 2 => "Sinov bayyonnomasini laboratoriya bo'limi ruxsatisiz to'liq yoki qisman o'zgartirib tarqatish taqiqlanadi.",
            ];

        foreach ($lines as $r => $text) {
            $this->merge($sheet, "A{$r}:W{$r}");
            $this->set($sheet, "A{$r}", $text);
            $sheet->getStyle("A{$r}:W{$r}")->getFont()->setSize(8)->setItalic(true);
        }
    }

    private function writeResultsHeaderG(Worksheet $sheet): void
    {
        $headers = [
            'A41:D41' => "Konstruksiya (guruhi) nomlanishi (joylashuv o'rni)",
            'E41:F41' => "Uchastka raqami",
            'G41:H41' => "Betonning  yoshi, sutka",
            'I41:M41' => "Uskuna (molotok Schmidt) o'rtacha ko'rsatkichi,\n Н",
            'N41' => "Koeffisiyent Kc",
            'O41' => "Betonning  mustahkamligi Rф=R*Kc, MPa",
            'P41' => "Betonning o'rtacha mustahkamligi (Rm), Mpa",
            'Q41:R41' => "Betonning amaldagi mustahkamlik sinfi ГОСТ 18105-2018 п.8.4.4.                    Вф = 0,8Rm",
            'S41' => "Betonning amaldagi o'rtacha mustahkamlik sinfi",
            'T41:U41' => "Betonnning loyihaviy mustahkamlik sinfi",
            'V41' => "Kengaytirilgan noaniqlik (P=0,95 k=2,0).",
            'W41' => "Вф≥Внорм",
        ];
        foreach ($headers as $range => $text) {
            $coord = explode(':', $range)[0];
            if (str_contains($range, ':')) {
                $this->merge($sheet, $range);
            }
            $this->set($sheet, $coord, $text);
        }
        $this->bold($sheet, 'A41:W41');
        $this->centerWrap($sheet, 'A41:W41');
        $sheet->getRowDimension(41)->setRowHeight(45);
    }

    private function writeResultsBodyG(Worksheet $sheet): void
    {
        $items = $this->items();

        foreach ($this->groups() as $gi => $group) {
            $top = $group['top'];
            $bottom = $top + 5;
            $item = $items[$gi] ?? [];

            // items[].construction_name -> A42/A48/A54/A60
            $this->merge($sheet, "A{$top}:D" . ($top + 2));
            $this->set($sheet, "A{$top}", $item['construction_name'] ?? '');

            // items[].location -> A45:D47 (bitta ramka)
            $this->merge($sheet, "A" . ($top + 3) . ":D{$bottom}");
            $this->set($sheet, "A" . ($top + 3), $item['location'] ?? '');

            // items[].concrete_age -> G42:H47
            $this->merge($sheet, "G{$top}:H{$bottom}");
            $this->set($sheet, "G{$top}", $item['concrete_age'] ?? null);

            // items[].kt_kc -> N42:N47 (har guruh uchun alohida koeffitsiyent)
            $this->merge($sheet, "N{$top}:N{$bottom}");
            $this->set($sheet, "N{$top}", $item['kt_kc'] ?? null);

            // items[].measurements[] -> E (place), I (h), O (mark)
            $measurements = $item['measurements'] ?? [];
            foreach (array_slice($measurements, 0, 6) as $mi => $m) {
                $row = $top + $mi;
                $this->merge($sheet, "E{$row}:F{$row}");
                $this->set($sheet, "E{$row}", $m['place'] ?? ($mi + 1));
                $this->merge($sheet, "I{$row}:M{$row}");
                $this->set($sheet, "I{$row}", $m['h'] ?? null);
                $this->set($sheet, "O{$row}", $m['mark'] ?? null);
            }

            // items[].rm -> P42:P47
            $this->merge($sheet, "P{$top}:P{$bottom}");
            $this->set($sheet, "P{$top}", $item['rm'] ?? null);

            // items[].vf -> Q42:R47
            $this->merge($sheet, "Q{$top}:R{$bottom}");
            $this->set($sheet, "Q{$top}", $item['vf'] ?? null);

            // requestda mos maydon yo'q -> bo'sh qoladi
            $this->merge($sheet, "V{$top}:V{$bottom}");
            $this->merge($sheet, "W{$top}:W{$bottom}");
        }

        $lastGroupRow = $this->lastGroupRow();

        // avg_vf -> S42:S<lastGroupRow> (butun jadval bo'yicha umumiy o'rtacha sinf)
        $this->merge($sheet, "S42:S{$lastGroupRow}");
        $this->set($sheet, 'S42', $this->avgVf());

        // design_class -> T42:U<lastGroupRow> (butun jadval uchun bitta umumiy katak)
        $this->merge($sheet, "T42:U{$lastGroupRow}");
        $this->set($sheet, 'T42', $this->designClass());

        $this->centerWrap($sheet, "A42:W{$lastGroupRow}");
        $this->box($sheet, "A41:W{$lastGroupRow}");
        $this->clearGroupInnerBorders($sheet);
    }

    // ------------------------------------------------------------------
    // Shared footer (identical layout in both schemes)
    // ------------------------------------------------------------------

    private function writeFooter(Worksheet $sheet, string $scheme, int $startRow): void
    {
        $endCol = $scheme === 'G' ? 'W' : 'AA';
        $r = $startRow;

        $this->set($sheet, "A{$r}", 'Sinov boshlangan sana: 03.11.2025- yil');
        $this->merge($sheet, "A{$r}:I{$r}");
        $this->set($sheet, "T{$r}", 'Sinov tugagan sana: 03.11.2025- yil');
        $this->merge($sheet, "N{$r}:S{$r}");

        $this->set($sheet, 'A' . ($r + 1), 'Bajaruvchi:');
        $this->set($sheet, 'A' . ($r + 2), 'Sinov laboratoriyasi mutaxassisi:');
        $this->merge($sheet, 'A' . ($r + 2) . ':G' . ($r + 2));
        $this->set($sheet, 'S' . ($r + 2), 'Aliyev B.');
        $this->merge($sheet, 'S' . ($r + 2) . ':V' . ($r + 2));

        $this->set($sheet, 'A' . ($r + 3), 'Sinov bayonnomasini rasmiylashtirdi ');
        $this->set($sheet, 'A' . ($r + 4), 'Sinov laboratoriyasi Bosh mutaxassisi:');
        $this->set($sheet, 'S' . ($r + 4), 'Karimov A.');
        $this->merge($sheet, 'S' . ($r + 4) . ':V' . ($r + 4));

        $this->set($sheet, 'A' . ($r + 5), "Sinov ob'ektiga mos normativ hujjatlar, sinov uskunalari, o'lchov vositalari normativ hujjat talabi bo'yicha tanlangan va o'lchovlar birligi ta'minlangan.");
        $this->set($sheet, 'A' . ($r + 6), "Sinov laboratoriyasida metrologiya bo'yicha javobgar shaxs:");
        $this->set($sheet, 'S' . ($r + 6), 'Rustamov D.');
        $this->merge($sheet, 'S' . ($r + 6) . ':V' . ($r + 6));

        // note -> A<r+7> "Sinov bayonnomasining yakuni"
        $items = $this->items();
        $conclusion = $this->field('note') ?: $this->meetsNormLabel($items[0]['meets_norm'] ?? null);
        $noteRow = $r + 7;
        $this->merge($sheet, "A{$noteRow}:{$endCol}{$noteRow}");
        $this->set($sheet, "A{$noteRow}", 'Sinov bayonnomasining yakuni: ' . $conclusion);
        $this->bold($sheet, "A{$noteRow}:{$endCol}{$noteRow}");

        $sheet->getStyle("A{$r}:{$endCol}{$noteRow}")->getFont()->setSize(9);
    }
}
