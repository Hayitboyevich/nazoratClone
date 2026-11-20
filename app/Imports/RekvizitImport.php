<?php

namespace App\Imports;

use App\Models\District;
use App\Models\Rekvizit;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class RekvizitImport implements ToModel, WithHeadingRow
{

//    public function model(array $row)
//    {
//
////        $district = District::query()->where('soato', $row['soato'])->first();
//        $rekvizit = Rekvizit::query()->where('soato', $row['soato'])->first();
////        $name = $district->region->name_uz.' qurilish va uy-joy kommunal xo’jaligi sohasida hududiy nazorat qilish inspeksiyasi G’azna x/r: '. $row['raqam'].'  MFO: 00014 STIR: 201122919 Markaziy bank Toshkent shahar hisob-kitob kassa markazi';
//         $rekvizit->update(['stir' => $row['raqam']]);
//         return $rekvizit;
//    }

    public function model(array $row)
    {
        $rekvizit = Rekvizit::query()->where('soato', $row['soato'])->first();

        if ($rekvizit) {
            $rekvizit->update(['stir' => $row['raqam']]);
            return $rekvizit;
        }

        return null;
    }
}
