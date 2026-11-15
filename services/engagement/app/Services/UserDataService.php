<?php

namespace App\Services;

use App\Models\MovieList;
use App\Models\Rating;
use App\Models\Review;
use App\Models\WatchHistoryEntry;
use App\Models\WatchlistItem;

class UserDataService
{
    public function setAuthorBlocked(string $userId, bool $blocked): void
    {
        Review::where('user_id', $userId)->toBase()->update(['author_blocked' => $blocked]);
        MovieList::where('user_id', $userId)->toBase()->update(['author_blocked' => $blocked]);
    }

    public function deleteAll(string $userId): void
    {
        Rating::where('user_id', $userId)->delete();
        Review::where('user_id', $userId)->delete();
        WatchlistItem::where('user_id', $userId)->delete();
        WatchHistoryEntry::where('user_id', $userId)->delete();
        MovieList::where('user_id', $userId)->delete();
    }
}
