<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bitta ish (qavatli ishda - bitta qavat) bo'yicha bir nechta sinov va bayonnoma bo'lishi mumkin:
 * har bir sinov shartnomadagi miqdorning bir qismini qoplaydi (masalan 3 partiyadan 1 tasi yoki 2 tasi).
 * quantity - shu sinov (bayonnoma) qoplagan miqdor, shartnoma qatori birligida (partiya, komplekt, namuna ...).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_tests', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->default(1);
        });

        // shu paytgacha bitta ish (qavat) bo'yicha bitta sinov bo'lgan - u shartnomadagi miqdorni to'liq qoplagan
        DB::statement(<<<'SQL'
            update lab_tests t
            set quantity = greatest(1, coalesce(
                case when t.floor is null then i.quantity else (i.floor_quantities ->> t.floor::text)::int end, 1))
            from lab_test_contract_items i
            where i.id = t.lab_contract_item_id
        SQL);
    }

    public function down(): void
    {
        Schema::table('lab_tests', function (Blueprint $table) {
            $table->dropColumn('quantity');
        });
    }
};
