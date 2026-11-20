<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sinov statusi o'zgarishlari va tahrirlar tarixi.
 * from_status = to_status bo'lsa - status o'zgarmagan, faqat ma'lumot tahrirlangan.
 * old_data - o'zgarishdan oldingi input_data / result_data (tahrirda eski ma'lumot yo'qolmasligi uchun).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_test_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_test_id')->index()->constrained('lab_tests');
            $table->tinyInteger('from_status')->nullable(); // null - sinov yaratilgan
            $table->tinyInteger('to_status');
            $table->jsonb('old_data')->nullable();
            $table->text('comment')->nullable(); // rad etish / tahrirga qaytarish sababi
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->integer('role_id')->nullable(); // amal bajarilgan paytdagi rol (UserRoleEnum)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_test_status_histories');
    }
};
