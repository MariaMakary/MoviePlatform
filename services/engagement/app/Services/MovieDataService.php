<?php

namespace App\Services;

use App\Models\ListItem;
use App\Models\Rating;
use App\Models\Review;
use App\Models\WatchHistoryEntry;
use App\Models\WatchlistItem;

class MovieDataService
{
    public function deleteAll(string $movieId): void
    {
        Rating::where('movie_id', $movieId)->delete();
        Review::where('movie_id', $movieId)->delete();
        WatchlistItem::where('movie_id', $movieId)->delete();
        WatchHistoryEntry::where('movie_id', $movieId)->delete();
        ListItem::where('movie_id', $movieId)->delete();
    }
}
