<?php

namespace App\Services;

use App\Integration\Clients\TmdbClient;
use App\Models\Genre;
use App\Models\Movie;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MovieService
{
    public const MAX_PAGE = 500;

    public const PER_PAGE = 20;

    public function __construct(private TmdbClient $tmdb) {}

    public function findMany(array $ids): Collection
    {
        $movies = Movie::with('genres')->whereIn('id', $ids)->get()->keyBy('id');

        return collect($ids)
            ->map(fn (string $id) => $movies->get($id))
            ->filter()
            ->values();
    }

    public function popular(int $page): array
    {
        return $this->fromTmdbList($this->tmdb->popular($page), $page);
    }

    public function upcoming(int $page): array
    {
        $list = $this->tmdb->upcoming($page);
        $today = Carbon::today('UTC')->toDateString();

        $list['results'] = array_values(array_filter(
            $list['results'],
            fn (array $result) => ($result['movie']['release_date'] ?? null) !== null && $result['movie']['release_date'] > $today,
        ));

        return $this->fromTmdbList($list, $page);
    }

    private function fromTmdbList(array $list, int $page): array
    {
        $lastPage = max(1, min($list['total_pages'], self::MAX_PAGE));

        return [
            'movies' => $this->ensureLocal($list['results']),
            'current_page' => $page,
            'last_page' => $lastPage,
            'total' => min($list['total_results'], $lastPage * self::PER_PAGE),
        ];
    }

    private function ensureLocal(array $results): Collection
    {
        $tmdbIds = array_map(fn (array $result) => $result['movie']['tmdb_id'], $results);
        $movies = Movie::whereIn('tmdb_id', $tmdbIds)->get()->keyBy('tmdb_id');

        foreach ($results as $result) {
            $tmdbId = $result['movie']['tmdb_id'];

            if (! $movies->has($tmdbId)) {
                $movies->put($tmdbId, $this->createFromList($result));
            }
        }

        return (new EloquentCollection(
            collect($tmdbIds)->unique()->map(fn (int $tmdbId) => $movies->get($tmdbId))->values()->all()
        ))->load('genres');
    }

    private function createFromList(array $result): Movie
    {
        try {
            return DB::transaction(function () use ($result) {
                $movie = Movie::create($result['movie']);
                $movie->genres()->sync(Genre::whereIn('tmdb_id', $result['genre_tmdb_ids'])->pluck('id'));

                return $movie;
            });
        } catch (UniqueConstraintViolationException) {
            return Movie::where('tmdb_id', $result['movie']['tmdb_id'])->firstOrFail();
        }
    }
}
