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
        Schema::create('lab_testing_orgs', function (Blueprint $table) {
            $table->id();
            $table->text('name');
            $table->foreignId('region_id')->constrained('regions')->nullOnDelete();
            $table->foreignId('district_id')->constrained('districts')->nullOnDelete();
            $table->bigInteger('inn');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_testing_orgs');
    }
};
