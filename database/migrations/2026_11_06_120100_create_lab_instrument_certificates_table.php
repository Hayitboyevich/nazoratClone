<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_instrument_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_instrument_id')->index()->constrained('lab_instruments')->cascadeOnDelete();
            $table->string('doc_type', 20); // LabCertificateDocTypeEnum
            $table->string('number', 100)->nullable(); // hujjat №
            $table->date('issued_at');
            $table->date('valid_to')->index();
            $table->string('value_form', 5)->default('abs'); // abs - absolyut, pct - % ko'rsatishdan
            $table->decimal('delta', 18, 6)->nullable(); // qiyoslash: ±Δ
            $table->boolean('use_correction')->default(true); // tuzatmani hisobga olish
            $table->string('deviation_meaning', 5)->default('corr'); // corr - tuzatma, dev - ko'rsatish - etalon
            $table->jsonb('points')->nullable(); // kalibrlash nuqtalari: [{x, u, d}]
            $table->string('file')->nullable();
            $table->string('file_name')->nullable();
            $table->tinyInteger('status')->default(1)->index(); // LabCertificateStatusEnum
            $table->text('comment')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users');
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_instrument_certificates');
    }
};
