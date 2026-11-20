<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QualityControlMaterial extends Model
{
    use HasFactory;

    protected $guarded =false;

    public function controls(): HasMany
    {
        return $this->hasMany(QualityControl::class, 'material_id');
    }
}
