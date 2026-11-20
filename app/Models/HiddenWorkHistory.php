<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HiddenWorkHistory extends Model
{
    protected $table = 'hidden_work_histories';

    protected $guarded = false;

    protected $casts = [
        'content' => 'object'
    ];
}
