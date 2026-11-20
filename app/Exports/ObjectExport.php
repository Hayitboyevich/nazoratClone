<?php

namespace App\Exports;

use App\Enums\UserRoleEnum;
use App\Models\Article;
use Maatwebsite\Excel\Concerns\FromCollection;

class ObjectExport implements FromCollection
{
    protected $data;
    public function __construct($objects)
    {
        $this->data = $objects;
    }

    public function collection()
    {
        $rows = collect();

        foreach ($this->data as $item) {
            $object = Article::query()
                ->where('number_protocol', $item['guid'])
//                ->where('number_protocol', '186055206')
                ->first();

            $row = $item;

            if ($object) {
                $user = $object->users()->wherePivot('role_id', UserRoleEnum::BUYURTMACHI->value)->first();
                $row['object_name'] = $object->name ?? null;
                $row['object_task_id'] = $object->task_id ?? null;
                $row['object_sphere'] = $object->sphere->name_uz ?? null;
                $row['object_district'] = $object->district->name_uz ?? null;
                $row['object_address'] = $object->location_building ?? null;
                $row['object_status'] = $object->objectStatus->name ?? null;
                $row['organization_name'] = $user->organization_name ?? null;
                $row['organization_inn'] = $user->pinfl ?? null;
            }

            $rows->push($row);
        }

        return $rows;
    }
}
