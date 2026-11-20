<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabCheckListTestingQuestionMark extends Model
{
    use HasFactory, softDeletes;
    protected $guarded = [];

    public function markAverage()
    {
        return $this->hasOne(LabCheckListTestingQuestionMark::class, 'mark_id', 'id')->where('mark_number', 10);
    }

    public function marksChild()
    {
        return $this->hasMany(LabCheckListTestingQuestionMark::class, 'mark_id', 'id')->orderBy('mark_number');
    }
}
