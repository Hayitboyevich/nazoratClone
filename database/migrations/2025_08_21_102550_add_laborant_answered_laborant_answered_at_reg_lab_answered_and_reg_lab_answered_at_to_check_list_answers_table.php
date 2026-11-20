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
        Schema::table('check_list_answers', function (Blueprint $table) {
            $table->integer('laborant_answered')->nullable();
            $table->timestamp('laborant_answered_at')->nullable();
            $table->integer('reg_lab_answered')->nullable();
            $table->timestamp('reg_lab_answered_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('check_list_answers', function (Blueprint $table) {
            $table->dropColumn(['laborant_answered', 'laborant_answered_at','reg_lab_answered', 'reg_lab_answered_at']);
        });
    }
};
