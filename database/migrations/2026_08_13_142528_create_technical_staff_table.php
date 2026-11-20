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
        Schema::create('technical_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('object_id')->nullable()->index()->constrained('articles')->nullOnDelete();
            $table->string('pin')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('surname')->nullable();
            $table->text('start_info')->nullable();
            $table->date('end_date')->nullable();
            $table->date('start_date')->nullable();
            $table->tinyInteger('status')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('technical_staff');
    }
};
