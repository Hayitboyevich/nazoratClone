<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shartnoma oqimi «Laboratoriya kabinetlari» bo'yicha qayta tuziladi:
 * laboratoriya boshlig'i ariza yaratib laborantga biriktiradi -> laborant blok, qavat va ishlarni kiritadi ->
 * buxgalter -> inspeksiya boshlig'i -> buyurtmachi tasdig'i.
 *
 * Holatlar qayta raqamlanadi (LabTestContractStatusEnum):
 *   eski 1,2,3 (ko'rib chiqish / qaytarilgan / tuzilmoqda) -> 1 Laborantda
 *   eski 4 -> 2 Buxgalterda, 5 -> 3 Inspeksiya boshlig'ida, 6 -> 4 Buyurtmachi tasdig'ida,
 *   7 -> 5 Tasdiqlangan, 8 -> 6 Bekor qilingan
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_test_contracts', function (Blueprint $table) {
            // Obyekt
            $table->tinyInteger('object_type_id')->nullable()->index(); // ObjectTypeEnum: 1 tarmoq, 2 bino
            $table->string('construction_type', 100)->nullable(); // Yangi qurilish, Rekonstruksiya, Kapital ta'mirlash
            $table->string('district')->nullable();
            $table->text('address')->nullable();
            $table->string('cadastral_number', 100)->nullable()->index();
            $table->string('owner_name')->nullable(); // mulk egasi (kadastr bo'yicha)
            $table->boolean('expertise_exists')->nullable();
            $table->string('expertise_number', 100)->nullable();
            // Buyurtmachi - tizimga kirganda shu STIR / JShShIR bo'yicha topiladi
            $table->tinyInteger('customer_type')->nullable(); // 1 yuridik, 2 jismoniy
            $table->string('customer_tin', 20)->nullable()->index(); // STIR (9) yoki JShShIR (14)
            $table->string('customer_name')->nullable();
            $table->string('customer_phone', 50)->nullable();
            // Sinov usuli va mas'ul laborant
            $table->tinyInteger('test_type')->default(1)->index(); // LabTestTypeEnum
            $table->foreignId('employee_id')->nullable()->index()->constrained('users');
            $table->text('comment')->nullable(); // boshliqning laborantga ko'rsatmasi
            // Buxgalter yoki inspeksiya boshlig'i laborantga qaytargani: {by_role, user_id, at, comment, reasons[]}
            $table->jsonb('return_data')->nullable();
        });

        DB::statement('update lab_test_contracts set status = case
            when status in (1, 2, 3) then 1
            when status = 4 then 2
            when status = 5 then 3
            when status = 6 then 4
            when status = 7 then 5
            when status = 8 then 6
            else status end');

        // Blok: nomi, qavatlar soni va erkin tuzilishdagi ma'lumotlar (GPS turi, fundament, har bir qavat)
        Schema::create('lab_test_contract_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_test_contract_id')->index()->constrained('lab_test_contracts')->cascadeOnDelete();
            $table->string('name');
            $table->integer('floors_count')->default(1);
            $table->jsonb('data')->nullable(); // {gps, fund: {tur, B, V, S, L}, floors: [{kol, rig, pl, arm, wall, chok}]}
            $table->integer('sort')->default(0);
            $table->timestamps();
        });

        Schema::table('lab_test_contract_items', function (Blueprint $table) {
            $table->foreignId('lab_test_contract_block_id')->nullable()->index()
                ->constrained('lab_test_contract_blocks')->cascadeOnDelete();
            // Ko'p qavatli ish turida miqdor har bir qavat uchun alohida: {"1": 20, "2": 22}; quantity - yig'indisi
            $table->jsonb('floor_quantities')->nullable();
            $table->jsonb('proposed_floor_quantities')->nullable(); // tizim (GOST) taklifi
        });

        Schema::table('lab_method_indicators', function (Blueprint $table) {
            $table->tinyInteger('test_type')->default(1)->index(); // LabTestTypeEnum: sinov qaysi usulga tegishli
            $table->boolean('is_available')->default(true); // false - laboratoriya akkreditatsiyasida yo'q
        });

        Schema::table('lab_tests', function (Blueprint $table) {
            $table->foreignId('lab_contract_block_id')->nullable()->index()->constrained('lab_test_contract_blocks');
        });
    }

    public function down(): void
    {
        Schema::table('lab_tests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lab_contract_block_id');
        });

        Schema::table('lab_method_indicators', function (Blueprint $table) {
            $table->dropColumn(['test_type', 'is_available']);
        });

        Schema::table('lab_test_contract_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lab_test_contract_block_id');
            $table->dropColumn(['floor_quantities', 'proposed_floor_quantities']);
        });

        Schema::dropIfExists('lab_test_contract_blocks');

        DB::statement('update lab_test_contracts set status = case
            when status = 1 then 3
            when status = 2 then 4
            when status = 3 then 5
            when status = 4 then 6
            when status = 5 then 7
            when status = 6 then 8
            else status end');

        Schema::table('lab_test_contracts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employee_id');
            $table->dropColumn([
                'object_type_id', 'construction_type', 'district', 'address', 'cadastral_number', 'owner_name',
                'expertise_exists', 'expertise_number', 'customer_type', 'customer_tin', 'customer_name',
                'customer_phone', 'test_type', 'comment', 'return_data',
            ]);
        });
    }
};
