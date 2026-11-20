<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('declarations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('object_id')->index()->constrained('articles')->nullOnDelete();
            $table->integer('user_id')->index()->nullable();
            $table->tinyInteger('status')->default(1);
            $table->foreignId('fact_type_id')->index()->comment('asos turi')->constrained('fact_types')->nullOnDelete();
            $table->string('fact_number')->nullable()->comment('asos raqami');
            $table->string('fact_date')->nullable()->comment('asos sanasi');
            $table->string('construction_price')->nullable()->comment('Qurilish-montaj ishlarining qiymati');
            $table->string('instrument_price')->nullable()->comment('Uskunalar, asboblar va jihozlar qiymati');
            $table->string('fond_construction_price')->nullable()->comment(' Fond Qurilish-montaj ishlarining qiymati');
            $table->string('fond_instrument_price')->nullable()->comment('Fond Uskunalar, asboblar va jihozlar qiymati');
            $table->text('note')->nullable()->comment('Koʻzdan kechirish va tegishli hujjatlar');
            $table->string('capacity')->nullable()->comment('Obyekt quvvati, unumdorligi');
            $table->string('general_project_info')->nullable()->comment('loyiha Umumiy (avval qabul qilinganlarini hisobga olgan holda)');
            $table->string('general_project_queue')->nullable()->comment('loyiha Shu jumladan ishga tushirish kompleksi yoki navbati');
            $table->string('process_project_info')->nullable()->comment('amalda Umumiy (avval qabul qilinganlarini hisobga olgan holda)');
            $table->string('process_project_queue')->nullable()->comment('amalda Shu jumladan ishga tushirish kompleksi yoki navbati');
            $table->string('product_production')->nullable()->comment('Boshlangʻich davrda loyihaviy quvvatlarini oʻzlashtirish meʼyorlariga mos keluvchi hajmda loyihada koʻzda tutilgan mahsulotni ishlab chiqarish (xizmat koʻrsatish)');
            $table->string('solution_desc')->nullable()->comment('Obyektning texnik va meʼmoriy-qurilish yechimlari tavsifi');
            $table->string('labor_protection')->nullable()->comment('Loyihada koʻzda tutilgan mehnatni muhofaza qilish, portlash xavfsizligini, yongʻin xavfsizligini, atrof-muhit muhofazasini taʼminlaydigan va zilzilaga qarshi tadbirlar');
            $table->string('done_work_type')->nullable()->comment('Ish turi');
            $table->string('done_volume')->nullable()->comment('ish hajmi');
            $table->string('done_note')->nullable()->comment('eng pastdagi Koʻzdan kechirish va tegishli hujjatlar bilan tanishish natijalariga koʻra obyektda qoʻllanilgan texnologik yechimlarning ilgʻorligiga baho');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('declarations');
    }
};
