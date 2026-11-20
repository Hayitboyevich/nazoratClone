<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ClaimTaskExcel implements WithHeadings, FromQuery, WithMapping, WithChunkReading
{
    public array $filters;

    public function __construct(array $filters)
    {
        $this->filters = $filters;
    }

    public function query()
    {
        $dateFrom = $this->filters['start_date'] ?? null;
        $dateTo = $this->filters['end_date'] ?? null;

        return DB::table('claim_excel')
            ->select(
                'id',
                'ariza_raqami',
                'obyekt_nomi',
                'obyekt_raqami',
                'region_name',
                'district_name',
                'cadaster',
                'customer',
                'customer_inn',
                'jami_honadon',
                'jami_block',
                'noturar',
                'turar',
                'yakka',
                'count_apartments',
                'priyomka_jami_block',
                'priyomka_noturar',
                'priyomka_turar',
                'priyomka_yakka',
                'umumiy_maydon',
                'umumiy_noturar',
                'umumiy_turar',
                'umumiy_yakka',
                'foydalanish_maydon',
                'foydalanish_noturar',
                'foydalanish_turar',
                'foydalanish_yakka',
                'yashash_maydon',
                'yashash_noturar',
                'yashash_turar',
                'yashash_yakka',
                'qurilish_osti_maydoni',
                'qurilish_osti_noturar',
                'qurilish_osti_turar',
                'qurilish_osti_yakka',
                'inskepsiya_kelgan_vaqt',
                'tashkilotga_yuborilgan_sana',
                'oxirgi_tashkilot_sanasi',
                'inspektor_hulosa_vaqti',
                'inspektor',
                'yuridik_jismoniy',
                'manzil',
                'obyekt_turi',
                'toifasi',
                'obyekt_created_at',
                'inspektorga_yuborilgan_vaqt',
                'direktorga_yuborilgan_vaqt',
                'tugagan_sana',
                'status',
                'end_role_name',
                'reject_reason',
                'dastur_nomi',
                'quyosh_panel',
                'konstruktiv_checklist',
                'seysmik_checklist'
            )
            ->when(isset($this->filters['region_id']), function ($query) {
                $query->where('region_id', $this->filters['region_id']);
            })
            ->when($dateFrom && $dateTo, function ($query) use ($dateFrom, $dateTo) {
                $query->whereBetween('created_at', [
                    Carbon::parse($dateFrom)->startOfDay(),
                    Carbon::parse($dateTo)->endOfDay()
                ]);
            })
            ->orderBy('id');
    }


    public function map($row): array

    {
        return [
            $row->id,
            $row->ariza_raqami,
            $row->obyekt_nomi,
            $row->obyekt_raqami,
            $row->region_name,
            $row->district_name,
            $row->cadaster,
            $row->customer,
            $row->customer_inn,
            $row->jami_honadon,
            $row->jami_block,
            $row->noturar,
            $row->turar,
            $row->yakka,
            $row->count_apartments,
            $row->priyomka_jami_block,
            $row->priyomka_noturar,
            $row->priyomka_turar,
            $row->priyomka_yakka,
            $row->umumiy_maydon,
            $row->umumiy_noturar,
            $row->umumiy_turar,
            $row->umumiy_yakka,
            $row->foydalanish_maydon,
            $row->foydalanish_noturar,
            $row->foydalanish_turar,
            $row->foydalanish_yakka,
            $row->yashash_maydon,
            $row->yashash_noturar,
            $row->yashash_turar,
            $row->yashash_yakka,
            $row->qurilish_osti_maydoni,
            $row->qurilish_osti_noturar,
            $row->qurilish_osti_turar,
            $row->qurilish_osti_yakka,
            $row->inskepsiya_kelgan_vaqt,
            $row->tashkilotga_yuborilgan_sana,
            $row->oxirgi_tashkilot_sanasi,
            $row->inspektor_hulosa_vaqti,
            $row->inspektor,
            $row->yuridik_jismoniy,
            $row->manzil,
            $row->obyekt_turi,
            $row->toifasi,
            $row->obyekt_created_at,
            $row->inspektorga_yuborilgan_vaqt,
            $row->direktorga_yuborilgan_vaqt,
            $row->tugagan_sana,
            $row->status,
            $row->end_role_name,
            $row->reject_reason,
            $row->dastur_nomi,
            $row->quyosh_panel,
            $row->konstruktiv_checklist,
            $row->seysmik_checklist,
        ];
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function headings(): array
    {
        return [
            "T/R",
            "Ariza raqami",
            "Obyekt nomi",
            "Obyekt ariza raqami",
            "Viloyat",
            "Tuman",
            "Kadastr raqami",
            "Buyurtmachi",
            "Buyurtmachi INN",
            "Jami xonadon soni",
            "Bloklar soni",
            "Noturar",
            "Turar",
            "Yakka tartibdagi",
            "Topshirilgan honadon soni",
            "Topshirilgan bloklar soni",
            "Noturar",
            "Turar",
            "Yakka tartibdagi",
            "Umumiy maydoni tashqi (m2)",
            "Noturar",
            "Turar",
            "Yakka tartibdagi",
            "Topshirilgan umumiy foydalanish maydoni (m2)",
            "Noturar",
            "Turar",
            "Yakka tartibdagi",
            "Yashash maydoni (m2)",
            "Noturar",
            "Turar",
            "Yakka tartibdagi",
            "Qurilish osti maydoni (m2)",
            "Noturar",
            "Turar",
            "Yakka tartibdagi",

            'Inspeksiyaga kelgan vaqt',
            'Tashkilotlarga yuborilgan vaqt',
            'Oxirgi tashkilot hulosa bergan vaqti',
            'Inspektor hulosa bergan vaqat',
            'Inspektor',
            'Jismoniy yoki Yuridik shaxs',
            'Qurilish obyekti manzili',
            'Obyekt Turi',
            'Obyekt murakkablik toifasi',
            'Obyekt yaratilgan sana',
            'Inspektorga yuborilgan sana',
            'Direktorga yuborilgan sana',
            'Yakunlangan sana',
            'Xolati',
            'Kim tomonidan yakunlandi',
            'Rad etilganligi sababi',
            'Dastur nomi',
            'Qurosh panellari o`rnatilganligi',
            'Qurilishi tugallangan obektlarni konstruktiv tizimi qurilish maydonining seysmik darajasi yuk ko‘taruvchi konstruksiyalar materiallarining mastahkamlik ko‘rsatkichlarini nazarda tutuvchi elektron pasportini kiritish.""	Qurilishi tugallangan obektlarni konstruktiv tizimi qurilish maydonining seysmik darajasi yuk ko‘taruvchi konstruksiyalar materiallarining mastahkamlik ko‘rsatkichlarini nazarda tutuvchi elektron pasportini kiritish.',
            'Инспектор томонидан сейсмика бўйича техник кўрик олган ёки олинмаган III-IV toifadagi obʼektlarda zilzilabardoshlikka doyr instrumental-texnik tekshiruvdan utkazilganligini tasdiklovchi xujjat va laboratoriya sinov xulosalarini kiritish.'
        ];
    }
}
