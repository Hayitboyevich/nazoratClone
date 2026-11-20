<?php

use App\Enums\LabCheckListTestingOnixtStatusEnum;
use App\Enums\LabCheckListTestingSchmidtStatusEnum;
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
        Schema::create('lab_check_list_testing_conditions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lab_check_list_chosen_testing_type_id');
            $table->foreign('lab_check_list_chosen_testing_type_id')->references('id')->on('lab_check_list_chosen_testing_types');
            $table->unsignedBigInteger('question_id');
            $table->foreign('question_id')->references('id')->on('questions');
            $table->unsignedBigInteger('schmidt_main_info_id')->nullable();
            $table->unsignedBigInteger('onix_main_info_id')->nullable();
            $table->bigInteger('status')->default(LabCheckListTestingStatusEnum::INITIAL_STATUS->value);
            $table->bigInteger('onix_status')->default(LabCheckListTestingOnixtStatusEnum::INITIAL_STATUS->value);
            $table->bigInteger('schmidt_status')->default(LabCheckListTestingSchmidtStatusEnum::INITIAL_STATUS->value);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_check_list_testing_conditions');
    }
};
