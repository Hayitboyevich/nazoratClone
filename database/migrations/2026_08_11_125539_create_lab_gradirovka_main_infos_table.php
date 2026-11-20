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
        Schema::create('lab_gradirovka_main_infos', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('object_id');
            $table->bigInteger('block_id');
            $table->bigInteger('lab_contract_id')->nullable();
            $table->bigInteger('work_type_id')->nullable();
            $table->bigInteger('question_id')->nullable();
            $table->string('test_scheme_name')->nullable();
            $table->integer('status')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_gradirovka_main_infos');
    }
};
