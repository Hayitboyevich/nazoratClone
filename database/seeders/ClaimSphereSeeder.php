<?php

namespace Database\Seeders;

use App\Imports\TopicImport;
use App\Models\ClaimSphere;
use Illuminate\Database\Seeder;
use Maatwebsite\Excel\Facades\Excel;

class ClaimSphereSeeder extends Seeder
{

    public function run(): void
    {
        ClaimSphere::query()->truncate();
        $spheres = storage_path() . "/excel/spheres.xlsx";
        Excel::import(new TopicImport(), $spheres);
    }
}
