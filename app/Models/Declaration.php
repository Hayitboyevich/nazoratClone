<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Declaration extends Model
{
    use HasFactory;

    protected $guarded = false;
    protected $casts = [
        'done_work' => 'array',
    ];


    public function factType(): BelongsTo
    {
        return $this->belongsTo(FactType::class);
    }

    public function signatures(): MorphMany
    {
        return $this->morphMany(DocumentSignature::class, 'signable');
    }


    public function histories(): HasMany
    {
        return $this->hasMany(DeclarationHistory::class, 'gu_id');
    }

    public function object(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'object_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function doneUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'done_unit_id');
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(DeclarationBlock::class);
    }
}
