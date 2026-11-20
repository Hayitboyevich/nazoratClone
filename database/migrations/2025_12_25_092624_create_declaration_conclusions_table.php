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
        Schema::create('declaration_conclusions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('object_id')->nullable()->index()->constrained('articles')->nullOnDelete();
            $table->foreignId('declaration_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->foreignId('role_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->string('order_number')->nullable();
            $table->string('order_date')->nullable();
            $table->string('contract_number')->nullable();
            $table->string('contract_date')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('declaration_conclusions');
    }
};
