<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Holiday extends Model
{
    use HasFactory;

    protected $guarded = false;

    public function signatures(): MorphMany
    {
        return $this->morphMany(DocumentSignature::class, 'signable');
    }
}
