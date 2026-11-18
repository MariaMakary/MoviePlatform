<?php

namespace App\Services;

use App\Integration\Events\MovieDeleted;
use App\Models\Movie;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use MoviePlatform\Common\Integration\EventPublisher;

class AdminMovieService
{
    public const PER_PAGE = 20;

    public function __construct(private MovieSyncService $sync, private EventPublisher $events) {}

    public function list(?string $query, int $page): LengthAwarePaginator
    {
        $query = trim((string) $query);

        return Movie::with('genres')
            ->when($query !== '', fn ($movies) => $movies->where('title', 'ilike', '%'.addcslashes($query, '%_\\').'%'))
            ->orderBy('title')
            ->orderBy('id')
            ->paginate(self::PER_PAGE, ['*'], 'page', $page);
    }

    public function update(string $id, array $data): Movie
    {
        return DB::transaction(function () use ($id, $data) {
            $movie = $this->find($id);

            $movie->fill([
                'title' => trim($data['title']),
                'overview' => $data['overview'],
                'poster_path' => $data['poster_path'],
                'release_date' => $data['release_date'],
                'runtime' => $data['runtime'],
                'is_edited' => true,
            ])->save();

            $movie->genres()->sync(array_map('strtolower', $data['genre_ids']));

            return $movie->load('genres');
        });
    }

    public function delete(string $id): void
    {
        DB::transaction(fn () => $this->find($id)->delete());

        $this->events->publish(MovieDeleted::ROUTING_KEY, MovieDeleted::data(strtolower($id)));
    }

    public function import(int $tmdbId): Movie
    {
        if (Movie::where('tmdb_id', $tmdbId)->exists()) {
            throw ValidationException::withMessages(['tmdb_id' => 'This movie is already in the catalog.']);
        }

        $movie = $this->sync->sync($tmdbId);

        if ($movie === null) {
            throw ValidationException::withMessages(['tmdb_id' => 'TMDB has no movie with this id.']);
        }

        return $movie->load('genres');
    }

    private function find(string $id): Movie
    {
        $movie = Movie::find(strtolower($id));

        if ($movie === null) {
            abort(404, 'Movie not found.');
        }

        return $movie;
    }
}
