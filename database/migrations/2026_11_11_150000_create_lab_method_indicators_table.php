<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Usul ichidagi sinovlar (bayonnomaga chiqadigan ko'rsatkichlar) va ularning standartlari.
 * Masalan «Armatura» usuli: F0 (ГОСТ 12004 п.1.4), δ5 (ГОСТ 12004 п.3.1), Egilish (ГОСТ 14019) ...
 * Shartnomada aynan shu ko'rsatkichlar tanlanadi, laborant faqat tanlanganlari bo'yicha sinov o'tkazadi.
 */
return new class extends Migration
{
    public function up(): void
    {
        // usulning o'lchov birligi: komplekt, seriya, uchastka, partiya, dona ...
        Schema::table('lab_methods', function (Blueprint $table) {
            $table->string('unit', 50)->nullable();
        });

        Schema::create('lab_method_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_method_id')->index()->constrained('lab_methods');
            $table->string('code', 50); // usul ichida o'zgarmas kalit: input_data / result_data shu kod bo'yicha yoziladi
            $table->string('name');
            $table->string('standard')->nullable(); // GOST / O'zDSt va bandi
            $table->bigInteger('price')->default(0); // 1 birlik uchun narx (QQSsiz, so'm) - shartnoma summasi shundan hisoblanadi
            $table->integer('sort')->default(0);
            $table->boolean('status')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['lab_method_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_method_indicators');

        Schema::table('lab_methods', function (Blueprint $table) {
            $table->dropColumn('unit');
        });
    }
};
