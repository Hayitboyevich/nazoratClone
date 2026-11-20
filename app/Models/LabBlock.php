<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabBlock extends Model
{
    use HasFactory, softDeletes;
    protected $guarded = [];

    public function contractInfo()
    {
        return $this->hasMany(LabContractInfo::class, 'lab_block_id');
    }

    public function blockType()
    {
        return $this->belongsTo(BlockMode::class, 'block_mode_id');
    }
}
