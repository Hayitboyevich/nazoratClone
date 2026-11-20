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
        Schema::create('lab_test_categories', function (Blueprint $table) {
            $table->id();
            $table->text('name_uz');
            $table->text('name_ru');
            $table->text('description_uz')->nullable();
            $table->text('description_ru')->nullable();
            $table->integer('amount')->nullable();
            $table->string('unity')->nullable();
            $table->boolean('used')->default(false);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->foreign('parent_id')
                ->references('id')
                ->on('lab_test_categories')
                ->onDelete('set null')
                ->onUpdate('cascade');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_test_categories');
    }
};
