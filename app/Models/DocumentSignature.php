<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DocumentSignature extends Model
{
    use HasFactory;

    protected $guarded = false;

    public function signable(): MorphTo
    {
        return $this->morphTo();
    }
}
