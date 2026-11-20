<?php

namespace App\Exports;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class ConcreteWorkExport implements
    FromQuery,
    WithMapping,
    WithChunkReading,
    ShouldAutoSize,
    WithHeadings

{
    protected $filters;
    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function query(): Builder
    {
        return DB::table('concrete_works as con')

            ->leftJoin('articles as a', 'con.object_id', '=', 'a.id')

            ->leftJoin('blocks as b', 'con.block_id', '=', 'b.id')

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

            ->leftJoin(
                'users as us',
                'con.user_id',
                '=',
                'us.id'
            )

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

                'con.id as id',

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

                'con.concrete_grade as olib_kelingan_beton_markasi',

                'con.project_concrete_grade as loyihada_korsatilgan_beton_markasi',

                'con.temperature as beton_xarorati',

                'con.size as beton_xajmi',

                'con.date as ish_bajarilish_sanasi',

                'con.coordinate as kordinata',

                'con.outside_temperature as tashqi_xova_harorati',

                DB::raw("
                    CASE
                        WHEN con.lab_status = 1 THEN 'Ijobiy'
                        WHEN con.lab_status = 0 THEN 'Salbiy'
                        ELSE NULL
                    END AS labaratoriya_sinov_natoijalari
                "),

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

            ->orderByDesc('con.id');
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
            'Olib kelingan beton markasi',
            "Loyihada ko'rsatilgan beton markasi",
            'Betonning quyish paytidagi xarorati',
            'Joyiga quyilgan beton xajmi (bir smenada) m3',
            'Ishlar bajarilish sanasi',
            'Inshoot va konstruktiv elementlarning betonlanayotgan qismining nomi (koordinata o’qlari, otmetkalari)',
            'Tashqi havo harorati',
            'Laboratoriya sinov natijalari',
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
            $row->olib_kelingan_beton_markasi,
            $row->loyihada_korsatilgan_beton_markasi,
            $row->beton_xarorati,
            $row->beton_xajmi,
            $row->ish_bajarilish_sanasi,
            $row->kordinata,
            $row->tashqi_xova_harorati,
            $row->labaratoriya_sinov_natoijalari,
            $row->ichki_nazorat,
        ];
    }

    public function chunkSize(): int
    {
        return 1000;
    }


}
