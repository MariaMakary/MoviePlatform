<?php

namespace App\Services;

use App\Integration\Events\MovieWatched;
use App\Models\WatchHistoryEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use MoviePlatform\Common\Integration\EventPublisher;

class HistoryService
{
    public function __construct(private WatchlistService $watchlist, private EventPublisher $events) {}

    public function listFor(string $userId): Builder
    {
        return WatchHistoryEntry::ownedBy($userId)->orderByDesc('watched_at')->orderByDesc('id');
    }

    public function add(string $userId, string $movieId, ?string $watchedAt): WatchHistoryEntry
    {
        $entry = DB::transaction(function () use ($userId, $movieId, $watchedAt) {
            $this->watchlist->remove($userId, $movieId);

            return WatchHistoryEntry::create([
                'user_id' => $userId,
                'movie_id' => $movieId,
                'watched_at' => $watchedAt === null ? Carbon::now() : Carbon::parse($watchedAt)->utc(),
            ]);
        });

        $this->events->publish(MovieWatched::ROUTING_KEY, MovieWatched::data($entry));

        return $entry;
    }

    public function latestWatchedAt(string $userId, array $movieIds): Collection
    {
        return WatchHistoryEntry::ownedBy($userId)
            ->whereIn('movie_id', $movieIds)
            ->selectRaw('movie_id, max(watched_at) as watched_at')
            ->groupBy('movie_id')
            ->get();
    }
}
