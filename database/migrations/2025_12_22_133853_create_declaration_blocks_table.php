<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('declaration_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('declaration_id')->index()->constrained()->nullOnDelete();
            $table->foreignId('block_id')->index()->constrained()->nullOnDelete();
            $table->foreignId('object_id')->index()->constrained('articles')->nullOnDelete();
            $table->string('area')->nullable();
            $table->string('floor')->nullable();
            $table->string('size')->nullable();
            $table->string('under_area')->nullable();
            $table->string('real_area')->nullable();
            $table->string('real_floor')->nullable();
            $table->string('real_size')->nullable();
            $table->string('real_under_area')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('declaration_blocks');
    }
};
