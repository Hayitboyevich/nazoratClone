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
        Schema::create('foundation_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('object_id')->nullable()->index()->constrained('articles')->nullOnDelete();
            $table->foreignId('block_id')->nullable()->index()->constrained('articles')->nullOnDelete();
            $table->string('foundation_depth')->nullable();
            $table->string('foundation_slope')->nullable();
            $table->jsonb('file')->nullable();
            $table->jsonb('act_file')->nullable();
            $table->jsonb('geo_file')->nullable();
            $table->jsonb('act')->nullable();
            $table->foreignId('monitoring_id')->nullable()->index()->constrained('monitorings')->nullOnDelete();
            $table->foreignId('regulation_id')->nullable()->index()->constrained('regulations')->nullOnDelete();
            $table->tinyInteger('status')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('foundation_blocks');
    }
};
