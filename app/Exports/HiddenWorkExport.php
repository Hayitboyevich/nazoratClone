<?php

namespace App\Exports;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class HiddenWorkExport implements
    FromQuery,
    WithMapping,
    WithHeadings,
    WithChunkReading
{
    protected array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }
    public function query(): Builder
    {
        return DB::table('hidden_works_acts as hid')

            ->leftJoin('articles as a', 'hid.object_id', '=', 'a.id')

            ->leftJoin('blocks as b', 'hid.block_id', '=', 'b.id')

            ->leftJoin('regions as r', 'a.region_id', '=', 'r.id')

            ->leftJoin('districts as d', 'a.district_id', '=', 'd.id')

            ->leftJoin('article_users as au', 'a.id', '=', 'au.article_id')

            ->leftJoin('users as u', 'au.user_id', '=', 'u.id')

            ->leftJoin('spheres as s', 'a.sphere_id', '=', 's.id')

            ->leftJoin(
                'difficulty_categories as dif',
                'a.difficulty_category_id',
                '=',
                'dif.id'
            )

            ->leftJoin(
                'funding_sources as fun',
                'a.funding_source_id',
                '=',
                'fun.id'
            )

            ->leftJoin(
                'object_statuses as os',
                'a.object_status_id',
                '=',
                'os.id'
            )

            ->leftJoin('users as us', 'hid.internal_id', '=', 'us.id')

            ->leftJoin('hidden_work_histories as hw', function ($join) {
                $join->on('hw.gu_id', '=', 'hid.id')
                    ->where('hw.type', 423);
            })

            ->leftJoin('users as hw_user', function ($join) {
                $join->on(
                    'hw_user.id',
                    '=',
                    DB::raw("(hw.content->>'user')::bigint")
                );
            })

            ->leftJoinSub(
                DB::table('blocks')
                    ->select([
                        'article_id',
                        DB::raw('COUNT(*) AS bloklar_soni'),
                    ])
                    ->groupBy('article_id'),
                'blocks_count',
                'blocks_count.article_id',
                '=',
                'a.id'
            )

            ->select([
                'hid.id as id',

                'a.name as object_name',

                DB::raw("
                    MAX(
                        CASE
                            WHEN au.role_id = 8
                            THEN u.organization_name
                        END
                    ) AS buyurtmachi
                "),

                'r.name_uz as viloyat',

                'd.name_uz as tuman',

                'a.location_building as manzil',

                DB::raw("
                    MAX(
                        CASE
                            WHEN au.role_id = 3
                            THEN CONCAT_WS(
                                ' ',
                                u.surname,
                                u.name,
                                u.middle_name
                            )
                        END
                    ) AS inspektor
                "),

                DB::raw("
                    COALESCE(
                        a.task_id::text,
                        a.manual_task_id::text
                    ) AS task_id
                "),

                'os.name as object_status',

                DB::raw("
                    COALESCE(
                        blocks_count.bloklar_soni,
                        0
                    ) AS bloklar_soni
                "),

                's.name_uz as soha',

                'dif.difficulty as murakkablik_toifasi',

                'fun.description as moliyalashtirish_manbai',

                'a.created_at as royxatdan_otgan_sana',

                'b.name as block_nomi',

                'hid.id as dalolatnoma_raqami',

                'hid.created_at as dalolatnoma_sanasi',

                DB::raw("
                    CASE
                        WHEN hid.status = 1 THEN 'Yangi'
                        WHEN hid.status = 3 THEN 'Tasdiqlangan'
                        WHEN hid.status = 5 THEN 'Tahrirlashda'
                        ELSE NULL
                    END AS dalolatnoma_status
                "),

                DB::raw("
                    MAX(
                        CASE
                            WHEN (hw.content->>'role')::integer = 6
                            THEN CONCAT_WS(
                                ' ',
                                hw_user.surname,
                                hw_user.name,
                                hw_user.middle_name
                            )
                        END
                    ) AS texnik
                "),

                DB::raw("
                    MAX(
                        CASE
                            WHEN (hw.content->>'role')::integer = 6
                            THEN hw.content->>'comment'
                        END
                    ) AS texnik_comment
                "),

                DB::raw("
                    MAX(
                        CASE
                            WHEN (hw.content->>'role')::integer = 7
                            THEN CONCAT_WS(
                                ' ',
                                hw_user.surname,
                                hw_user.name,
                                hw_user.middle_name
                            )
                        END
                    ) AS muallif
                "),

                DB::raw("
                    MAX(
                        CASE
                            WHEN (hw.content->>'role')::integer = 7
                            THEN hw.content->>'comment'
                        END
                    ) AS muallif_comment
                "),

                'hid.desc as dalolatnoma_rasmiylashtirilgan_ish_turi',

                'hid.materials as qollanilgan_materiallar',

                DB::raw("
                    CASE
                        WHEN hid.has_project_deviation IS TRUE THEN 'Bor'
                        WHEN hid.has_project_deviation IS FALSE THEN 'Yo‘q'
                        ELSE NULL
                    END AS loyihadan_chetga_chiqish
                "),

                'hid.start_date as ishlar_boshlanish_sanasi',

                'hid.end_date as tugash_sanasi',

                DB::raw("
                    CONCAT_WS(
                        ' ',
                        us.surname,
                        us.name,
                        us.middle_name
                    ) AS ichki_nazorat
                "),
            ])

            ->when(
                !empty($this->filters['region_id']),
                function ($query) {
                    $query->where(
                        'a.region_id',
                        $this->filters['region_id']
                    );
                }
            )

            ->when(
                !empty($this->filters['district_id']),
                function ($query) {
                    $query->where(
                        'a.district_id',
                        $this->filters['district_id']
                    );
                }
            )

            ->when(
                !empty($this->filters['object_id']),
                function ($query) {
                    $query->where(
                        'a.id',
                        $this->filters['object_id']
                    );
                }
            )

            ->when(
                !empty($this->filters['status']),
                function ($query) {
                    $query->where(
                        'hid.status',
                        $this->filters['status']
                    );
                }
            )

            ->when(
                !empty($this->filters['date_from']),
                function ($query) {
                    $query->whereDate(
                        'hid.created_at',
                        '>=',
                        $this->filters['date_from']
                    );
                }
            )

            ->when(
                !empty($this->filters['date_to']),
                function ($query) {
                    $query->whereDate(
                        'hid.created_at',
                        '<=',
                        $this->filters['date_to']
                    );
                }
            )

            ->groupBy(
                'hid.id',
                'a.id',
                'a.name',
                'r.name_uz',
                'd.name_uz',
                'a.location_building',
                'a.task_id',
                'a.manual_task_id',
                'os.name',
                'blocks_count.bloklar_soni',
                's.name_uz',
                'dif.difficulty',
                'fun.description',
                'a.created_at',
                'b.id',
                'b.name',
                'us.id',
                'us.surname',
                'us.name',
                'us.middle_name'
            )

            ->orderByDesc('hid.id');
    }

    public function headings(): array
    {
        return [
            'ID',
            'Obyekt nomi',
            'Buyurtmachi',
            'Viloyat',
            'Tuman',
            'Manzil',
            'Inspektor',
            'Task ID',
            'Obyekt holati',
            'Bloklar soni',
            'Soha',
            'Murakkablik toifasi',
            'Moliyalashtirish manbai',
            "Ro'yxatdan o'tgan sana",
            'Blok nomi',
            'Dalolatnoma raqami',
            'Dalolatnoma sanasi',
            'Dalolatnoma xolati',
            'Dalolatnomani rad qilgan ishtirokchi (texnodzor)',
            'Texnodzor izohi',
            'Dalolatnomani rad qilgan ishtirokchi (muallif)',
            'Muallif izohi',
            'Dalolatnoma rasmiylashtirilgan ish turi',
            "Ishlarni bajarishda qo'llanilgan materiallar",
            'Loyiha hujjatidan chetga chiqish',
            'Ishlar boshlanish sanasi',
            'Ishlar tugash sanasi',
            'Jurnalga ma\'lumot kiritgan ichki nazoratchi F.I.SH.',
        ];
    }

    public function map($row): array
    {
        return [
            $row->id,
            $row->object_name,
            $row->buyurtmachi,
            $row->viloyat,
            $row->tuman,
            $row->manzil,
            $row->inspektor,
            $row->task_id,
            $row->object_status,
            $row->bloklar_soni,
            $row->soha,
            $row->murakkablik_toifasi,
            $row->moliyalashtirish_manbai,
            $row->royxatdan_otgan_sana,
            $row->block_nomi,
            $row->dalolatnoma_raqami,
            $row->dalolatnoma_sanasi,
            $row->dalolatnoma_status,
            $row->texnik,
            $row->texnik_comment,
            $row->muallif,
            $row->muallif_comment,
            $row->dalolatnoma_rasmiylashtirilgan_ish_turi,
            $row->qollanilgan_materiallar,
            $row->loyihadan_chetga_chiqish,
            $row->ishlar_boshlanish_sanasi,
            $row->tugash_sanasi,
            $row->ichki_nazorat,
        ];
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}
