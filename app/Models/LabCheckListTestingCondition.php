<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabCheckListTestingCondition extends Model
{
    use HasFactory, softDeletes;

    protected $guarded = [];


    const SCHMIDT = 1;
    const ONIX = 2;
    public function mainInfos()
    {
        return $this->hasMany(LabCheckListTestingMainInfo::class, 'lab_check_list_testing_condition_id');
    }
}
