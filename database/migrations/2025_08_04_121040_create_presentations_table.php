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
        Schema::create('presentations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('object_id')->index()->constrained('articles');
            $table->foreignId('material_id')->index()->constrained();
            $table->tinyInteger('status');
            $table->string('number')->index();
            $table->tinyInteger('technic_answer')->default(0);
            $table->tinyInteger('internal_answer')->default(0);
            $table->text('description')->nullable()->comment('izoh');
            $table->string('producer_tin')->nullable()->comment('ishlab chiqaruvchi tin');
            $table->string('producer_name')->nullable()->comment('ishlab chiqaruvchi nomi');
            $table->string('certifier_tin')->nullable()->comment('sertifikat beruvchi tin');
            $table->string('certifier_name')->nullable()->comment('sertificat beruvchi nomi');
            $table->text('defect')->nullable()->comment('aniqlangan kamchilik');
            $table->string('date')->nullable()->comment('olib kelingan sana');
            $table->string('quantity')->nullable()->comment('olib kelingan miqdor');
            $table->string('used')->nullable()->comment('ishlatilgan miqdor');
            $table->jsonb('images')->nullable();
            $table->jsonb('certificate_files')->nullable()->comment('hisob faktura fayl');
            $table->jsonb('presentation_files')->nullable()->comment('ishlab chiqaruvchi fayl');
            $table->jsonb('report_files')->nullable()->comment('sinov bayonnomasi fayl');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('presentations');
    }
};
