<?php

namespace App\Models;

use App\Enums\OgohAppealStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OgohAppeal extends Model
{
    protected $guarded = false;

    protected $casts = [
        'files' => 'array',
        'result_files' => 'array',
        'payload' => 'array',
        'appeal_created_at' => 'datetime',
        'answered_at' => 'datetime',
    ];

    public function isFinal(): bool
    {
        return in_array((int)$this->status, OgohAppealStatusEnum::finals(), true);
    }

    public function events(): HasMany
    {
        return $this->hasMany(OgohAppealEvent::class)->orderBy('id');
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function answeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by');
    }
}
