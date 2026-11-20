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
        Schema::table('lab_contract_infos', function (Blueprint $table) {
            $table->string('block')->nullable();
            $table->string('floor')->nullable();
            $table->unsignedBigInteger('work_type_id')->nullable();
            $table->foreign('work_type_id')->references('id')->on('work_types')
                ->onDelete('set null');
            $table->unsignedBigInteger('question_id')->nullable();
            $table->foreign('question_id')->references('id')->on('questions')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lab_contract_infos', function (Blueprint $table) {
            $table->dropColumn(['block', 'floor', 'work_type_id', 'question_id']);
        });
    }
};
