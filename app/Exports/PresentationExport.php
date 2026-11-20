<?php

namespace App\Exports;

use App\Enums\LogType;
use App\Enums\UserRoleEnum;
use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PresentationExport implements
    FromQuery,
    WithMapping,
    WithHeadings,
    ShouldAutoSize,
    ShouldQueue
{
    public function __construct(
        protected $query
    ) {
    }

    public function query()
    {
        return $this->query
            ->with([
                'object.region',
                'object.district',
                'object.users',
                'histories',
                'inspector' => fn ($q) => $q->withTrashed()
            ]);
    }

    public function map($presentation): array
    {
        $buyer = $presentation->object?->users
            ?->firstWhere('role_id', UserRoleEnum::BUYURTMACHI->value);

        $builder = $presentation->object?->users
            ?->firstWhere('role_id', UserRoleEnum::QURILISH->value);

        $rejectHistory = $presentation->histories
            ?->firstWhere('type', LogType::PRESENTATION_REJECT_INSPECTION);

        $updateHistory = $presentation->histories
            ?->firstWhere('type', LogType::PRESENTATION_UPDATE_INSPECTION);

        return [
            $presentation->number,
            $presentation->object?->region?->name_uz,
            $presentation->object?->district?->name_uz,

            $presentation->inspector?->full_name,

            $presentation->object?->name,

            $buyer?->organization_name,

            $builder?->organization_name,

            $presentation->object?->task_id
                ?: $presentation->object?->manual_task_id,

            $presentation->material_name,

            $presentation->producer_name,

            $presentation->producer_tin,

            $presentation->certifier_name,

            $presentation->certifier_tin,

            $presentation->quantity,

            $presentation->used,

            $presentation->defect,

            $presentation->created_at,

            $rejectHistory?->created_at,

            data_get($rejectHistory, 'content.comment'),

            $updateHistory?->created_at,

            $presentation->gost_doc,
            $presentation->conclusion_at,
        ];
    }

    public function headings(): array
    {
        return [
            '#',
            'Hudud nomi',
            'Tuman',
            'Inspektor FISH',
            'Qurilish obyekti nomi',
            'Buyurtmachi nomi',
            'Pudratchi nomi',
            'Obyekt ariza raqami',
            'Mahsulot nomi',
            'Ishlab chiqaruvchi nomi',
            'INN',
            'Sertifikat beruvchi tashkilot nomi',
            'INN',
            'Sifatsiz deb aniqlangan mahsulot miqdori',
            'Ishlatilgan mahsulot miqdori',
            'Aniqlangan kamchilik',
            'Dalolatnoma tuzilgan sana',
            'Rad qilingan sana',
            'Izoh',
            'Taqdimnoma yuborilgan sana',
            'Yakuniy xulosa',
            'Agentlik xulosa bergan sana'
        ];
    }
}
