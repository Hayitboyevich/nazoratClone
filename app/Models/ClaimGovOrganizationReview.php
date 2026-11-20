<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ClaimGovOrganizationReview extends Model
{
    use HasFactory;

    protected $guarded = false;

    public function monitoring(): BelongsTo
    {
        return $this->belongsTo(ClaimGovMonitoring::class, 'monitoring_id');
    }

    public function signatures(): MorphMany
    {
        return $this->morphMany(DocumentSignature::class, 'signable');
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(ClaimGov::class, 'claim_gov_id');
    }

    public function object(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'object_id');
    }

    public function getAnswerAttribute()
    {
        $answers = ($this->attributes['answer']) ? json_decode(gzuncompress(base64_decode($this->attributes['answer'])), true) : null;
        if ($answers != null) {
            foreach ($answers as $key => $value) {
                if (str_contains($key, 'notes'))
                    $answers['notes'] = $value;
            }
        }

        return ($answers != null) ? $answers['notes'] : null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'organization_id');
    }

}
