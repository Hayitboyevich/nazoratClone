<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sinov shartnomada tanlangan usul va ko'rsatkichlar bo'yicha o'tkaziladi:
 * bitta yozuv = bitta usul + bitta ish turi (ko'p qavatli ish turida - bitta qavat) = bitta bayonnoma.
 * Ish turi (bosqich) work_types dan olinadi: Kotlovan, Fundament, Konstruksiya elementlari ...
 * Qavat faqat work_types.is_multiple_floor ish turlarida bo'ladi, shuning uchun floor nullable qilinadi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_tests', function (Blueprint $table) {
            // Shartnomaning (lab_test_contracts, lab_contract_id ustuni) qaysi qatori bo'yicha o'tkazilayotgani
            $table->foreignId('lab_contract_item_id')->nullable()->index()->constrained('lab_test_contract_items');
            $table->foreignId('work_type_id')->nullable()->index()->constrained('work_types');
            // Shartnomada tanlangan, bayonnomaga chiqadigan ko'rsatkichlar (lab_method_indicators.id ro'yxati)
            $table->jsonb('indicator_ids')->nullable();
        });

        DB::statement('alter table lab_tests alter column floor drop not null');
    }

    public function down(): void
    {
        DB::statement('update lab_tests set floor = 0 where floor is null');
        DB::statement('alter table lab_tests alter column floor set not null');

        Schema::table('lab_tests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('work_type_id');
            $table->dropConstrainedForeignId('lab_contract_item_id');
            $table->dropColumn('indicator_ids');
        });
    }
};
