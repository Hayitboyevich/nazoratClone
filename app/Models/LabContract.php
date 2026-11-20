<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabContract extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    const EXIST = 1;
    const NOT_EXIST = 2;

    public function contractInfo()
    {
        return $this->hasMany(LabContractInfo::class, 'lab_contract_id');
    }

    public function getSignedConPathAttribute($value): ?string
    {
        return $value ? asset('storage/' . $value) : null;
    }

    public function blocks()
    {
        return $this->hasMany(LabBlock::class, 'lab_contract_id');
    }

    public function complication(): HasOne
    {
        return $this->hasOne(LabComplication::class, 'lab_contract_id');
    }
}
