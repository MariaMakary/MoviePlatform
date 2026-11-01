<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** One row per follow. The primary key is (follower_id, following_id), so rows are changed through queries only. */
class Follow extends Model
{
    public const UPDATED_AT = null;

    public $incrementing = false;

    protected $primaryKey = null;

    protected $fillable = ['follower_id', 'following_id', 'created_at'];

    public function scopeFollowersOf(Builder $query, string $userId): Builder
    {
        return $query->where('following_id', $userId);
    }

    public function scopeFollowedBy(Builder $query, string $userId): Builder
    {
        return $query->where('follower_id', $userId);
    }
}
