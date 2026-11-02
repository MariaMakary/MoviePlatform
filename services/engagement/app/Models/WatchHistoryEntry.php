<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class WatchHistoryEntry extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'watch_history';

    protected $fillable = ['user_id', 'movie_id', 'watched_at'];

    protected $casts = [
        'watched_at' => 'datetime',
    ];

    public function scopeOwnedBy(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
