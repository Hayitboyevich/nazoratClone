<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabQuestion extends Model
{
    use HasFactory, SoftDeletes;
    protected $guarded = [];

    public function workType()
    {
        return $this->belongsTo(WorkType::class, 'work_type_id');
    }

    public function contractInfo()
    {
        return $this->hasMany(LabContractInfo::class, 'question_id');
    }
}
