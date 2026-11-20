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
        Schema::create('lab_protocol_constructions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lab_protocol_id');
            $table->foreign('lab_protocol_id')->references('id')->on('lab_testing_protocol_mains');
            $table->text('construction_name')->nullable();
            $table->text('location')->nullable();
            $table->bigInteger('concrete_age')->nullable();
            $table->float('rm')->nullable();
            $table->float('vf')->nullable();
            $table->float('kt_kc')->nullable();
            $table->float('uncertainty')->nullable();
            $table->boolean('meets_norm')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_protocol_constructions');
    }
};
