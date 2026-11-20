<?php

namespace App\Imports;

use App\Models\Inspection;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class InspectionImport implements ToModel, WithHeadingRow
{

    public function model(array $row)
    {
        return new Inspection([
            'name' => $row['name'],
            'full_name' => $row['full_name'],
            'short_name' => $row['short_name'],
            'stir' => $row['stir'],
            'address' => $row['address'],
            'phone' => $row['phone'],
            'region_id' => $row['region_id'],
        ]);
    }
}
