<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Yangi laboratoriya shartnomalari (eski lab_contracts / lab_contract_infos o'rniga, ularga bog'liq emas).
 * Ariza -> laboratoriya ko'rib chiqadi -> shartnoma tuziladi (ishlar tanlanadi) -> buxgalter ->
 * hududiy inspeksiya boshlig'i -> buyurtmachi tasdig'i -> tasdiqlangan -> obyekt biriktiriladi -> sinovlar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_test_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('contract_number', 100)->nullable()->index();
            $table->string('object_name')->nullable();
            $table->foreignId('region_id')->nullable()->index()->constrained('regions');
            // Tasdiqlangandan keyin biriktiriladigan obyekt - shundan so'ng sinovlar o'tkaziladi
            $table->foreignId('object_id')->nullable()->index()->constrained('articles');
            $table->tinyInteger('status')->default(1)->index(); // LabTestContractStatusEnum
            // Buyurtmachi kiritgan obyekt ma'lumotlari (blok, kotlovan, fundament, qavat ...) - tuzilishi erkin
            $table->jsonb('object_data')->nullable();
            // Ilova qilingan hujjatlar: [{type, name, path, is_valid}] - is_valid ni laboratoriya belgilaydi
            $table->jsonb('documents')->nullable();
            $table->integer('vat_percent')->default(12);
            $table->bigInteger('total_price')->default(0);
            $table->bigInteger('total_vat')->default(0);
            $table->bigInteger('total_with_vat')->default(0);
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('created_by')->nullable()->index()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->softDeletes();
            $table->timestamps();
        });

        // Shartnoma ilovasi: tanlangan ish (usul) va uning ichidan tanlangan sinovlar
        Schema::create('lab_test_contract_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_test_contract_id')->index()->constrained('lab_test_contracts')->cascadeOnDelete();
            $table->foreignId('work_type_id')->index()->constrained('work_types'); // Kotlovan, Fundament, Konstruksiya elementlari
            $table->foreignId('lab_method_id')->index()->constrained('lab_methods');
            $table->jsonb('indicator_ids'); // tanlangan sinovlar (lab_method_indicators.id)
            $table->foreignId('employee_id')->nullable()->index()->constrained('users'); // mas'ul xodim (laborant)
            $table->integer('quantity')->default(0); // 1 qavat / bosqich uchun miqdor
            $table->integer('proposed_quantity')->nullable(); // tizim (GOST) taklif qilgan miqdor
            $table->integer('multiplier')->default(1); // ko'p qavatli ish turida - qavatlar soni
            $table->string('unit', 50)->nullable();
            $table->bigInteger('unit_price')->default(0); // tanlangan sinovlar narxlari yig'indisi
            $table->bigInteger('total_price')->default(0); // quantity * unit_price * multiplier
            $table->bigInteger('total_vat')->default(0);
            $table->bigInteger('total_with_vat')->default(0);
            $table->timestamps();
        });

        Schema::create('lab_test_contract_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_test_contract_id')->index()->constrained('lab_test_contracts')->cascadeOnDelete();
            $table->tinyInteger('from_status')->nullable(); // null - ariza yaratilgan
            $table->tinyInteger('to_status');
            $table->text('comment')->nullable();
            $table->jsonb('data')->nullable(); // qo'shimcha: qaytarish sabablari, o'zgarishdan oldingi ma'lumotlar
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->integer('role_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_test_contract_histories');
        Schema::dropIfExists('lab_test_contract_items');
        Schema::dropIfExists('lab_test_contracts');
    }
};
