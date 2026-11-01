<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class WatchlistItem extends Model
{
    use HasUuids;

    protected $table = 'watchlist';

    protected $fillable = ['user_id', 'movie_id'];

    public function scopeOwnedBy(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
