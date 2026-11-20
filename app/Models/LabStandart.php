<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Laboratoriya standartlari (armatura normalari) - ma'lumotnoma.
 */
class LabStandart extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'd_mm' => 'integer',
        'ot_mm' => 'integer',
        'ov_mm' => 'integer',
        'b5_percent' => 'integer',
        'br_percent' => 'integer',
        'status' => 'boolean',
    ];

    protected $hidden = ['deleted_at'];
}
