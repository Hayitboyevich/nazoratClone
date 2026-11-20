<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_instruments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->index(); // viloyat-tur-№
            $table->string('type', 20)->index(); // LabInstrumentTypeEnum
            $table->string('name');
            $table->string('serial_number', 100)->nullable(); // zavod №
            $table->string('unit', 10); // g, mm, N
            $table->decimal('range_from', 18, 6)->default(0);
            $table->decimal('range_to', 18, 6)->default(0);
            $table->decimal('resolution', 18, 6)->default(0); // o'qish aniqligi (diskretlik)
            $table->tinyInteger('status')->default(1)->index(); // LabInstrumentStatusEnum
            $table->text('comment')->nullable();
            $table->foreignId('region_id')->nullable()->index()->constrained('regions');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_instruments');
    }
};
