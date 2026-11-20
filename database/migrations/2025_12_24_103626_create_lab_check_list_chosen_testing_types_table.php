<?php

use App\Enums\LabCheckListTestingStatusEnum;
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
        Schema::create('lab_check_list_chosen_testing_types', function (Blueprint $table) {
            $table->id();
            $table->integer('actions_status')->default(LabCheckListTestingStatusEnum::INITIAL_STATUS->value);
            $table->bigInteger('floor')->nullable();
            $table->unsignedBigInteger('article_id');
            $table->foreign('article_id')->references('id')->on('articles');
            $table->unsignedBigInteger('block_id');
            $table->foreign('block_id')->references('id')->on('blocks');
            $table->unsignedBigInteger('work_type_id');
            $table->foreign('work_type_id')->references('id')->on('work_types');
            $table->string('question_type');
            $table->boolean('statement_decision')->nullable()->comment('true - muvofiq; false - nomuvofiq');
            $table->integer('laborant_answered')->nullable();
            $table->timestamp('laborant_answered_at')->nullable();
            $table->integer('reg_lab_answered')->nullable();
            $table->timestamp('reg_lab_answered_at')->nullable();
            $table->unsignedBigInteger('lab_id')->nullable();
            $table->foreign('lab_id')->references('id')->on('users');
            $table->unsignedBigInteger('reg_lab_id')->nullable();
            $table->foreign('reg_lab_id')->references('id')->on('users');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_check_list_chosen_testing_types');
    }
};
