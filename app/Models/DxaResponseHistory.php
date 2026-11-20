<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DxaResponseHistory extends Model
{
    use HasFactory;

    protected $table = 'dxa_response_histories';

    protected $guarded = false;

    protected $casts = [
        'content' => 'object'
    ];
}
