<?php

namespace App\Exports;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AuthorRegulationsExport implements
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
        return DB::table('author_regulations as auth')

            ->leftJoin('articles as a', 'auth.object_id', '=', 'a.id')

            ->leftJoin('blocks as b', 'auth.block_id', '=', 'b.id')

            ->leftJoin('regions as r', 'a.region_id', '=', 'r.id')

            ->leftJoin('districts as d', 'a.district_id', '=', 'd.id')

            ->leftJoinSub(
                DB::table('article_users as au')
                    ->join('users as u', 'au.user_id', '=', 'u.id')
                    ->whereIn('au.role_id', [3, 8])
                    ->select([
                        'au.article_id',

                        DB::raw("
                            MAX(
                                CASE
                                    WHEN au.role_id = 8
                                    THEN u.organization_name
                                END
                            ) AS buyurtmachi
                        "),

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
                    ])
                    ->groupBy('au.article_id'),
                'article_users_info',
                'article_users_info.article_id',
                '=',
                'a.id'
            )

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

            ->leftJoin('users as us', 'auth.author_id', '=', 'us.id')

            ->leftJoin('bases as base', 'auth.bases_id', '=', 'base.id')

            ->leftJoin('work_types as w', 'auth.work_type_id', '=', 'w.id')

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
                'auth.id as id',

                'a.name as object_name',

                'article_users_info.buyurtmachi',

                'r.name_uz as viloyat',

                'd.name_uz as tuman',

                'a.location_building as manzil',

                'article_users_info.inspektor',

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

                'base.name as shnq_bandi',

                'w.name as ish_turi',

                'auth.author_comment as berilgan_korsatma',

                'auth.deadline as bartaraf_etish_muddati',

                DB::raw("
                    CONCAT_WS(
                        ' ',
                        us.surname,
                        us.name,
                        us.middle_name
                    ) AS muallif_nazorat
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
                !empty($this->filters['author_id']),
                function ($query) {
                    $query->where(
                        'auth.author_id',
                        $this->filters['author_id']
                    );
                }
            )

            ->when(
                !empty($this->filters['work_type_id']),
                function ($query) {
                    $query->where(
                        'auth.work_type_id',
                        $this->filters['work_type_id']
                    );
                }
            )

            ->when(
                !empty($this->filters['bases_id']),
                function ($query) {
                    $query->where(
                        'auth.bases_id',
                        $this->filters['bases_id']
                    );
                }
            )

            ->when(
                !empty($this->filters['date_from']),
                function ($query) {
                    $query->whereDate(
                        'auth.created_at',
                        '>=',
                        $this->filters['date_from']
                    );
                }
            )

            ->when(
                !empty($this->filters['date_to']),
                function ($query) {
                    $query->whereDate(
                        'auth.created_at',
                        '<=',
                        $this->filters['date_to']
                    );
                }
            )

            ->orderByDesc('auth.id');
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
            'SHNQ bandi',
            'Ish turi',
            "Berilgan ko'rsatma",
            'Bartaraf etish muddati',
            'Muallif nazorat',
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
            $row->shnq_bandi,
            $row->ish_turi,
            $row->berilgan_korsatma,
            $row->bartaraf_etish_muddati,
            $row->muallif_nazorat,
        ];
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}
