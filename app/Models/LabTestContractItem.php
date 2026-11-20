<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Shartnoma ilovasi qatori: blok + ish turi (bosqich) + usul + tanlangan sinovlar + miqdor va summa.
 * Ko'p qavatli ish turida miqdor har bir qavat uchun alohida (floor_quantities), quantity - ularning yig'indisi.
 */
class LabTestContractItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'indicator_ids' => 'array',
        'floor_quantities' => 'array',
        'floor_indicators' => 'array',
        'floor_unit_prices' => 'array',
        'proposed_floor_quantities' => 'array',
        'quantity' => 'integer',
        'proposed_quantity' => 'integer',
        'multiplier' => 'integer',
        'unit_price' => 'integer',
        'total_price' => 'integer',
        'total_vat' => 'integer',
        'total_with_vat' => 'integer',
    ];

    // employee_id va multiplier - eski oqimdan qolgan, ishlatilmaydi (mas'ul laborant endi arizaning o'zida)
    protected $hidden = ['employee_id', 'multiplier'];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(LabTestContract::class, 'lab_test_contract_id');
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(LabTestContractBlock::class, 'lab_test_contract_block_id');
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(LabMethod::class, 'lab_method_id')->select('id', 'code', 'name', 'unit');
    }

    public function workType(): BelongsTo
    {
        return $this->belongsTo(WorkType::class)->select('id', 'name', 'is_multiple_floor');
    }

    public function tests(): HasMany
    {
        return $this->hasMany(LabTest::class, 'lab_contract_item_id');
    }

    /**
     * Shu qavatda (qavatsiz ishda - ishning o'zida) shartnomada tanlangan sinovlar.
     * Qavatlar bo'yicha tanlov saqlanmagan (eski) yozuvda barcha qavatlar uchun indicator_ids.
     */
    public function indicatorIdsFor(?int $floor): array
    {
        if ($floor !== null && is_array($this->floor_indicators)) {
            return array_map('intval', $this->floor_indicators[(string)$floor] ?? $this->floor_indicators[$floor] ?? []);
        }

        return array_map('intval', $this->indicator_ids ?? []);
    }

    /** Shu qavatda bitta birlik narxi (tanlangan sinovlar narxi yig'indisi) */
    public function unitPriceFor(?int $floor): int
    {
        if ($floor !== null && is_array($this->floor_unit_prices)) {
            return (int)($this->floor_unit_prices[(string)$floor] ?? $this->floor_unit_prices[$floor] ?? 0);
        }

        return (int)$this->unit_price;
    }

    /** Qator summasi: qavatli ishda har qavatning miqdori o'z narxiga ko'paytiriladi */
    public function calculateTotal(): int
    {
        if ($this->floor_quantities === null) {
            return (int)$this->quantity * (int)$this->unit_price;
        }
        $total = 0;
        foreach ($this->floor_quantities as $floor => $quantity) {
            $total += (int)$quantity * $this->unitPriceFor((int)$floor);
        }

        return $total;
    }

    /** Shu qavat uchun shartnomadagi miqdor (0 - bu qavatda ish yo'q) */
    public function floorQuantity(int $floor): int
    {
        return (int)($this->floor_quantities[(string)$floor] ?? $this->floor_quantities[$floor] ?? 0);
    }
}
