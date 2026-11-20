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
        Schema::create('lab_check_list_testing_statement_marks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lab_check_list_statement_id');
            $table->foreign('lab_check_list_statement_id')->references('id')->on('lab_check_list_testing_statements');
            $table->unsignedBigInteger('lab_check_list_testing_main_info_id')->nullable();
            $table->foreign('lab_check_list_testing_main_info_id')->references('id')->on('lab_check_list_testing_main_infos');
            $table->float('concrete_norm');
            $table->float('first_mark');
            $table->float('second_mark');
            $table->float('third_mark');
            $table->float('fourth_mark');
            $table->float('fifth_mark');
            $table->float('sixth_mark');
            $table->float('average_mark');
            $table->float('first_num');
            $table->float('second_num');
            $table->boolean('decision')->comment('muvofiq (true) yoki muvofiq emas (false)');
            $table->float('concrete_sustainability')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_check_list_testing_statement_marks');
    }
};
