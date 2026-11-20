<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qo'shimcha shartnoma: tasdiqlangan asosiy shartnomaga keyinchalik yangi blok yoki oldin tanlanmagan
 * sinovlar qo'shiladi. parent_id - asosiy shartnoma (qo'shimchalarning hammasi to'g'ridan-to'g'ri asosiysiga bog'lanadi).
 * parent_block_id - qo'shimcha shartnomaga oldingi shartnomalardan ko'chirilgan blokning asl bloki
 * (null - shu shartnomada yangi kiritilgan blok).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_test_contracts', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->index()->constrained('lab_test_contracts');
        });

        Schema::table('lab_test_contract_blocks', function (Blueprint $table) {
            $table->foreignId('parent_block_id')->nullable()->index()->constrained('lab_test_contract_blocks');
        });
    }

    public function down(): void
    {
        Schema::table('lab_test_contract_blocks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_block_id');
        });

        Schema::table('lab_test_contracts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};
