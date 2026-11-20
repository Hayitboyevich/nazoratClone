<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabProtocolConstruction extends Model
{
    use HasFactory, softDeletes;
    protected $guarded = [];

    public function measurements()
    {
        return $this->hasMany(LabProtocolConstructionIndex::class, 'lab_protocol_construction_id');
    }
}
