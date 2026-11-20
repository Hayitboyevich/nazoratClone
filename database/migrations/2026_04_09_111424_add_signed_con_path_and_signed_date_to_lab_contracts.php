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
        Schema::table('lab_contracts', function (Blueprint $table) {
            $table->string('signed_con_path')->nullable();
            $table->date('signed_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lab_contracts', function (Blueprint $table) {
            $table->dropColumn(['signed_con_path', 'signed_date']);
        });
    }
};
