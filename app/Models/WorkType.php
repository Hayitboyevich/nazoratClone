<?php

namespace App\Models;

use App\Enums\ObjectTypeEnum;
use App\Enums\QuestionTypeEnum;
use App\Models\Traits\LabTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkType extends Model
{
    use HasFactory , LabTrait;

    protected $guarded = false;

    protected $casts = [
        'object_type_id' => ObjectTypeEnum::class,
    ];

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'work_type_id');
    }

    public function LabQuestions()
    {
        return $this->hasMany(LabQuestion::class, 'work_type_id');
    }
}
