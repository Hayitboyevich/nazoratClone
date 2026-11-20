<?php

namespace App\Models;

use App\Scopes\LabClaimUserScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabClaim extends Model
{

    const ALL_STATUSES = 0;
    const STATUS_NEW = 1;
    const STATUS_IN_LAB = 2;
    const STATUS_IN_ACCOUNTANT = 3;
    const STATUS_IN_INSPEKSIYA = 4;
    const STATUS_IN_CUSTOMER = 5;
    const STATUS_COMPLETED = 6;
    const STATUS_CANCELLED = 7;
    const STATUS_REJECTED = 8;
    const STATUS_RETURNED = 9;
    const STATUS_IN_PROCESS = 10;
    const STATUS_IN_LAB_CONTRACT_ACTION = 11;


    const TYPE_SEND = 1;
    const TYPE_DECLINE = 2;

    //HISTORY TYPE
    const PRICE_SET = 'Price set to lab test';
    const LAB_ACTION = 'Lab claim status updated';
    const LAB_CLAIM_CREATED = 'Lab claim created';

    use HasFactory, SoftDeletes;
    protected $guarded = [];
    protected $casts = [
        'project_estimate_file_path' => 'array',
    ];


    protected static function booted()
    {
        static::addGlobalScope(new LabClaimUserScope());
    }
    public function getProjectEstimateFileUrlsAttribute(): array
    {
        if (!$this->project_estimate_file_path) return [];

        return array_map(function ($path) {
            return asset('storage/' . $path);
        }, $this->project_estimate_file_path);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function labTestingOrg()
    {
        return $this->belongsTo(LabTestingOrg::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function sphere()
    {
        return $this->belongsTo(Sphere::class);
    }

    public function labTestCategory()
    {
        return $this->belongsTo(LabTestCategory::class);
    }

    public function objectType()
    {
        return $this->belongsTo(ObjectType::class);
    }

    public function labClaimUsers()
    {
        return $this->hasMany(LabClaimUser::class, 'lab_claim_id');
    }

    public function contract()
    {
        return $this->hasOne(LabContract::class, 'lab_claim_id');
    }

    public function actions()
    {
        return $this->hasMany(LabUserActionHistory::class, 'lab_claim_id');
    }
}
