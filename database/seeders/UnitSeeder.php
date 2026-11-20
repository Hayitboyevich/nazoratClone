<?php

namespace Database\Seeders;

use App\Imports\UnitImport;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Maatwebsite\Excel\Facades\Excel;

class UnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = storage_path() . "/excel/units.xlsx";
        Excel::import(new UnitImport(), $data);
    }
}
