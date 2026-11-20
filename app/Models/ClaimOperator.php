<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClaimOperator extends Model
{
    use HasFactory;

    protected $guarded = false;

    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class);
    }
}
