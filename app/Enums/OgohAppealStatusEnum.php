<?php

namespace App\Enums;

/**
 * Kodlar "Ogoh fuqaro" tizimidagi statuslar bilan bir xil (callback da shu kodlar yuboriladi).
 */
enum OgohAppealStatusEnum: int
{
    case ACCEPTED = 1;   // Qabul qilindi / ko'rib chiqilmoqda
    case RESOLVED = 2;   // Hal qilindi
    case REJECTED = 3;   // Rad etildi
    case EXPLAINED = 5;  // Tushuntirish berildi

    public static function finals(): array
    {
        return [self::RESOLVED->value, self::REJECTED->value, self::EXPLAINED->value];
    }

    public function label(): string
    {
        return match ($this) {
            self::ACCEPTED => "Ko'rib chiqilmoqda",
            self::RESOLVED => 'Hal qilindi',
            self::REJECTED => 'Rad etildi',
            self::EXPLAINED => 'Tushuntirish berildi',
        };
    }
}
