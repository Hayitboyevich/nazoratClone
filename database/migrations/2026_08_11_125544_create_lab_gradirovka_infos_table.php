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
        Schema::create('lab_gradirovka_infos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lab_gradirovka_main_info_id');
            $table->foreign('lab_gradirovka_main_info_id')->references('id')->on('lab_gradirovka_main_infos');
            $table->string('gradirovka_type_name')->nullable();
            $table->text('construction_name_sh')->nullable();
            $table->text('place_sh')->nullable();
            $table->bigInteger('mark_sh')->nullable();
            $table->bigInteger('concrete_year_sh')->nullable();
            $table->float('rate_sh')->nullable();
            $table->text('construction_name_o')->nullable();
            $table->text('place_o')->nullable();
            $table->float('mark_o')->nullable();
            $table->float('coefficient_o')->nullable();
            $table->float('formula_o')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_gradirovka_infos');
    }
};
