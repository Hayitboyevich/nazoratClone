<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ariza bloki. data - laborant kiritgan erkin tuzilishdagi ma'lumotlar:
 * {gps, fund: {tur, B, V, S, L}, floors: [{kol, rig, pl, arm, wall, chok}]}.
 */
class LabTestContractBlock extends Model
{
    protected $guarded = [];

    protected $casts = [
        'floors_count' => 'integer',
        'data' => 'array',
        'sort' => 'integer',
    ];

    protected $appends = ['is_inherited'];

    /** Qo'shimcha shartnomaga oldingi shartnomalardan ko'chirilgan blok - tahrirlanmaydi */
    public function getIsInheritedAttribute(): bool
    {
        return $this->parent_block_id !== null;
    }

    /** Asl blok id si: ko'chirilgan blokda - manbasi, aks holda o'zi */
    public function rootId(): int
    {
        return (int)($this->parent_block_id ?? $this->id);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(LabTestContract::class, 'lab_test_contract_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(LabTestContractItem::class)->orderBy('work_type_id')->orderBy('id');
    }
}
