<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ko'p qavatli ish turida sinovlar har bir qavat uchun alohida tanlanadi:
 * floor_indicators - {"1": [26, 27], "2": [26]} (qavat => lab_method_indicators.id ro'yxati),
 * floor_unit_prices - {"1": 80000, "2": 40000} (qavat => shu qavatda tanlangan sinovlar narxi yig'indisi).
 * indicator_ids - barcha qavatlardagi sinovlar birlashmasi bo'lib qoladi.
 * null - eski yozuv yoki qavatsiz ish: barcha qavatlar uchun indicator_ids va unit_price amal qiladi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_test_contract_items', function (Blueprint $table) {
            $table->jsonb('floor_indicators')->nullable();
            $table->jsonb('floor_unit_prices')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('lab_test_contract_items', function (Blueprint $table) {
            $table->dropColumn(['floor_indicators', 'floor_unit_prices']);
        });
    }
};
