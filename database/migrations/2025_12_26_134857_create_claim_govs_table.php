<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('claim_govs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('object_id')->nullable()->index()->constrained('articles');
            $table->foreignId('declaration_id')->nullable()->index()->constrained('declarations');
            $table->foreignId('region_id')->nullable()->index()->constrained('regions');
            $table->foreignId('claim_sphere_id')->nullable()->index()->constrained('claim_spheres');
            $table->foreignId('district_id')->nullable()->index()->constrained('districts');
            $table->text('address')->nullable();
            $table->integer('status')->nullable();
            $table->bigInteger('task_id')->nullable()->index();
            $table->string('user_type')->nullable();
            $table->string('legal_tin')->nullable();
            $table->string('legal_name')->nullable();
            $table->string('legal_phone')->nullable();
            $table->string('property_owner')->nullable();
            $table->string('topography')->nullable();
            $table->string('information_organizations_responsible')->nullable();
            $table->string('equipment_acceptance_report')->nullable();
            $table->string('object_task_id')->nullable();
            $table->text('consumer_name')->nullable()->comment('foydalanuvchi nomi');
            $table->string('consumer_inn')->nullable()->comment('foydalanuvchi inn');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('claim_govs');
    }
};
