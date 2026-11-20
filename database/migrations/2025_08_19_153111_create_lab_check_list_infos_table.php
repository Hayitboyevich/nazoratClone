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
        Schema::create('lab_check_list_infos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('block_id')->nullable();
            $table->foreign('block_id')->references('id')->on('blocks')
                ->onDelete('set null');
            $table->unsignedBigInteger('object_id')->nullable();
            $table->foreign('object_id')->references('id')->on('articles')
            ->onDelete('set null');
            $table->unsignedBigInteger('checklist_id');
            $table->foreign('checklist_id')->references('id')->on('check_list_answers')
                ->onDelete('set null');
            $table->unsignedBigInteger('history_id');
            $table->foreign('history_id')->references('id')->on('check_list_histories')
                ->onDelete('set null');
            $table->text('description')->nullable();
            $table->integer('type')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users');
            $table->unsignedBigInteger('role_id');
            $table->foreign('role_id')->references('id')->on('roles');
            $table->json('files')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_check_list_infos');
    }
};
