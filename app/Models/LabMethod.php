<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Laboratoriya sinov usuli (ma'lumotnoma).
 */
class LabMethod extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'sort' => 'integer',
        'status' => 'boolean',
    ];

    protected $hidden = ['deleted_at', 'pivot'];

    /** Usul ichidagi sinovlar (ko'rsatkichlar) va ularning standartlari */
    public function indicators(): HasMany
    {
        return $this->hasMany(LabMethodIndicator::class)->where('status', true)->orderBy('sort')->orderBy('id');
    }

    /** Shu sinov turida ishlatiladigan asboblar (metrolog reyestri) */
    public function instruments(): BelongsToMany
    {
        return $this->belongsToMany(LabInstrument::class, 'lab_instrument_methods');
    }

    public function tests(): HasMany
    {
        return $this->hasMany(LabTest::class);
    }
}
