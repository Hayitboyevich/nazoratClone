<?php

namespace App\Imports;

use App\Models\Unit;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class UnitImport implements ToCollection, WithHeadingRow
{

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            if (empty($row['name_uz'])) {
                continue;
            }

            Unit::query()->create([
                'name_uz' => $row['name_uz'],
                'name_ru' => $row['name_ru'],
            ]);
        }
    }
}
