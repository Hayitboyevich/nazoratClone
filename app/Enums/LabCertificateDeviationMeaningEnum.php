<?php

namespace App\Enums;

/**
 * Kalibrlash sertifikatidagi «og'ish» ustunining ma'nosi.
 */
enum LabCertificateDeviationMeaningEnum: int
{
    case CORRECTION = 1; // tuzatma - ko'rsatishga qo'shiladi
    case DEVIATION = 2;  // ko'rsatish - etalon

    public function label(): string
    {
        return match ($this) {
            self::CORRECTION => "tuzatma (ko'rsatishga qo'shiladi)",
            self::DEVIATION => "ko'rsatish − etalon",
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
