<?php

namespace App\Models;

use App\Enums\LabCertificateStatusEnum;
use App\Enums\LabInstrumentStateEnum;
use App\Enums\LabInstrumentStatusEnum;
use App\Enums\LabInstrumentTypeEnum;
use App\Enums\LabInstrumentUnitEnum;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * O'lchash vositasi (metrolog reyestri).
 */
class LabInstrument extends Model
{
    use SoftDeletes;

    /** Muddat tugashiga necha kun qolganda ogohlantiriladi */
    public const EXPIRY_WARN_DAYS = 30;

    protected $guarded = [];

    protected $casts = [
        'range_from' => 'float',
        'range_to' => 'float',
        'resolution' => 'float',
        'type' => 'integer',
        'unit' => 'integer',
        'status' => 'integer',
    ];

    protected $appends = ['type_name', 'unit_name', 'status_name'];

    public function certificates(): HasMany
    {
        return $this->hasMany(LabInstrumentCertificate::class)->orderByDesc('issued_at')->orderByDesc('id');
    }

    /** Asbob ishlatiladigan sinov turlari - sinovda faqat o'sha turga bog'langan asbob tanlanadi */
    public function methods(): BelongsToMany
    {
        return $this->belongsToMany(LabMethod::class, 'lab_instrument_methods')
            ->select('lab_methods.id', 'lab_methods.code', 'lab_methods.name')
            ->orderBy('lab_methods.sort')->orderBy('lab_methods.id');
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->select('id', 'name', 'surname', 'middle_name');
    }

    public function getTypeNameAttribute(): ?string
    {
        return LabInstrumentTypeEnum::tryFrom((int)$this->type)?->label();
    }

    public function getUnitNameAttribute(): ?string
    {
        return LabInstrumentUnitEnum::tryFrom((int)$this->unit)?->label();
    }

    public function getStatusNameAttribute(): ?string
    {
        return LabInstrumentStatusEnum::tryFrom((int)$this->status)?->label();
    }

    /**
     * Berilgan sanada amalda bo'lgan, tasdiqlangan sertifikat - eng oxirgi berilgani yangisi sifatida eskisini almashtiradi.
     */
    public function certificateOn($date = null): ?LabInstrumentCertificate
    {
        $date = Carbon::parse($date ?? now())->toDateString();

        return $this->certificates
            ->filter(fn($c) => $c->status === LabCertificateStatusEnum::CONFIRMED->value
                && $c->issued_at->toDateString() <= $date
                && $date <= $c->valid_to->toDateString())
            ->sortByDesc(fn($c) => $c->issued_at->toDateString() . sprintf('%012d', $c->id))
            ->first();
    }

    /**
     * Berilgan sanagacha boshlangan (issued_at <= sana) tasdiqlangan sertifikatlardan muddati eng uzoq tugaydigani.
     * Kelajakdan boshlanadigan sertifikat hisobga olinmaydi.
     */
    public function lastConfirmedCertificate($date = null): ?LabInstrumentCertificate
    {
        $date = Carbon::parse($date ?? now())->toDateString();

        return $this->certificates
            ->filter(fn($c) => $c->status === LabCertificateStatusEnum::CONFIRMED->value
                && $c->issued_at->toDateString() <= $date)
            ->sortByDesc(fn($c) => $c->valid_to->toDateString() . $c->issued_at->toDateString() . sprintf('%012d', $c->id))
            ->first();
    }

    /**
     * «Joriy hujjat»: sanada amaldagi sertifikat, bo'lmasa oxirgi tugagani. Muddat holati ham shunga qarab hisoblanadi.
     */
    public function currentCertificate($date = null): ?LabInstrumentCertificate
    {
        return $this->certificateOn($date) ?? $this->lastConfirmedCertificate($date);
    }

    /**
     * Muddat holati (LabInstrumentStateEnum): ACTIVE | EXPIRING | EXPIRED | NO_CERTIFICATE | DECOMMISSIONED
     */
    public function expiryState($date = null): array
    {
        $date = Carbon::parse($date ?? now())->startOfDay();
        $state = fn(LabInstrumentStateEnum $s, array $extra = []) => array_merge(['id' => $s->value, 'name' => $s->label()], $extra);

        if ($this->status === LabInstrumentStatusEnum::DECOMMISSIONED->value) {
            return $state(LabInstrumentStateEnum::DECOMMISSIONED);
        }

        $confirmed = $this->currentCertificate($date);

        if (!$confirmed) {
            return $state(LabInstrumentStateEnum::NO_CERTIFICATE);
        }

        $days = (int)$date->diffInDays($confirmed->valid_to->copy()->startOfDay(), false);
        $extra = ['days' => $days, 'valid_to' => $confirmed->valid_to->toDateString()];

        if ($days < 0) {
            return $state(LabInstrumentStateEnum::EXPIRED, $extra);
        }
        if ($days <= self::EXPIRY_WARN_DAYS) {
            return array_merge($state(LabInstrumentStateEnum::EXPIRING, $extra), ['name' => "$days kun qoldi"]);
        }
        return $state(LabInstrumentStateEnum::ACTIVE, $extra);
    }

    /**
     * Sinovda ishlatsa bo'ladigan asboblar: holati «Ishlayapti» va berilgan sanada amaldagi tasdiqlangan sertifikati bor.
     */
    public function scopeUsable(Builder $query, $date = null): Builder
    {
        $date = Carbon::parse($date ?? now())->toDateString();

        return $query
            ->where('status', LabInstrumentStatusEnum::WORKING->value)
            ->whereHas('certificates', fn($q) => $q
                ->where('status', LabCertificateStatusEnum::CONFIRMED->value)
                ->whereDate('issued_at', '<=', $date)
                ->whereDate('valid_to', '>=', $date));
    }

    /**
     * Ro'yxatni muddat holati bo'yicha filtrlash (kartochkalar uchun).
     */
    public function scopeWhereExpiryState(Builder $query, $state): Builder
    {
        $state = LabInstrumentStateEnum::tryFrom((int)$state);
        if (!$state) return $query;

        $confirmed = LabCertificateStatusEnum::CONFIRMED->value;
        $today = now()->toDateString();
        $warnDate = now()->addDays(self::EXPIRY_WARN_DAYS)->toDateString();
        $own = "from lab_instrument_certificates c where c.lab_instrument_id = lab_instruments.id and c.status = $confirmed and c.issued_at <= '$today'";
        // expiryState() bilan bir xil: «joriy hujjat»ning valid_to si (amaldagi eng yangisi, bo'lmasa oxirgi tugagani)
        $currentValidTo = "coalesce("
            . "(select c.valid_to $own and c.valid_to >= '$today' order by c.issued_at desc, c.id desc limit 1), "
            . "(select max(c.valid_to) $own))";

        // asbob holati bo'yicha kartochkalar
        $byStatus = match ($state) {
            LabInstrumentStateEnum::CALIBRATION => LabInstrumentStatusEnum::CALIBRATION,
            LabInstrumentStateEnum::REPAIR => LabInstrumentStatusEnum::REPAIR,
            LabInstrumentStateEnum::DECOMMISSIONED => LabInstrumentStatusEnum::DECOMMISSIONED,
            default => null,
        };
        if ($byStatus) {
            return $query->where('status', $byStatus->value);
        }

        $query->where('status', '!=', LabInstrumentStatusEnum::DECOMMISSIONED->value);
        $noCertificate = "($currentValidTo is null)";

        return match ($state) {
            LabInstrumentStateEnum::ACTIVE => $query->whereRaw("$currentValidTo > ?", [$warnDate]),
            LabInstrumentStateEnum::EXPIRING => $query->whereRaw("$currentValidTo between ? and ?", [$today, $warnDate]),
            // muddati o'tgan yoki tasdiqlangan sertifikati yo'q
            LabInstrumentStateEnum::EXPIRED => $query->where(fn($q) => $q->whereRaw("$currentValidTo < ?", [$today])->orWhereRaw($noCertificate)),
            LabInstrumentStateEnum::NO_CERTIFICATE => $query->whereRaw($noCertificate),
            default => $query,
        };
    }
}
