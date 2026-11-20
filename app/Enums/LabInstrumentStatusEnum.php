<?php

namespace App\Enums;

/**
 * O'lchash vositasi (asbob) holati - metrolog o'zgartiradi.
 */
enum LabInstrumentStatusEnum: int
{
    case WORKING = 1;         // Ishlayapti
    case CALIBRATION = 2;     // Kalibrlashda
    case REPAIR = 3;          // Ta'mirda
    case DECOMMISSIONED = 4;  // Hisobdan chiqarilgan

    public function label(): string
    {
        return match ($this) {
            self::WORKING => 'Ishlayapti',
            self::CALIBRATION => 'Kalibrlashda',
            self::REPAIR => "Ta'mirda",
            self::DECOMMISSIONED => 'Hisobdan chiqarilgan',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
