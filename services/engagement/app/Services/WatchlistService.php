<?php

namespace App\Services;

use App\Models\WatchlistItem;
use Illuminate\Database\Eloquent\Builder;

class WatchlistService
{
    public function listFor(string $userId): Builder
    {
        return WatchlistItem::ownedBy($userId)->latest()->orderByDesc('id');
    }

    // createOrFirst relies on the unique key, so two clicks at the same time still give one row.
    public function add(string $userId, string $movieId): WatchlistItem
    {
        return WatchlistItem::createOrFirst(['user_id' => $userId, 'movie_id' => $movieId]);
    }

    public function remove(string $userId, string $movieId): void
    {
        WatchlistItem::ownedBy($userId)->where('movie_id', $movieId)->delete();
    }

    public function movieIdsIn(string $userId, array $movieIds): array
    {
        return WatchlistItem::ownedBy($userId)->whereIn('movie_id', $movieIds)->pluck('movie_id')->all();
    }
}
