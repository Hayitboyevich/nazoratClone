<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabMeasurementInstrument extends Model
{
    use HasFactory, softDeletes;
    protected $guarded = [];
}
