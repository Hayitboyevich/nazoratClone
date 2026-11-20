<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ConcreteWork extends Model
{
    use HasFactory;

    protected $guarded = false;

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function images(): MorphMany
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    public function object(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'object_id');
    }

    public function workType(): BelongsTo
    {
        return $this->belongsTo(WorkType::class, 'work_type_id');
    }
}
