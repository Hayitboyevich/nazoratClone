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
        Schema::create('ai_controls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('object_id')->index()->nullable()->constrained('articles');
            $table->integer('control_id')->index()->nullable();
            $table->string('type')->nullable();
            $table->text('desc')->nullable();
            $table->tinyInteger('status')->nullable()->default(1);
            $table->jsonb('response')->nullable();
            $table->string('image');
            $table->timestamps();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('ai_controls');
    }
};
