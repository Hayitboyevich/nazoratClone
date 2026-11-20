<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('lab_checklist_answers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('object_id');
            $table->unsignedBigInteger('block_id');
            $table->foreign('block_id')->references('id')->on('blocks');
            $table->unsignedBigInteger('work_type_id');
            $table->foreign('work_type_id')->references('id')->on('work_types');
            $table->unsignedBigInteger('question_id');
            $table->foreign('question_id')->references('id')->on('questions');
            $table->unsignedBigInteger('object_type_id');
            $table->foreign('object_type_id')->references('id')->on('object_types');
            $table->bigInteger('floor')->nullable();
            $table->integer('status')->default(1);
            $table->integer('laborant_answered')->nullable();
            $table->timestamp('laborant_answered_at')->nullable()->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->integer('reg_lab_answered')->nullable();
            $table->timestamp('reg_lab_answered_at')->nullable()->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_checklist_answers');
    }
};
