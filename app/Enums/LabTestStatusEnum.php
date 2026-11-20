<?php

namespace App\Enums;

/**
 * Laboratoriya sinovi holati.
 * Laborant: IN_PROGRESS -> PROTOCOL_FORMED (bayonnoma shakllantirildi, tasdiq kutilmoqda).
 * Rahbar (inspeksiya boshlig'i / hududiy laboratoriya boshlig'i): PROTOCOL_FORMED -> CONFIRMED | RETURNED | REJECTED.
 * Laborant: RETURNED -> PROTOCOL_FORMED (tahrirlab, bayonnomani qayta shakllantiradi).
 */
enum LabTestStatusEnum: int
{
    case IN_PROGRESS = 1;      // Sinov o'tkazilmoqda
    case PROTOCOL_FORMED = 2;  // Bayonnoma shakllantirildi, tasdiq kutilmoqda
    case RETURNED = 3;         // Tahrirlashga qaytarildi
    case CONFIRMED = 4;        // Tasdiqlangan
    case REJECTED = 5;         // Rad etilgan

    public function label(): string
    {
        return match ($this) {
            self::IN_PROGRESS => 'Jarayonda',
            self::PROTOCOL_FORMED => 'Tasdiq kutilmoqda',
            self::RETURNED => 'Tahrirlashga qaytarilgan',
            self::CONFIRMED => 'Tasdiqlangan',
            self::REJECTED => 'Rad etilgan',
        };
    }

    /**
     * Shu holatdan o'tish mumkin bo'lgan holatlar.
     *
     * @return self[]
     */
    public function nextStatuses(): array
    {
        return match ($this) {
            self::IN_PROGRESS, self::RETURNED => [self::PROTOCOL_FORMED],
            self::PROTOCOL_FORMED => [self::CONFIRMED, self::RETURNED, self::REJECTED],
            self::CONFIRMED, self::REJECTED => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->nextStatuses(), true);
    }

    /** Laborant ma'lumotlarni tahrirlashi mumkin bo'lgan holatlar */
    public function isEditable(): bool
    {
        return in_array($this, [self::IN_PROGRESS, self::RETURNED], true);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
