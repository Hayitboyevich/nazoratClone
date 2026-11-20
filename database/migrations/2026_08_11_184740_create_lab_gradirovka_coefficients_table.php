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
        Schema::create('lab_gradirovka_coefficients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lab_gradirovka_main_info_id');
            $table->foreign('lab_gradirovka_main_info_id')->references('id')->on('lab_gradirovka_main_infos')->onDelete('cascade');
            $table->float('coefficient_a')->nullable();
            $table->float('coefficient_b')->nullable();
            $table->float('e_1')->nullable();
            $table->float('e_2')->nullable();
            $table->float('e_3')->nullable();
            $table->float('e_4')->nullable();
            $table->float('e_5')->nullable();
            $table->float('e_6')->nullable();
            $table->float('e_7')->nullable();
            $table->float('e_8')->nullable();
            $table->float('e_9')->nullable();
            $table->float('g_koef')->nullable();
            $table->float('g_shmidt')->nullable();
            $table->float('g_onix')->nullable();
            $table->float('g_ratio')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_gradirovka_coefficients');
    }
};
