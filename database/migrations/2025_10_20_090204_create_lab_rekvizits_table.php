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
        Schema::create('lab_rekvizits', function (Blueprint $table) {
            $table->id();
            $table->text('name')->nullable();
            $table->bigInteger('inn');
            $table->bigInteger('district_id');
            $table->bigInteger('region_id');
            $table->bigInteger('region_soato');
            $table->bigInteger('district_soato');
            $table->string('personal_account');
            $table->string('bank_name')->nullable();
            $table->string('bank_code')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_rekvizits');
    }
};
