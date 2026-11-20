<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ogoh fuqaroga yuboriladigan callback (outbox). Yuborilmasa ogoh:send-events qayta urinadi.
 */
class OgohAppealEvent extends Model
{
    const SEND_PENDING = 1;
    const SEND_SENT = 2;
    const SEND_FAILED = 3;

    protected $guarded = false;

    protected $casts = [
        'result_files' => 'array',
        'response' => 'array',
        'occurred_at' => 'datetime',
        'next_attempt_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function appeal(): BelongsTo
    {
        return $this->belongsTo(OgohAppeal::class, 'ogoh_appeal_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
