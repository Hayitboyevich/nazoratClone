<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabCheckListTestingMainInfo extends Model
{
    use HasFactory, softDeletes;

    protected $guarded = [];

    public function testingQuestion()
    {
        return $this->hasMany(LabCheckListTestingQuestion::class, 'lab_check_list_testing_main_info_id')->orderBy('id');
    }

    public function belongedObject()
    {
        return $this->belongsTo(Article::class, 'article_id', 'id');
    }

    public function workTypeQuestion()
    {
        return $this->belongsTo(Question::class, 'question_id', 'id');
    }
}
