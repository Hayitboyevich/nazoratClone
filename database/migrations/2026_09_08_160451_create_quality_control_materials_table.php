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
        Schema::create('quality_control_materials', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('unit')->nullable();
            $table->timestamps();
        });

        Schema::table('quality_controls', function (Blueprint $table) {
            $table->integer('material_id')->index()->nullable();
            $table->text('desc')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quality_control_materials');
    }
};
