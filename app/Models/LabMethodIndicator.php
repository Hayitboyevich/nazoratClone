<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Usul ichidagi sinov (ko'rsatkich) va uning standarti, masalan «Oqish chegarasi σт - ГОСТ 12004 п.3.6».
 */
class LabMethodIndicator extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'sort' => 'integer',
        'price' => 'integer',
        'test_type' => 'integer',
        'is_available' => 'boolean',
        'status' => 'boolean',
    ];

    protected $hidden = ['deleted_at', 'created_at', 'updated_at'];

    public function method(): BelongsTo
    {
        return $this->belongsTo(LabMethod::class, 'lab_method_id');
    }
}
