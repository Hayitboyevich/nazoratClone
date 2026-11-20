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
        Schema::create('lab_protocol_construction_indices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lab_protocol_construction_id');
            $table->foreign('lab_protocol_construction_id')->references('id')->on('lab_protocol_constructions');
            $table->bigInteger('place')->nullable();
            $table->float('h')->nullable();
            $table->float('r')->nullable();
            $table->float('mark')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_protocol_construction_indices');
    }
};
