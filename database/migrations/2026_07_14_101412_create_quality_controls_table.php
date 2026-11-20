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
        Schema::create('quality_controls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('object_id')->nullable()->index()->constrained('articles')->nullOnDelete();
            $table->foreignId('block_id')->nullable()->index()->constrained('blocks')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->foreignId('role_id')->nullable()->index()->constrained('roles')->nullOnDelete();
            $table->date('received_date')->nullable();
            $table->string('inn')->nullable();
            $table->text('organization_name')->nullable();
            $table->string('contract_number')->nullable();
            $table->string('quantity')->nullable();
            $table->string('non_compliant_quantity')->nullable();
            $table->text('comment')->nullable();
            $table->text('corrective_action')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quality_controls');
    }
};
