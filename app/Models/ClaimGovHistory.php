<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClaimGovHistory extends Model
{
    use HasFactory;

    protected $casts = [
        'content' => 'object'
    ];

    protected $guarded = false;
}
