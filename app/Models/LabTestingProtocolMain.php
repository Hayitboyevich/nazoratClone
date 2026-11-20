<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabTestingProtocolMain extends Model
{
    use HasFactory, softDeletes;
    protected $guarded = [];

    protected $casts = [
        'homogeneity' => 'array',
        'equipment' => 'array'
    ];

    public function constructions()
    {
        return $this->hasMany(LabProtocolConstruction::class, 'lab_protocol_id');
    }
}
