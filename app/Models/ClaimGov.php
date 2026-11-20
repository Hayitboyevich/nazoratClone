<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ClaimGov extends Model
{
    use HasFactory;

    protected $guarded = false;

    public function  object(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'object_id');
    }
    public function signatures(): MorphMany
    {
        return $this->morphMany(DocumentSignature::class, 'signable');
    }


    public function monitoring(): HasOne
    {
        return $this->hasOne(ClaimGovMonitoring::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ClaimGovHistory::class, 'gu_id', 'task_id');
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function sphere(): BelongsTo
    {
        return $this->belongsTo(ClaimSphere::class, 'claim_sphere_id');
    }

    public function declaration(): BelongsTo
    {
        return $this->belongsTo(Declaration::class, 'declaration_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ClaimGovOrganizationReview::class);
    }

    public function getBlocksAttribute()
    {
        if ($this->monitoring()->first() != null) {
            $blockArray = json_decode($this->monitoring()->first()->blocks, true);
            $blocks = Block::with(['type', 'mode'])->whereIn('id', $blockArray)->get();
            $blockResponseArr = [];
            foreach ($blocks as $block) {
                $countChecklist = $block->getClaimChecklistCount($this->id);
                $blockArr = $block->toArray();
                $blockArr['is_filled'] = $countChecklist == 7;
                $blockResponseArr[] = $blockArr;
            }

            return $blockResponseArr;
        } else return null;
    }
}
