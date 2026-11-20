<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laboratoriya sinovlari - har bir qavat uchun alohida yozuv va alohida bayonnoma. Barcha usullar bitta jadvalda:
 * input_data - usulga xos kiritilgan ma'lumotlar, result_data - hisoblangan natijalar (ikkalasi ham jsonb).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_method_id')->index()->constrained('lab_methods');
            // Shartnoma keyinchalik ulanadi - hozircha null, FK cheklovi qo'yilmagan
            $table->unsignedBigInteger('lab_contract_id')->nullable()->index();
            // Sinov bino qavati bo'yicha o'tkaziladi: bitta yozuv = bitta qavat = bitta bayonnoma
            $table->foreignId('lab_block_id')->nullable()->index()->constrained('lab_blocks');
            $table->integer('floor')->index(); // yerto'la uchun manfiy bo'lishi mumkin
            $table->foreignId('region_id')->nullable()->index()->constrained('regions');
            $table->tinyInteger('status')->default(1)->index(); // LabTestStatusEnum
            $table->jsonb('input_data')->nullable();
            $table->jsonb('result_data')->nullable();
            // Bayonnoma - sinov yakunida shakllantiriladi
            $table->string('protocol_number', 100)->nullable()->index();
            $table->timestamp('protocol_formed_at')->nullable();
            $table->string('protocol_path')->nullable();
            // Bayonnomani ko'rib chiqqan rahbar (tasdiq / rad / tahrirga qaytarish)
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('created_by')->nullable()->index()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_tests');
    }
};
