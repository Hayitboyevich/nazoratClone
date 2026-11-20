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
        Schema::table('claim_gov_organization_reviews', function (Blueprint $table) {
            $table->foreignId('claim_sphere_id')->index()->nullable()->constrained('claim_spheres');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('claim_gov_organizations', function (Blueprint $table) {
            //
        });
    }
};
