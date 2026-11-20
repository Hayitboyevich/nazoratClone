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
        Schema::create('lab_contract_infos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_contract_id')->constrained('lab_contracts')->nullOnDelete();
            $table->foreignId('lab_test_category_id')->constrained('lab_test_categories')->nullOnDelete();
            $table->bigInteger('amount');
            $table->bigInteger('price');
            $table->bigInteger('total_price');
            $table->bigInteger('nds');
            $table->bigInteger('total_price_with_nds');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_contract_infos');
    }
};
