<?php

namespace App\Enums;

/**
 * O'lchash vositasi turlari.
 */
enum LabInstrumentTypeEnum: int
{
    case BALANCE = 1;           // Tarozi
    case RULER = 2;             // Metall chizg'ich
    case TAPE = 3;              // Ruletka
    case CALIPER = 4;           // Shtangensirkul
    case DEPTH = 5;             // Shtangenglubinomer
    case INDICATOR = 6;         // Indikator
    case MICROMETER = 7;        // Mikrometr
    case PRESS = 8;             // Press (sinov mashinasi)
    case EXTENSOMETER = 9;      // Ekstenzometr
    case THERMOHYGROMETER = 10; // Termogigrometr
    case STOPWATCH = 11;        // Sekundomer

    public function label(): string
    {
        return match ($this) {
            self::BALANCE => 'Tarozi',
            self::RULER => "Metall chizg'ich",
            self::TAPE => 'Ruletka',
            self::CALIPER => 'Shtangensirkul',
            self::DEPTH => 'Shtangenglubinomer',
            self::INDICATOR => 'Indikator',
            self::MICROMETER => 'Mikrometr',
            self::PRESS => 'Press',
            self::EXTENSOMETER => 'Ekstenzometr',
            self::THERMOHYGROMETER => 'Termogigrometr',
            self::STOPWATCH => 'Sekundomer',
        };
    }

    /** Uzunlik o'lchash vositalari (GOST 12004, 1.7-band: U <= 0,5 mm) */
    public static function lengthTypes(): array
    {
        return [self::RULER->value, self::TAPE->value];
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
