<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ogoh_appeals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('appeal_id')->unique(); // ogohfuqarodagi murojaat raqami
            $table->string('integration')->nullable();
            $table->integer('sphere_id')->nullable();
            $table->string('sphere_name')->nullable();
            $table->integer('sphere_parent_id')->nullable();
            $table->string('sphere_parent_name')->nullable();
            $table->text('content')->nullable();
            $table->string('full_name')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('gender')->nullable();
            $table->date('birth_date')->nullable();
            $table->foreignId('region_id')->nullable()->index()->constrained('regions');
            $table->foreignId('district_id')->nullable()->index()->constrained('districts');
            $table->string('province_name')->nullable();
            $table->string('region_name')->nullable();
            $table->string('mahalla_name')->nullable();
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->jsonb('files')->nullable();
            $table->string('callback_url')->nullable();
            $table->timestamp('appeal_created_at')->nullable();
            $table->tinyInteger('status')->default(1)->index();
            $table->text('comment')->nullable();
            $table->jsonb('result_files')->nullable();
            $table->foreignId('answered_by')->nullable()->constrained('users');
            $table->timestamp('answered_at')->nullable();
            $table->jsonb('payload')->nullable();
            $table->timestamps();
        });

        Schema::create('ogoh_appeal_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ogoh_appeal_id')->index()->constrained('ogoh_appeals')->cascadeOnDelete();
            $table->uuid('event_id')->unique();
            $table->tinyInteger('status')->nullable(); // null - faqat xabar
            $table->text('comment')->nullable();
            $table->jsonb('result_files')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->timestamp('occurred_at');
            $table->tinyInteger('send_status')->default(1)->index(); // 1 pending, 2 sent, 3 failed
            $table->integer('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->integer('http_status')->nullable();
            $table->jsonb('response')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ogoh_appeal_events');
        Schema::dropIfExists('ogoh_appeals');
    }
};
