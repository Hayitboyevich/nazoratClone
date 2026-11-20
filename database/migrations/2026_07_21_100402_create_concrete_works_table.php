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
        Schema::create('concrete_works', function (Blueprint $table) {
            $table->id();
            $table->foreignId('object_id')->index()->nullable()->constrained('articles')->nullOnDelete();
            $table->foreignId('block_id')->index()->nullable()->constrained('blocks')->nullOnDelete();
            $table->foreignId('user_id')->index()->nullable()->constrained('users')->nullOnDelete();
            $table->date('date')->nullable();
            $table->string('coordinate')->nullable()->comment('koordinata');
            $table->string('concrete_grade')->nullable()->comment('olib kelingan beton markasi');
            $table->string('project_concrete_grade')->nullable()->comment('loyihada korsatilgan  beton markasi');
            $table->string('temperature')->nullable()->comment('betonning quyish paytidagi xarorat');
            $table->string('size')->nullable()->comment('joyiga quyilgan beton hajmi');
            $table->string('outside_temperature')->nullable()->comment('tashqi xavo xarorati');
            $table->string('mark')->nullable()->comment('marka');
            $table->text('result')->nullable()->comment('natija');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('concrete_works');
    }
};
