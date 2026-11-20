<?php

namespace App\Enums;

/**
 * Sertifikatdagi qiymatlar ko'rinishi (U, og'ish, ±Δ).
 */
enum LabCertificateValueFormEnum: int
{
    case ABSOLUTE = 1; // absolyut, asbob birligida
    case PERCENT = 2;  // % ko'rsatishdan

    public function label(): string
    {
        return match ($this) {
            self::ABSOLUTE => 'absolyut',
            self::PERCENT => "% ko'rsatishdan",
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
