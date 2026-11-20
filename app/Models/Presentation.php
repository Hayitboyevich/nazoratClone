<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Presentation extends Model
{
    use HasFactory;

    protected $guarded = false;

    public function images(): MorphMany
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    public function object(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'object_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(PresentationHistory::class, 'gu_id');
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspection_id');
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }
    public function technic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technic_id');
    }
    public function internal(): BelongsTo
    {
        return $this->belongsTo(User::class, 'internal_id');
    }
}
