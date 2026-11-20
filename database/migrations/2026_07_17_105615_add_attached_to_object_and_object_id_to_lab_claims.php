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
        Schema::table('lab_claims', function (Blueprint $table) {
            $table->boolean('attached_to_object')->default(false);
            $table->unsignedBigInteger('object_id')->nullable();
            $table->foreign('object_id')->references('id')->on('articles');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lab_claims', function (Blueprint $table) {
            $table->dropForeign(['object_id']);
            $table->dropColumn('attached_to_object', 'object_id');
        });
    }
};
