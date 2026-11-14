<?php

namespace App\Services;

use App\Integration\Clients\TmdbClient;
use App\Integration\Clients\TmdbUnavailable;
use App\Models\Genre;
use App\Models\Movie;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MovieService
{
    public const MAX_PAGE = 500;

    public const PER_PAGE = 20;

    public const RESYNC_AFTER_DAYS = 7;

    public const SIMILAR_LIMIT = 12;

    /** Search sorts, always descending; the first one is the default. */
    public const SEARCH_SORTS = ['popularity', 'vote_average', 'release_date'];

    /** Below this many local matches, a text search also asks TMDB. */
    private const ENOUGH_LOCAL_RESULTS = 10;

    public function __construct(private TmdbClient $tmdb, private MovieSyncService $sync) {}

    /**
     * A movie with everything its page shows. A TMDB movie that was never synced (created from a list) or whose sync
     * is older than RESYNC_AFTER_DAYS is synced first; if TMDB fails, the local copy is served.
     */
    public function find(string $id): Movie
    {
        $movie = Movie::findOrFail($id);

        if ($this->needsSync($movie)) {
            try {
                $movie = $this->sync->sync($movie->tmdb_id) ?? $movie;
            } catch (TmdbUnavailable) {
                Log::info('Serving the local copy of a movie, TMDB is unavailable', ['movie_id' => $movie->id]);
            }
        }

        return $movie->load(['genres', 'directors', 'cast']);
    }

    public function findMany(array $ids): Collection
    {
        $movies = Movie::with('genres')->whereIn('id', $ids)->get()->keyBy('id');

        return collect($ids)
            ->map(fn (string $id) => $movies->get($id))
            ->filter()
            ->values();
    }

    /**
     * Up to SIMILAR_LIMIT movies TMDB finds similar to $id, as local movies (created from the list data when new).
     * A movie without a tmdb_id (added by an admin) has none. Used by the public and the internal similar endpoints.
     */
    public function similar(string $id): Collection
    {
        $movie = Movie::findOrFail($id);

        if ($movie->tmdb_id === null) {
            return new Collection();
        }

        $results = array_filter($this->tmdb->similar($movie->tmdb_id)['results'], fn (array $result) => $result['movie']['tmdb_id'] !== $movie->tmdb_id);

        return $this->ensureLocal(array_slice(array_values($results), 0, self::SIMILAR_LIMIT));
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

    /**
     * Local movies matching every given filter (q, genre, year), sorted descending. A text search with few local
     * matches first imports TMDB's first page of results, created from the list data like popular and upcoming.
     */
    public function search(array $filters, int $page): LengthAwarePaginator
    {
        $filters['q'] = trim($filters['q'] ?? '');

        if ($filters['q'] !== '' && $this->matching($filters)->count() < self::ENOUGH_LOCAL_RESULTS) {
            try {
                $this->ensureLocal($this->tmdb->search($filters['q'])['results']);
            } catch (TmdbUnavailable) {
                Log::info('Searching local movies only, TMDB is unavailable');
            }
        }

        $sort = $filters['sort'] ?? self::SEARCH_SORTS[0];

        return $this->matching($filters)
            ->with('genres')
            ->orderByRaw($this->sortColumn($sort).' DESC NULLS LAST')
            ->orderBy('id')
            ->paginate(self::PER_PAGE, ['*'], 'page', $page);
    }

    public function discover(array $genreNames, array $excludeIds, int $limit): Collection
    {
        return Movie::with('genres')
            ->whereHas('genres', fn (Builder $genres) => $genres->whereIn('genres.name', $genreNames))
            ->when($excludeIds !== [], fn (Builder $query) => $query->whereNotIn('id', $excludeIds))
            ->orderByDesc('popularity')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    private function matching(array $filters): Builder
    {
        return Movie::query()
            ->when($filters['q'] ?? '', fn (Builder $query, string $text) => $query->where('title', 'ILIKE', '%'.$this->escapeLike($text).'%'))
            ->when($filters['genre'] ?? null, fn (Builder $query, string $genreId) => $query->whereHas('genres', fn (Builder $genres) => $genres->where('genres.id', $genreId)))
            ->when($filters['year'] ?? null, fn (Builder $query, $year) => $query->whereBetween('release_date', ["$year-01-01", "$year-12-31"]));
    }

    // Only the allowed columns ever reach the SQL.
    private function sortColumn(string $sort): string
    {
        return match ($sort) {
            'vote_average' => 'vote_average',
            'release_date' => 'release_date',
            default => 'popularity',
        };
    }

    // A typed % or _ is searched as itself, not as a LIKE wildcard.
    private function escapeLike(string $text): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $text);
    }

    private function needsSync(Movie $movie): bool
    {
        return $movie->tmdb_id !== null
            && ($movie->synced_at === null || $movie->synced_at->lt(Carbon::now()->subDays(self::RESYNC_AFTER_DAYS)));
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

    /**
     * The local movies for TMDB list results (`['movie' => […], 'genre_tmdb_ids' => […]]`), in the same order.
     * A movie seen for the first time is created from the list data, without an extra TMDB call.
     */
    public function ensureLocal(array $results): Collection
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
