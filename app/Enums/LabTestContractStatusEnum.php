<?php

namespace App\Enums;

/**
 * Laboratoriya arizasi / shartnomasi holati («Laboratoriya kabinetlari» oqimi).
 * LABORANT -> ACCOUNTANT -> DIRECTOR -> CUSTOMER -> CONFIRMED.
 * ACCOUNTANT / DIRECTOR -> LABORANT (qaytarish, return_data to'ldiriladi); CUSTOMER -> CANCELLED.
 */
enum LabTestContractStatusEnum: int
{
    case LABORANT = 1;   // Laborantda: blok, qavat va ishlar kiritilmoqda
    case ACCOUNTANT = 2; // Buxgalterda
    case DIRECTOR = 3;   // Inspeksiya boshlig'ida
    case CUSTOMER = 4;   // Buyurtmachi tasdig'ida
    case CONFIRMED = 5;  // Tasdiqlangan
    case CANCELLED = 6;  // Bekor qilingan

    public function label(): string
    {
        return match ($this) {
            self::LABORANT => 'Laborantda',
            self::ACCOUNTANT => 'Buxgalterda',
            self::DIRECTOR => "Inspeksiya boshlig'ida",
            self::CUSTOMER => "Buyurtmachi tasdig'ida",
            self::CONFIRMED => 'Tasdiqlangan',
            self::CANCELLED => 'Bekor qilingan',
        };
    }

    /**
     * @return self[]
     */
    public function nextStatuses(): array
    {
        return match ($this) {
            self::LABORANT => [self::ACCOUNTANT],
            self::ACCOUNTANT => [self::DIRECTOR, self::LABORANT],
            self::DIRECTOR => [self::CUSTOMER, self::LABORANT],
            self::CUSTOMER => [self::CONFIRMED, self::CANCELLED],
            self::CONFIRMED, self::CANCELLED => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->nextStatuses(), true);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
