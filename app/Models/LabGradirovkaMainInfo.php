<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabGradirovkaMainInfo extends Model
{
    use HasFactory, softDeletes;
    protected $guarded = [];

    public function info()
    {
        return $this->hasMany(LabGradirovkaInfo::class, 'lab_gradirovka_main_info_id');
    }

    public function coefficient()
    {
        return $this->hasOne(LabGradirovkaCoefficient::class, 'lab_gradirovka_main_info_id');
    }

    public function testActionInfo()
    {
        return $this->hasMany(LabTestActionInfo::class, 'lab_gradirovka_main_info_id');
    }
}
