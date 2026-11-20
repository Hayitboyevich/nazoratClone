<?php

namespace App\Enums;

/**
 * Asbobning muddat holati - bazada saqlanmaydi, sertifikatlardan hisoblanadi.
 * Ro'yxat filtri (?state=) va kartochkalar uchun.
 * CALIBRATION va REPAIR faqat filtr: asbob holati (status) bo'yicha tanlaydi, expiry_state sifatida qaytmaydi.
 */
enum LabInstrumentStateEnum: int
{
    case ACTIVE = 1;          // Amalda
    case EXPIRING = 2;        // 30 kun ichida tugaydi
    case EXPIRED = 3;         // Muddati o'tgan (filtrda sertifikati yo'qlar ham)
    case NO_CERTIFICATE = 4;  // Tasdiqlangan sertifikat yo'q
    // 5 (avvalgi «Tasdiq kutilmoqda») olib tashlangan - qolgan id'lar o'zgarmagan
    case CALIBRATION = 6;     // Kalibrlashda
    case REPAIR = 7;          // Ta'mirda
    case DECOMMISSIONED = 8;  // Hisobdan chiqarilgan

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Amalda',
            self::EXPIRING => '30 kun ichida',
            self::EXPIRED => "Muddati o'tgan",
            self::NO_CERTIFICATE => "Sertifikat yo'q",
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
