<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeclarationBlock extends Model
{
    use HasFactory;

    protected $guarded = false;

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }
}
