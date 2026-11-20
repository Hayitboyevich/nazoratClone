<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabChecklistAnswer extends Model
{
    use HasFactory, softDeletes;

    protected $guarded = [];

    protected static array $allRelations = [
        'objectInfo', 'workTypeInfo', 'questionInfo', 'objectType', 'blockInfo'
    ];


    public function scopeWithAll($query)
    {
        return $query->with(self::$allRelations);
    }

    public function objectInfo()
    {
        return $this->belongsTo(Article::class, 'object_id', 'id');
    }

    public function workTypeInfo()
    {
        return $this->belongsTo(WorkType::class, 'work_type_id', 'id');
    }

    public function questionInfo()
    {
        return $this->belongsTo(Question::class, 'question_id', 'id');
    }

    public function objectType()
    {
        return $this->belongsTo(ObjectType::class, 'object_type_id', 'id');
    }

    public function blockInfo()
    {
        return $this->belongsTo(Block::class, 'block_id', 'id');
    }
}
