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
        Schema::create('hidden_works_acts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('object_id')->nullable()->index()->constrained('articles')->nullOnDelete();
            $table->foreignId('block_id')->nullable()->index()->constrained('blocks')->nullOnDelete();
            $table->tinyInteger('status')->default(1);
            $table->integer('organization_id')->nullable()->index();
            $table->integer('vendor_id')->nullable()->index();
            $table->integer('designer_id')->nullable()->index();
            $table->string('address')->nullable();
            $table->date('presentation_date')->nullable();
            $table->integer('internal_id')->nullable()->index();
            $table->integer('technic_id')->nullable()->index();
            $table->integer('author_id')->nullable()->index();
            $table->text('desc')->nullable();
            $table->string('project_name')->nullable();
            $table->string('drawing_number')->nullable();
            $table->date('drawing_date')->nullable();
            $table->text('materials')->nullable();
            $table->string('certificate_info')->nullable();
            $table->boolean('has_project_deviation')->nullable();
            $table->string('project_drawing_number')->nullable();
            $table->date('project_drawing_date')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('comment')->nullable();
            $table->text('post_permit_works')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hidden_works_acts');
    }
};
