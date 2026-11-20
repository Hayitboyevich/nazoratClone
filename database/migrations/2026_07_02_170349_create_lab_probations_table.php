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
        Schema::create('lab_probations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('object_id')->index()->constrained('articles')->nullOnDelete();
            $table->foreignId('block_id')->index()->constrained('blocks')->nullOnDelete();
            $table->foreignId('lab_checklist_answer_id')->index()->constrained('blocks')->nullOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_probations');
    }
};
