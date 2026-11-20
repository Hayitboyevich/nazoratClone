<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabCheckListTestingQuestion extends Model
{
    use HasFactory, softDeletes;

    protected $guarded = [];

    public function marks()
    {
        return $this->hasMany(LabCheckListTestingQuestionMark::class, 'lab_check_list_testing_question_id')->orderBy('mark_number');
    }
}
