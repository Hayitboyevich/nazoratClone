<?php

namespace App\Enums;

/**
 * Sinov (bayonnoma) natijasi - barcha sinov turlari uchun umumiy. Hisobni frontend qiladi, natijani o'zi yuboradi.
 */
enum LabTestResultEnum: int
{
    case POSITIVE = 1; // Ijobiy: namunalar talablarga muvofiq
    case NEGATIVE = 2; // Salbiy: muvofiq emas yoki natija ishonchsiz

    public function label(): string
    {
        return match ($this) {
            self::POSITIVE => 'Ijobiy',
            self::NEGATIVE => 'Salbiy',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
