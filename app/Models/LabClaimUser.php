<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabClaimUser extends Model
{
    use HasFactory, SoftDeletes;
    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function toArray()
    {
        $array = parent::toArray();
        if ($this->relationLoaded('user') && $this->relationLoaded('role')) {
            $user = $this->user->toArray();
            $user['role'] = $this->role->toArray();
            $array['user'] = $user;
            unset($array['role']);
        }
        return $array;
    }
}
