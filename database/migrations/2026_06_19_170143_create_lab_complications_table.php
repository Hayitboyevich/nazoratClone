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
        Schema::create('lab_complications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_claim_id')->index()->constrained('lab_claims');
            $table->foreignId('lab_contract_id')->index()->constrained('lab_contracts');
            $table->foreignId('lab_test_category_id')->index()->nullable()->constrained('lab_test_categories');
            $table->integer('complication_category_id')->index()->nullable();
            $table->string('building_size')->nullable();
            $table->string('price')->nullable();
            $table->string('total_price')->nullable();
            $table->string('qqs_price')->nullable();
            $table->string('total_with_qqs_price')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_complications');
    }
};
