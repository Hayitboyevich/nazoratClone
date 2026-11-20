<?php

namespace App\Models;

use App\Enums\LabCertificateDeviationMeaningEnum;
use App\Enums\LabCertificateDocTypeEnum;
use App\Enums\LabCertificateStatusEnum;
use App\Enums\LabCertificateValueFormEnum;
use App\Enums\LabInstrumentTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * O'lchash vositasining kalibrlash / qiyoslash sertifikati.
 * Tasdiqlangan sertifikat o'zgartirilmaydi va o'chirilmaydi - o'tgan bayonnomalar unga tayanadi.
 */
class LabInstrumentCertificate extends Model
{
    protected $guarded = [];

    protected $casts = [
        'issued_at' => 'date:Y-m-d',
        'valid_to' => 'date:Y-m-d',
        'doc_type' => 'integer',
        'value_form' => 'integer',
        'deviation_meaning' => 'integer',
        'delta' => 'float',
        'use_correction' => 'boolean',
        'points' => 'array',
        'status' => 'integer',
        'confirmed_at' => 'datetime',
    ];

    protected $appends = ['doc_type_name', 'value_form_name', 'deviation_meaning_name', 'status_name', 'file_url', 'days_left'];

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(LabInstrument::class, 'lab_instrument_id');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by')->select('id', 'name', 'surname', 'middle_name');
    }

    public function getDocTypeNameAttribute(): ?string
    {
        return LabCertificateDocTypeEnum::tryFrom((int)$this->doc_type)?->label();
    }

    public function getValueFormNameAttribute(): ?string
    {
        return LabCertificateValueFormEnum::tryFrom((int)$this->value_form)?->label();
    }

    public function getDeviationMeaningNameAttribute(): ?string
    {
        return LabCertificateDeviationMeaningEnum::tryFrom((int)$this->deviation_meaning)?->label();
    }

    public function getStatusNameAttribute(): ?string
    {
        return LabCertificateStatusEnum::tryFrom((int)$this->status)?->label();
    }

    public function getFileUrlAttribute(): ?string
    {
        return $this->file ? url('storage/' . $this->file) : null;
    }

    /** Muddat tugashiga qolgan kun (manfiy - o'tib ketgan) */
    public function getDaysLeftAttribute(): ?int
    {
        return $this->valid_to ? (int)now()->startOfDay()->diffInDays($this->valid_to->copy()->startOfDay(), false) : null;
    }

    public function isConfirmed(): bool
    {
        return $this->status === LabCertificateStatusEnum::CONFIRMED->value;
    }

    /**
     * Saqlash / tasdiqlashdan oldingi tekshiruv: errors - saqlab bo'lmaydi, warnings - ogohlantirish.
     * $fileAttached - shu so'rovda yangi fayl kelgan (hali diskka yozilmagan).
     */
    public function validateForConfirm(LabInstrument $instrument, bool $fileAttached = false): array
    {
        $errors = [];
        $warnings = [];

        if (!$this->number) $errors[] = 'Hujjat raqami kiritilmagan';
        if (!$this->issued_at || !$this->valid_to || $this->valid_to->lte($this->issued_at)) $errors[] = "Sanalar noto'g'ri";

        if ($this->doc_type === LabCertificateDocTypeEnum::VERIFICATION->value && !$this->delta) {
            $errors[] = '±Δ kiritilmagan';
        }

        if ($this->doc_type === LabCertificateDocTypeEnum::CALIBRATION->value) {
            $points = $this->points ?? [];
            if (!count($points)) {
                $errors[] = "Kalibrlash nuqtalari yo'q";
            } else {
                $hasEmpty = collect($points)->contains(fn($p) => !isset($p['x'], $p['u'], $p['d']));
                if ($hasEmpty) $errors[] = "Nuqtalarda bo'sh qiymat bor";

                $xs = array_map(fn($p) => $p['x'] ?? null, $points);
                for ($i = 1; $i < count($xs); $i++) {
                    if ($xs[$i] <= $xs[$i - 1]) {
                        $errors[] = "Nuqtalar o'sish tartibida emas";
                        break;
                    }
                }

                if (collect($points)->contains(fn($p) => isset($p['u']) && $p['u'] <= 0)) {
                    $errors[] = "U musbat bo'lishi kerak";
                }

                $maxU = collect($points)->max(fn($p) => $p['u'] ?? 0);
                if (in_array($instrument->type, LabInstrumentTypeEnum::lengthTypes()) && $maxU > 0.5) {
                    $warnings[] = 'Uzunlik U > 0,5 mm - GOST 12004, 1.7-bandga mos emas';
                }
                if ($instrument->type === LabInstrumentTypeEnum::BALANCE->value && $maxU > 1) {
                    $warnings[] = 'Tarozi U > 1 g - d < 10 mm armatura uchun yaramaydi (1.9-band)';
                }

                // birinchi nuqta diapazonning 20% idan yuqorida bo'lsa, undan pastdagi o'lchovlar qoplanmaydi
                $firstX = $xs[0] ?? null;
                $from = (float)$instrument->range_from;
                $to = (float)$instrument->range_to;
                if ($firstX !== null && $firstX > $from + 0.2 * ($to - $from)) {
                    $warnings[] = "Birinchi nuqta ($firstX {$instrument->unit_name}) diapazon boshidan uzoq: undan pastdagi o'lchovlar uchun ogohlantirish chiqadi";
                }
            }
        }

        if (!$this->file && !$fileAttached) $errors[] = 'Sertifikat fayli biriktirilmagan';

        return [$errors, $warnings];
    }
}
