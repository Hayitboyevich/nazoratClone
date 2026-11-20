<?php

namespace Database\Seeders;

use App\Models\Material;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;

class MaterialSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = Http::get('https://mk.shaffofqurilish.uz/api/get-props');
        Material::query()->truncate();

        $json = $data->json();

        foreach ($json['result']['data'] as $volume) {
            $volumeModel = Material::create([
                'name' => $volume['name'],
                'old_id' => $volume['old_id'],
                'type' => 'volume',
                'parent_id' => null,
            ]);

            foreach ($volume['categories'] as $category) {
                $categoryModel = Material::create([
                    'name' => $category['name'],
                    'old_id' => $category['old_id'],
                    'type' => 'category',
                    'parent_id' => $volumeModel->id,
                ]);

                foreach ($category['groups'] as $group) {
                    Material::create([
                        'name' => $group['name'],
                        'old_id' => $group['old_id'],
                        'type' => 'group',
                        'parent_id' => $categoryModel->id,
                    ]);
                }
            }
        }


    }
}
