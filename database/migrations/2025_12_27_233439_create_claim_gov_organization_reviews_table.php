<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('claim_gov_organization_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('object_id')->nullable()->index()->constrained('articles')->nullOnDelete();
            $table->foreignId('claim_gov_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->foreignId('monitoring_id')->nullable()->index()->constrained('claim_gov_monitorings')->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->index()->constrained('roles')->nullOnDelete();
            $table->text('answer')->nullable();
            $table->boolean('status')->nullable();
            $table->timestamp('deadline')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('claim_gov_organization_reviews');
    }
};
