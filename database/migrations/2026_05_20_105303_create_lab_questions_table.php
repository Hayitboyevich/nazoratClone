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
        Schema::create('lab_questions', function (Blueprint $table) {
            $table->id();
            $table->text('name');
            $table->unsignedBigInteger('object_type_id');
            $table->foreign('object_type_id')->references('id')->on('object_types');
            $table->unsignedBigInteger('work_type_id');
            $table->foreign('work_type_id')->references('id')->on('work_types');
            $table->unsignedBigInteger('role_id')->nullable();
            $table->foreign('role_id')->references('id')->on('roles');
            $table->integer('type')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_laboratoriya')->default(true);
            $table->boolean('is_only_lab')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_questions');
    }
};
