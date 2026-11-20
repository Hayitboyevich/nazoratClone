<?php

namespace App\Enums;

enum LabCertificateDocTypeEnum: int
{
    case CALIBRATION = 1;   // Kalibrlash sertifikati (nuqtalar bilan)
    case VERIFICATION = 2;  // Qiyoslash guvohnomasi (±Δ bilan)

    public function label(): string
    {
        return match ($this) {
            self::CALIBRATION => 'Kalibrlash',
            self::VERIFICATION => 'Qiyoslash',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
