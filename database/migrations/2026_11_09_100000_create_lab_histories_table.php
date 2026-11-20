<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laboratoriya modullari uchun umumiy tarix jadvali (HistoryService('lab_histories')).
 * gu_id - yozuv id si, type - LogType::LAB_* (qaysi obyekt va qanday amal ekanini bildiradi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_histories', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('gu_id');
            $table->integer('type');
            $table->jsonb('content');
            $table->timestamps();

            $table->index(['type', 'gu_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_histories');
    }
};
