<?php

namespace App\Services;

use App\Enums\CreditRole;
use App\Integration\Clients\TmdbClient;
use App\Models\Genre;
use App\Models\Movie;
use App\Models\Person;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MovieSyncService
{
    public const CAST_LIMIT = 15;

    public const MOVIES_PER_PAGE = 20;

    public function __construct(private TmdbClient $tmdb)
    {
    }

    public function sync(int $tmdbId): ?Movie
    {
        $data = $this->tmdb->movie($tmdbId);

        if ($data === null) {
            return null;
        }

        return DB::transaction(function () use ($data) {
            $movie = Movie::firstOrNew(['tmdb_id' => $data['movie']['tmdb_id']]);
            $edited = $movie->exists && $movie->is_edited;

            if (! $edited) {
                $movie->fill($data['movie']);
            }

            $movie->synced_at = Carbon::now();
            $movie->save();

            if (! $edited) {
                $movie->genres()->sync(array_map(fn (array $genre) => $this->saveGenre($genre)->id, $data['genres']));
            }

            $this->saveCredits($movie, $data);

            return $movie;
        });
    }

    public function syncGenres(): int
    {
        $genres = $this->tmdb->genres();

        DB::transaction(fn () => array_map(fn (array $genre) => $this->saveGenre($genre), $genres));

        return count($genres);
    }

    public function syncPopular(int $pages, ?callable $onMovie = null): int
    {
        $synced = 0;

        for ($page = 1; $page <= $pages; $page++) {
            $list = $this->tmdb->popular($page);

            foreach ($list['results'] as $result) {
                if ($this->sync($result['movie']['tmdb_id'])) {
                    $synced++;
                }

                if ($onMovie) {
                    $onMovie();
                }
            }

            if ($page >= $list['total_pages']) {
                break;
            }
        }

        return $synced;
    }

    private function saveGenre(array $attributes): Genre
    {
        $genre = Genre::where('tmdb_id', $attributes['tmdb_id'])->first()
            ?? Genre::whereNull('tmdb_id')->where('name', $attributes['name'])->first()
            ?? new Genre();

        $genre->fill($attributes)->save();

        return $genre;
    }

    private function savePerson(array $attributes): Person
    {
        return Person::updateOrCreate(['tmdb_id' => $attributes['tmdb_id']], $attributes);
    }

    private function saveCredits(Movie $movie, array $data): void
    {
        $credits = collect($data['cast'])
            ->take(self::CAST_LIMIT)
            ->map(fn (array $credit) => $this->creditRow($movie, $credit['person'], CreditRole::Cast, $credit['character'], $credit['cast_order']))
            ->concat(collect($data['directors'])
                ->map(fn (array $credit) => $this->creditRow($movie, $credit['person'], CreditRole::Director)))
            ->unique(fn (array $row) => $row['person_id'].'|'.$row['role'])
            ->values();

        if ($credits->isNotEmpty()) {
            DB::table('movie_person')->upsert($credits->all(), ['movie_id', 'person_id', 'role'], ['character', 'cast_order']);
        }

        $kept = $credits->map(fn (array $row) => $row['person_id'].'|'.$row['role']);

        $gone = DB::table('movie_person')
            ->where('movie_id', $movie->id)
            ->get(['id', 'person_id', 'role'])
            ->reject(fn ($row) => $kept->contains($row->person_id.'|'.$row->role))
            ->pluck('id');

        if ($gone->isNotEmpty()) {
            DB::table('movie_person')->whereIn('id', $gone)->delete();
        }
    }

    private function creditRow(Movie $movie, array $person, CreditRole $role, ?string $character = null, ?int $castOrder = null): array
    {
        return [
            'id' => (string) Str::orderedUuid(),
            'movie_id' => $movie->id,
            'person_id' => $this->savePerson($person)->id,
            'role' => $role->value,
            'character' => $character,
            'cast_order' => $castOrder,
        ];
    }
}
