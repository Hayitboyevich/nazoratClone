<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class FoundationBlock extends Model
{
    use HasFactory;

    protected $guarded = false;

    public function images(): MorphMany
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }

    public function object(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'object_id');
    }

    public function regulation(): BelongsTo
    {
        return $this->belongsTo(Regulation::class);
    }

    public function monitoring(): BelongsTo
    {
        return $this->belongsTo(Monitoring::class);
    }
}
