<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('claim_excel', function (Blueprint $table) {
            $table->id();
            $table->string('ariza_raqami')->nullable();
            $table->text('obyekt_nomi')->nullable();
            $table->string('obyekt_raqami')->nullable();
            $table->string('region_name')->nullable();
            $table->string('region_id')->nullable();
            $table->string('district_name')->nullable();
            $table->string('cadaster')->nullable();
            $table->string('customer')->nullable();
            $table->string('customer_inn')->nullable();
            $table->string('jami_honadon')->nullable();
            $table->string('jami_block')->nullable();
            $table->string('noturar')->nullable();
            $table->string('turar')->nullable();
            $table->string('yakka')->nullable();
            $table->string('count_apartments')->nullable();
            $table->string('priyomka_jami_block')->nullable();
            $table->string('priyomka_noturar')->nullable();
            $table->string('priyomka_turar')->nullable();
            $table->string('priyomka_yakka')->nullable();
            $table->string('umumiy_maydon')->nullable();
            $table->string('umumiy_noturar')->nullable();
            $table->string('umumiy_turar')->nullable();
            $table->string('umumiy_yakka')->nullable();
            $table->string('foydalanish_maydon')->nullable();
            $table->string('foydalanish_noturar')->nullable();
            $table->string('foydalanish_turar')->nullable();
            $table->string('yashash_maydon')->nullable();
            $table->string('yashash_noturar')->nullable();
            $table->string('yashash_turar')->nullable();
            $table->string('yashash_yakka')->nullable();
            $table->string('qurilish_osti_maydoni')->nullable();
            $table->string('qurilish_osti_noturar')->nullable();
            $table->string('qurilish_osti_turar')->nullable();
            $table->string('qurilish_osti_yakka')->nullable();
            $table->string('inskepsiya_kelgan_vaqt')->nullable();
            $table->string('tashkilotga_yuborilgan_sana')->nullable();
            $table->string('oxirgi_tashkilot_sanasi')->nullable();
            $table->string('inspektor_hulosa_vaqti')->nullable();
            $table->string('inspektor')->nullable();
            $table->string('yuridik_jismoniy')->nullable();
            $table->string('manzil')->nullable();
            $table->string('obyekt_turi')->nullable();
            $table->string('toifasi')->nullable();
            $table->string('obyekt_created_at')->nullable();
            $table->string('inspektorga_yuborilgan_vaqt')->nullable();
            $table->string('direktorga_yuborilgan_vaqt')->nullable();
            $table->string('tugagan_sana')->nullable();
            $table->string('status')->nullable();
            $table->string('end_role_name')->nullable();
            $table->text('reject_reason')->nullable();
            $table->text('dastur_nomi')->nullable();
            $table->text('quyosh_panel')->nullable();
            $table->text('konstruktiv_checklist')->nullable();
            $table->text('seysmik_checklist')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('claim_excel');
    }
};
