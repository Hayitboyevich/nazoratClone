<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sertifikatda «Tasdiq kutilmoqda» (1) holati olib tashlandi: sertifikat darhol tasdiqlangan (2) holatda yaratiladi.
 * Mavjud 1-holatdagilar 2 ga o'tkaziladi (confirmed_by / confirmed_at bo'sh bo'lsa - yaratgan user va yaratilgan vaqt).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('UPDATE lab_instrument_certificates
            SET status = 2,
                confirmed_by = COALESCE(confirmed_by, created_by),
                confirmed_at = COALESCE(confirmed_at, created_at, NOW())
            WHERE status = 1');

        DB::statement('ALTER TABLE lab_instrument_certificates ALTER COLUMN status SET DEFAULT 2');
    }

    public function down(): void
    {
        // qaysi yozuvlar 1 bo'lgani saqlanmaydi - faqat standart qiymat qaytariladi
        DB::statement('ALTER TABLE lab_instrument_certificates ALTER COLUMN status SET DEFAULT 1');
    }
};
