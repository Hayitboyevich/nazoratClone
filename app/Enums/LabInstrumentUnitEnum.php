<?php

namespace App\Enums;

/**
 * O'lchash vositasining o'lchov birligi.
 */
enum LabInstrumentUnitEnum: int
{
    case GRAM = 1;        // g
    case KILOGRAM = 2;    // kg
    case MILLIMETER = 3;  // mm
    case METER = 4;       // m
    case NEWTON = 5;      // N
    case KILONEWTON = 6;  // kN
    case CELSIUS = 7;     // °C
    case PERCENT = 8;     // %
    case SECOND = 9;      // s

    public function label(): string
    {
        return match ($this) {
            self::GRAM => 'g',
            self::KILOGRAM => 'kg',
            self::MILLIMETER => 'mm',
            self::METER => 'm',
            self::NEWTON => 'N',
            self::KILONEWTON => 'kN',
            self::CELSIUS => '°C',
            self::PERCENT => '%',
            self::SECOND => 's',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
