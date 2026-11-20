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
        Schema::create('lab_test_action_infos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lab_gradirovka_main_info_id');
            $table->foreign('lab_gradirovka_main_info_id')->references('id')->on('lab_gradirovka_main_infos');
            $table->text('construction_name')->nullable();
            $table->text('place_name')->nullable();
            $table->bigInteger('concrete_year')->nullable();
            $table->float('index_first')->nullable();
            $table->float('index_second')->nullable();
            $table->float('index_third')->nullable();
            $table->float('index_fourth')->nullable();
            $table->float('index_fifth')->nullable();
            $table->float('index_sixth')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_test_action_infos');
    }
};
