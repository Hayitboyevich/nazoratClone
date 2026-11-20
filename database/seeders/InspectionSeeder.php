<?php

namespace Database\Seeders;

use App\Imports\InspectionImport;
use App\Models\Inspection;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Maatwebsite\Excel\Facades\Excel;

class InspectionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Inspection::query()->truncate();
        $inspections = storage_path() . "/excel/inspections.xlsx";
        Excel::import(new InspectionImport(), $inspections);
    }
}
