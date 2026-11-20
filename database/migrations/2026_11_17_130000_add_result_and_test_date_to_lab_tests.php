<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_tests', function (Blueprint $table) {
            // sinov o'tkazilgan (boshlangan) sana: asboblar sertifikati shu sanaga tekshiriladi
            $table->date('test_date')->nullable()->after('quantity');
            // LabTestResultEnum: 1 - ijobiy, 2 - salbiy
            $table->unsignedTinyInteger('result')->nullable()->index()->after('test_date');
        });
    }

    public function down(): void
    {
        Schema::table('lab_tests', function (Blueprint $table) {
            $table->dropColumn(['test_date', 'result']);
        });
    }
};
