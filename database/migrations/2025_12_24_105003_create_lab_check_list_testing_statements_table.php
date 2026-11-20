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
        Schema::create('lab_check_list_testing_statements', function (Blueprint $table) {
            $table->id();
            $table->float('temperature', 10);
            $table->text('relative_humidity');
            $table->float('concrete_temperature', 10);
            $table->text('first_test_tool');
            $table->text('second_test_tool')->nullable();
            $table->text('third_test_tool')->nullable();
            $table->text('fourth_test_tool')->nullable();
            $table->text('fifth_test_tool')->nullable();
            $table->text('sixth_test_tool')->nullable();
            $table->text('test_equipment');
            $table->text('auxiliary_equipment');
            $table->integer('status')->default(1);
            $table->bigInteger('floor')->nullable();
            $table->unsignedBigInteger('article_id');
            $table->foreign('article_id')->references('id')->on('articles');
            $table->unsignedBigInteger('block_id');
            $table->foreign('block_id')->references('id')->on('blocks');
            $table->unsignedBigInteger('work_type_id');
            $table->foreign('work_type_id')->references('id')->on('work_types');
            $table->string('question_type');
            $table->text('testing_object_name');
            $table->text('method_concrete_strength');
            $table->text('document_number');
            $table->text('testing_object_condition');
            $table->text('leaving_from_testing_method');
            $table->text('others_testing_work');
            $table->unsignedBigInteger('lab_check_list_chosen_testing_type_id');
            $table->foreign('lab_check_list_chosen_testing_type_id')->references('id')->on('lab_check_list_chosen_testing_types');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_check_list_testing_statements');
    }
};
