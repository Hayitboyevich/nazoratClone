<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laboratoriya sinov usullari ro'yxati (ma'lumotnoma).
 * code - usulning o'zgarmas kaliti: lab_tests.input_data tuzilishi va hisob-kitob shu kod bo'yicha tanlanadi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_methods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('sort')->default(0);
            $table->boolean('status')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_methods');
    }
};
