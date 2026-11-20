<?php

namespace App\Enums;

/**
 * Sinov usuli - ariza yaratishda tanlanadi. LABORATORY da usullarning barcha sinovlari,
 * qolganlarida faqat shu turga tegishli sinovlar (lab_method_indicators.test_type) tanlanadi.
 */
enum LabTestTypeEnum: int
{
    case LABORATORY = 1;    // Laboratoriya: to'liq GOST ro'yxati
    case INSTRUMENTAL = 2;  // Instrumental-texnik tekshiruv
    case VISUAL = 3;        // Vizual tekshiruv

    public function label(): string
    {
        return match ($this) {
            self::LABORATORY => 'Laboratoriya',
            self::INSTRUMENTAL => 'Instrumental-texnik tekshiruv',
            self::VISUAL => 'Vizual tekshiruv',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
