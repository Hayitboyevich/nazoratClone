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
        Schema::create('lab_standarts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('mark')->nullable();
            $table->string('profile')->nullable();
            $table->string('d_mm')->nullable();
            $table->bigInteger('ot_mm')->nullable();
            $table->bigInteger('ov_mm')->nullable();
            $table->bigInteger('b5_percent')->nullable();
            $table->bigInteger('br_percent')->nullable();
            $table->string('flexibility')->nullable();
            $table->string('fr')->nullable();
            $table->string('fluidity')->nullable();
            $table->boolean('status')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_standarts');
    }
};
