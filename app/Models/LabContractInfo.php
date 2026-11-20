<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabContractInfo extends Model
{
    use HasFactory, softDeletes;
    protected $guarded = [];


    public function testCategory()
    {
        return $this->belongsTo(LabTestCategory::class, 'lab_test_category_id');
    }

    public function workType()
    {
        return $this->belongsTo(WorkType::class, 'work_type_id');
    }

    public function question()
    {
        return $this->belongsTo(LabQuestion::class, 'question_id');
    }

    public function labBlock()
    {
        return $this->belongsTo(LabBlock::class, 'lab_block_id');
    }
}
