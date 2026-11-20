<?php

namespace Database\Seeders;

use App\Models\QualityControlMaterial;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class QualityControlMaterialSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $materials = [
            ['name' => 'Armatura', 'unit' => 't'],
            ['name' => 'Sim', 'unit' => 't'],
            ['name' => 'Po‘lat listlar', 'unit' => 't'],
            ['name' => 'Shvellerlar', 'unit' => 't'],
            ['name' => 'Dvutavrlar', 'unit' => 't'],
            ['name' => 'Burchakliklar', 'unit' => 't'],
            ['name' => 'Po‘lat polosa', 'unit' => 't'],
            ['name' => 'Profil quvurlar', 'unit' => 't'],
            ['name' => 'Po‘lat quvurlar', 'unit' => 't'],
            ['name' => 'Payvandlangan metall to‘r', 'unit' => 'm²'],
            ['name' => 'Metall kolonnalar', 'unit' => 't'],
            ['name' => 'Metall fermalar', 'unit' => 't'],
            ['name' => 'Metall balkalar', 'unit' => 't'],
            ['name' => 'Profnastil', 'unit' => 'm²'],
            ['name' => 'Sendvich panellar', 'unit' => 'm²'],
            ['name' => 'Orayopma plitalar', 'unit' => 'dona'],
            ['name' => 'Tom yopma plitalar', 'unit' => 'dona'],
            ['name' => 'Fundament bloklari', 'unit' => 'dona'],
            ['name' => 'Temir-beton devor panellari', 'unit' => 'dona'],
            ['name' => 'Temir-beton zinapoya marshlari', 'unit' => 'dona'],
            ['name' => 'Temir-beton peremichkalar', 'unit' => 'dona'],
            ['name' => 'Temir-beton lotoklar', 'unit' => 'dona'],
            ['name' => 'Temir-beton quduq halqalari', 'unit' => 'dona'],
            ['name' => 'G‘isht', 'unit' => 'dona'],
            ['name' => 'Gazobeton bloklar', 'unit' => 'm³'],
            ['name' => 'Keramik bloklar', 'unit' => 'm³'],
            ['name' => 'Sement', 'unit' => 't'],
            ['name' => 'Qum', 'unit' => 'm³'],
            ['name' => 'Shcheben', 'unit' => 'm³'],
            ['name' => 'Beton qorishmasi', 'unit' => 'm³'],
            ['name' => 'Yog‘och materiallar', 'unit' => 'm³'],
            ['name' => 'Issiqlik izolyatsiya materiallari', 'unit' => 'm³'],
            ['name' => 'Gidroizolyatsiya materiallari', 'unit' => 'm²'],
            ['name' => 'Boshqa', 'unit' => null],
        ];

        foreach ($materials as $material) {
            QualityControlMaterial::create($material);
        }
    }
}
