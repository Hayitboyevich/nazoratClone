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
        Schema::create('lab_user_action_histories', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('lab_claim_id')->nullable();
            $table->bigInteger('role_id');
            $table->integer('action_status')->nullable();
            $table->string('info')->nullable();
            $table->text('type');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_user_action_histories');
    }
};
