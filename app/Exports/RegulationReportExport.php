<?php

namespace App\Exports;

use App\Enums\UserRoleEnum;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class RegulationReportExport implements
    FromQuery,
    WithHeadings,
    WithMapping,
    WithChunkReading
{

    public function __construct(
        protected array $filters,
    ){
    }
    public function query(): Builder
    {

        $crmSubQuery = DB::table('regulation_events')
            ->select('regulation_id')
            ->selectRaw("'ha' AS crm")
            ->groupBy('regulation_id');

        return DB::table('regulations as r')

            ->leftJoin('articles as a', 'r.object_id', '=', 'a.id')

            ->leftJoin(
                'regions as reg',
                'a.region_id',
                '=',
                'reg.id'
            )

            ->leftJoin(
                'districts as dis',
                'a.district_id',
                '=',
                'dis.id'
            )

            ->leftJoin(
                'monitorings as mon',
                'r.monitoring_id',
                '=',
                'mon.id'
            )

            ->leftJoin(
                'blocks as mb',
                'mon.block_id',
                '=',
                'mb.id'
            )

            ->leftJoin(
                'spheres as sp',
                'a.sphere_id',
                '=',
                'sp.id'
            )

            ->leftJoin(
                'difficulty_categories as diff',
                'a.difficulty_category_id',
                '=',
                'diff.id'
            )

            ->leftJoin(
                'blocks as b',
                'a.id',
                '=',
                'b.article_id'
            )

            ->leftJoin(
                'object_statuses as os',
                'a.object_status_id',
                '=',
                'os.id'
            )

            ->leftJoin(
                'regulation_statuses as rs',
                'r.regulation_status_id',
                '=',
                'rs.id'
            )

            ->leftJoin(
                'article_users as au',
                'a.id',
                '=',
                'au.article_id'
            )

            ->leftJoin(
                'users as u',
                'au.user_id',
                '=',
                'u.id'
            )

            ->join(
                'users as pu',
                'r.user_id',
                '=',
                'pu.id'
            )

            ->join(
                'users as ui',
                'r.created_by_user_id',
                '=',
                'ui.id'
            )

            ->leftJoin(
                'roles as rol',
                'r.role_id',
                '=',
                'rol.id'
            )
            ->join(
                'regulation_violations as rv',
                'rv.regulation_id',
                '=',
                'r.id'
            )

            ->join(
                'violations as v',
                'v.id',
                '=',
                'rv.violation_id'
            )
            ->leftJoin(
                DB::raw('LATERAL (
                SELECT
                    v2.description,
                    v2.title,
                    v2.comment
                FROM regulation_violations rv2
                JOIN violations v2
                    ON v2.id = rv2.violation_id
                WHERE rv2.regulation_id = r.id
                ORDER BY v2.id
                LIMIT 1
            ) AS fv'),
                DB::raw('true'),
                '=',
                DB::raw('true')
            )
            ->leftJoinSub(
                $crmSubQuery,
                're',
                're.regulation_id',
                '=',
                'r.id'
            )

            ->select([
                'a.name as obyekt_nomi',
                DB::raw("
                MAX(
                    CASE
                        WHEN au.role_id = 8
                        THEN u.organization_name
                    END
                ) AS buyurtmachi
            "),
                'reg.name_uz as viloyat',
                'dis.name_uz as tuman',
                'a.location_building as manzil',
                DB::raw("
                MAX(
                    CASE
                        WHEN au.role_id = 3
                        THEN
                            u.surname || ' ' ||
                            u.name || ' ' ||
                            u.middle_name
                    END
                ) AS inspektor
            "),
                DB::raw("
                COALESCE(
                    a.task_id::text,
                    a.manual_task_id
                ) AS ariza_raqami
            "),
                'os.name as obyekt_holati',
                DB::raw("
                COUNT(DISTINCT b.id) AS bloklar_soni
            "),
                'sp.name_uz as soha',
                'diff.difficulty as murakkablik',
                'r.regulation_number as korsatma_raqami',

                'r.created_at as korsatma_berilgan_sana',
                DB::raw("
                MAX(
                    CASE
                        WHEN r.regulation_status_id IN (6, 8)
                        THEN r.updated_at
                    END
                ) AS bartaraf_etilgan_sana
            "),
                DB::raw("
                ui.surname || ' ' ||
                ui.name || ' ' ||
                ui.middle_name AS inspector_fish
            "),
                'r.deadline as muddat',
                'rol.name as subject',
                DB::raw("
                pu.surname || ' ' ||
                pu.name || ' ' ||
                pu.middle_name AS subject_fish
            "),
                'rs.status as korsatma_holati',
                DB::raw("
                COUNT(DISTINCT v.id) AS qoidabuzarlik_soni
            "),
                'mb.name as blok_nomi',

                'fv.title as ish_turi',

                'fv.comment as aniqlangan_kamchilik',

                'fv.description as berilgan_korsatma',

                DB::raw("
                MAX(
                    CASE
                        WHEN r.lawyer_status_id = 3
                        THEN 'ha'
                        ELSE NULL
                    END
                ) AS mamuriy_qilingan
            "),
                're.crm',
            ])
            ->whereBetween('r.created_at', [
                $this->filters['date_from'] . ' 00:00:00',
                $this->filters['date_to'] . ' 23:59:59',
            ])
            ->when(
                isset($this->filters['region_id']),
                function ($query) {
                    $query->where(
                        'a.region_id',
                        $this->filters['region_id']
                    );
                }
            )

            ->where(
                'r.created_by_role_id',
                UserRoleEnum::INSPECTOR->value
            )
            ->groupBy([
                'a.name',

                'reg.name_uz',
                'dis.name_uz',
                'a.location_building',

                'a.task_id',
                'a.manual_task_id',

                'os.name',

                'sp.name_uz',

                'diff.difficulty',

                'r.regulation_number',
                'r.created_at',
                'r.updated_at',
                'r.regulation_status_id',
                'r.deadline',
                'r.lawyer_status_id',

                'rol.name',

                'rs.status',

                'ui.surname',
                'ui.name',
                'ui.middle_name',

                'pu.surname',
                'pu.name',
                'pu.middle_name',

                'mb.name',

                'fv.title',
                'fv.comment',
                'fv.description',
                're.crm',
            ])
            ->orderBy('r.created_at');
    }

    public function headings(): array
    {
        return [
            'Obyekt nomi',
            'Buyurtmachi',
            'Viloyat',
            'Tuman',
            'Manzil',
            'Inspektor',
            'Ariza raqami',
            'Obyekt holati',
            'Bloklar soni',
            'Soha',
            'Murakkablik',
            'Ko‘rsatma raqami',
            'Ko‘rsatma berilgan sana',
            'Bartaraf etilgan sana',
            'Inspector FISH',
            'Muddat',
            'Subject',
            'Subject FISH',
            'Ko‘rsatma holati',
            'Qoidabuzarlik soni',
            'Blok nomi',
            'Ish turi',
            'Aniqlangan kamchilik',
            'Berilgan ko‘rsatma',
            'Ma’muriy qilingan',
            'CRM',
        ];
    }

    public function map($row): array
    {
        return [
            $row->obyekt_nomi,
            $row->buyurtmachi,
            $row->viloyat,
            $row->tuman,
            $row->manzil,
            $row->inspektor,
            $row->ariza_raqami,
            $row->obyekt_holati,
            $row->bloklar_soni,
            $row->soha,
            $row->murakkablik,
            $row->korsatma_raqami,
            $row->korsatma_berilgan_sana,
            $row->bartaraf_etilgan_sana,
            $row->inspector_fish,
            $row->muddat,
            $row->subject,
            $row->subject_fish,
            $row->korsatma_holati,
            $row->qoidabuzarlik_soni,
            $row->blok_nomi,
            $row->ish_turi,
            $row->aniqlangan_kamchilik,
            $row->berilgan_korsatma,
            $row->mamuriy_qilingan,
            $row->crm,
        ];
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}
