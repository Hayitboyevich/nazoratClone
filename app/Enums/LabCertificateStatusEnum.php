<?php

namespace App\Enums;

/**
 * Sertifikat holati. Sertifikat darhol CONFIRMED holatda yaratiladi; xato kiritilgani REJECTED (bekor) qilinadi.
 * Laborant hisoblarida faqat CONFIRMED sertifikatlar ishlatiladi.
 * 1 (avvalgi «Tasdiq kutilmoqda») olib tashlangan - id'lar frontend uchun o'zgarmagan.
 */
enum LabCertificateStatusEnum: int
{
    case CONFIRMED = 2;  // Tasdiqlangan
    case REJECTED = 3;   // Rad etilgan / bekor qilingan

    public function label(): string
    {
        return match ($this) {
            self::CONFIRMED => 'Tasdiqlangan',
            self::REJECTED => 'Rad etilgan',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
