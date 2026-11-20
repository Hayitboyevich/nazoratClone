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
        Schema::create('document_signatures', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->nullable();
            $table->nullableMorphs('signable');
            $table->string('status')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->integer('role_id')->index()->nullable();
            $table->integer('user_id')->index()->nullable();
            $table->string('guid')->unique()->nullable()->comment('ozimiz generatsiya qilamiz create uchun');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_signatures');
    }
};
