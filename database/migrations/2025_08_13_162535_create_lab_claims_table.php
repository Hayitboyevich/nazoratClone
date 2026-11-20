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
        Schema::create('lab_claims', function (Blueprint $table) {
            $table->id();
            $table->text('object_name');
            $table->string('construction_works')->nullable();
            $table->integer('sphere_id')->nullable();
            $table->integer('program_id')->nullable();
            $table->foreignId('lab_test_category_id')->constrained()->nullOnDelete();
            $table->foreignId('lab_testing_org_id')->constrained()->nullOnDelete();
            $table->string('cad_num')->nullable();
            $table->json('project_estimate_file_path')->nullable();
            $table->integer('object_type_id')->nullable()->index()->comment('Tarmoqli yoki bino');
            $table->foreignId('region_id')->nullable()->constrained()->nullOnDelete();
            $table->bigInteger('region_soato')->nullable();
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->bigInteger('district_soato')->nullable();
            $table->string('address')->nullable();
            $table->text('location_building')->nullable();
            $table->string('reestr_num')->nullable();
            $table->string('phone')->nullable();
            $table->integer('status')->default(1);
            $table->string('claim_type')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_claims');
    }
};
