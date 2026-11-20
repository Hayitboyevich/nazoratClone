<?php

namespace App\Console\Commands;

use App\Models\District;
use App\Models\ObjectStatus;
use App\Models\Program;
use App\Models\Region;
use App\Models\Sphere;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class SyncReferenceDataToRedis extends Command
{

    protected $signature = 'redis:sync';


    protected $description = 'Command description';


    public function handle()
    {
        $this->cacheTable(
            'regions',
            Region::select('id', 'name_uz', 'name_ru', 'soato', 'region_code')->orderBy('id')
        );

        $this->cacheTable(
            'spheres',
            Sphere::select('id', 'name_uz', 'name_ru')->orderBy('id')
        );

        $this->cacheTable(
            'programs',
            Program::select('id', 'name_uz', 'name_ru')->orderBy('id')
        );

        $this->cacheTable(
            'object_statuses',
            ObjectStatus::select('id', 'name', 'description')->orderBy('id')
        );

        District::query()
            ->select('id', 'name_uz', 'name_ru', 'soato', 'region_id')
            ->orderBy('region_id')
            ->orderBy('id')
            ->get()
            ->groupBy('region_id')
            ->each(function ($districts, $regionId) {
                Redis::set(
                    "districts:{$regionId}",
                    $districts->values()->toJson()
                );
            });
    }

    protected function cacheTable(string $key, $query): void
    {
        Redis::set($key, $query->get()->toJson());
    }
}
