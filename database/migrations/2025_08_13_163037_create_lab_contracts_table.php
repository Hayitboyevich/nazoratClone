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
        Schema::create('lab_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_claim_id')->constrained('lab_claims')->nullOnDelete();
            $table->bigInteger('con_total_price');
            $table->bigInteger('con_total_nds');
            $table->bigInteger('con_total_price_with_nds');
            $table->timestamp('inspeksiya_confirmed_at')->nullable();
            $table->timestamp('customer_confirmed_at')->nullable();
            $table->integer('status');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_contracts');
    }
};
