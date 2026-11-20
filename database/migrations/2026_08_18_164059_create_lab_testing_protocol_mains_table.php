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
        Schema::create('lab_testing_protocol_mains', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lab_gradirovka_main_info_id');
            $table->foreign('lab_gradirovka_main_info_id')->references('id')->on('lab_gradirovka_main_infos');
            $table->text('test_place')->nullable();
            $table->text('deviations')->nullable();
            $table->text('temperature')->nullable();
            $table->text('humidity')->nullable();
            $table->text('note')->nullable();
            $table->jsonb('homogeneity')->nullable();
            $table->jsonb('equipment')->nullable();
            $table->string('design_class')->nullable();
            $table->float('avg_vf')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_testing_protocol_mains');
    }
};
