<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabCheckListChosenTestingType extends Model
{
    use HasFactory, softDeletes;

    protected $guarded = [];

    public function labCheckListTYpe()
    {
        return $this->belongsTo(LabCheckListTestingType::class, 'lab_check_list_testing_type_id');
    }

    public function conditions()
    {
        return $this->hasMany(LabCheckListTestingCondition::class, 'lab_check_list_chosen_testing_type_id')
            ->orderBy('id');
    }

    public function condition()
    {
        return $this->hasOne(LabCheckListTestingCondition::class, 'lab_check_list_chosen_testing_type_id');
    }
    public function initialCreatedCondition()
    {
        return $this->hasOne(LabCheckListTestingCondition::class, 'lab_check_list_chosen_testing_type_id');
    }

    public function statement()
    {
        return $this->hasOne(LabCheckListTestingStatement::class, 'lab_check_list_chosen_testing_type_id');
    }

    public function statements()
    {
        return $this->hasMany(LabCheckListTestingStatement::class, 'lab_check_list_chosen_testing_type_id');
    }
}
