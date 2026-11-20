<?php

namespace App\Models;

use App\Enums\LabTestStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sinov statusi o'zgarishi / tahriri tarixi. old_data - o'zgarishdan oldingi input_data va result_data.
 */
class LabTestStatusHistory extends Model
{
    protected $guarded = [];

    protected $casts = [
        'from_status' => 'integer',
        'to_status' => 'integer',
        'old_data' => 'array',
        'role_id' => 'integer',
    ];

    protected $appends = ['from_status_name', 'to_status_name'];

    public function test(): BelongsTo
    {
        return $this->belongsTo(LabTest::class, 'lab_test_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->select('id', 'name', 'surname', 'middle_name');
    }

    public function getFromStatusNameAttribute(): ?string
    {
        return $this->from_status === null ? null : LabTestStatusEnum::tryFrom($this->from_status)?->label();
    }

    public function getToStatusNameAttribute(): ?string
    {
        return LabTestStatusEnum::tryFrom((int)$this->to_status)?->label();
    }
}
