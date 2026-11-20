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
        Schema::create('lab_test_prices', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('price');
            $table->unsignedBigInteger('test_id');
            $table->foreign('test_id')
                ->references('id')
                ->on('lab_test_categories')
                ->onDelete('set null')
                ->onUpdate('cascade');
            $table->foreignId('region_id')->constrained('regions')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_test_prices');
    }
};
