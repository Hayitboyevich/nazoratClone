<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // asbob qaysi sinov turlarida (lab_methods) ishlatiladi; yangi sinov turi qo'shilsa unga o'z asboblari bog'lanadi
        Schema::create('lab_instrument_methods', function (Blueprint $table) {
            $table->foreignId('lab_instrument_id')->constrained('lab_instruments')->cascadeOnDelete();
            $table->foreignId('lab_method_id')->index()->constrained('lab_methods')->cascadeOnDelete();
            $table->primary(['lab_instrument_id', 'lab_method_id']);
        });

        // sinovda ishlatilgan asboblar (lab_instruments.id ro'yxati)
        Schema::table('lab_tests', function (Blueprint $table) {
            $table->jsonb('instrument_ids')->nullable()->after('indicator_ids');
        });
    }

    public function down(): void
    {
        Schema::table('lab_tests', function (Blueprint $table) {
            $table->dropColumn('instrument_ids');
        });
        Schema::dropIfExists('lab_instrument_methods');
    }
};
