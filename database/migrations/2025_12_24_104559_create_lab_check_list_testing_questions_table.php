<?php

use App\Enums\LabCheckListTestingStatusEnum;
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
        Schema::create('lab_check_list_testing_questions', function (Blueprint $table) {
            $table->id();
            $table->text('name');
            $table->unsignedBigInteger('lab_check_list_testing_main_info_id');
            $table->foreign('lab_check_list_testing_main_info_id')->references('id')->on('lab_check_list_testing_main_infos');
            $table->bigInteger('status')->default(LabCheckListTestingStatusEnum::INITIAL_STATUS->value);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_check_list_testing_questions');
    }
};
