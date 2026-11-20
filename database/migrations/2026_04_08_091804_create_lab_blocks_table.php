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
        Schema::create('lab_blocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('block_mode_id');
            $table->foreign('block_mode_id')->references('id')->on('block_modes');
            $table->string('block_name')->nullable();
            $table->bigInteger('floor')->nullable();
            $table->unsignedBigInteger('lab_contract_id');
            $table->foreign('lab_contract_id')->references('id')->on('lab_contracts');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_blocks');
    }
};
