<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** One of a user's best movies to watch next, with its score (0–1) and why it was picked. */
class Recommendation extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'movie_id', 'score', 'reason'];

    protected $casts = [
        'score' => 'float',
    ];

    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
