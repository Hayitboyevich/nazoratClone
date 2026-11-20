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
        Schema::create('user_infos', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('surname')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('pin')->index()->nullable();
            $table->string('company_inn')->index()->nullable();
            $table->string('company_name')->nullable();
            $table->string('start_date')->nullable();
            $table->string('position')->nullable();
            $table->integer('specialist_id')->index()->nullable();
            $table->string('specialist_name')->index()->nullable();
            $table->string('certificate_number')->nullable();
            $table->boolean('mehnat_integration')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_infos');
    }
};
