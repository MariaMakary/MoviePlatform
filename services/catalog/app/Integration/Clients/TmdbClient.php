<?php

namespace App\Integration\Clients;

use App\Enums\CreditRole;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TmdbClient
{
    public const CAST_LIMIT = 20;

    public function popular(int $page = 1): array
    {
        return $this->movieList('/movie/popular', ['page' => $page]);
    }

    public function upcoming(int $page = 1): array
    {
        return $this->movieList('/movie/upcoming', ['page' => $page]);
    }

    public function search(string $query, int $page = 1): array
    {
        return $this->movieList('/search/movie', ['query' => $query, 'page' => $page, 'include_adult' => 'false']);
    }

    public function discover(?int $genreTmdbId = null, ?int $year = null, int $page = 1): array
    {
        return $this->movieList('/discover/movie', array_filter([
            'with_genres' => $genreTmdbId,
            'primary_release_year' => $year,
            'sort_by' => 'popularity.desc',
            'include_adult' => 'false',
            'page' => $page,
        ], fn ($value) => $value !== null));
    }

    public function movie(int $tmdbId): ?array
    {
        $data = $this->get("/movie/{$tmdbId}", ['append_to_response' => 'credits,videos'], nullOn404: true);

        if ($data === null) {
            return null;
        }

        $crew = collect($data['credits']['crew'] ?? []);

        return [
            'movie' => $this->movieAttributes($data) + ['trailer_key' => $this->trailerKey($data['videos']['results'] ?? [])],
            'genres' => array_map(fn (array $genre) => $this->genreAttributes($genre), $data['genres'] ?? []),
            'cast' => collect($data['credits']['cast'] ?? [])
                ->sortBy('order')
                ->take(self::CAST_LIMIT)
                ->map(fn (array $person) => [
                    'person' => $this->personAttributes($person),
                    'character' => $this->nullIfEmpty($person['character'] ?? null),
                    'cast_order' => $person['order'] ?? null,
                ])
                ->values()
                ->all(),
            'directors' => $crew->where('job', 'Director')
                ->unique('id')
                ->map(fn (array $person) => ['person' => $this->personAttributes($person)])
                ->values()
                ->all(),
        ];
    }

    public function person(int $tmdbId): ?array
    {
        $data = $this->get("/person/{$tmdbId}", ['append_to_response' => 'combined_credits'], nullOn404: true);

        if ($data === null) {
            return null;
        }

        $credits = $data['combined_credits'] ?? [];
        $movies = collect($credits['cast'] ?? [])
            ->where('media_type', 'movie')
            ->map(fn (array $movie) => [
                'movie' => $this->movieAttributes($movie),
                'genre_tmdb_ids' => $movie['genre_ids'] ?? [],
                'role' => CreditRole::Cast,
                'character' => $this->nullIfEmpty($movie['character'] ?? null),
            ])
            ->concat(collect($credits['crew'] ?? [])
                ->where('media_type', 'movie')
                ->where('job', 'Director')
                ->map(fn (array $movie) => [
                    'movie' => $this->movieAttributes($movie),
                    'genre_tmdb_ids' => $movie['genre_ids'] ?? [],
                    'role' => CreditRole::Director,
                    'character' => null,
                ]))
            ->unique(fn (array $credit) => $credit['movie']['tmdb_id'].'-'.$credit['role']->value)
            ->values()
            ->all();

        return [
            'person' => $this->personAttributes($data) + [
                'biography' => $this->nullIfEmpty($data['biography'] ?? null),
                'birthday' => $this->nullIfEmpty($data['birthday'] ?? null),
            ],
            'movies' => $movies,
        ];
    }

    public function genres(): array
    {
        $data = $this->get('/genre/movie/list');

        return array_map(fn (array $genre) => $this->genreAttributes($genre), $data['genres'] ?? []);
    }

    private function movieList(string $path, array $query): array
    {
        $data = $this->get($path, $query);

        return [
            'results' => array_map(fn (array $movie) => [
                'movie' => $this->movieAttributes($movie),
                'genre_tmdb_ids' => $movie['genre_ids'] ?? [],
            ], $data['results'] ?? []),
            'page' => $data['page'] ?? 1,
            'total_pages' => $data['total_pages'] ?? 1,
        ];
    }

    private function movieAttributes(array $movie): array
    {
        return [
            'tmdb_id' => $movie['id'],
            'title' => $movie['title'] ?? $movie['original_title'] ?? '',
            'overview' => $this->nullIfEmpty($movie['overview'] ?? null),
            'poster_path' => $movie['poster_path'] ?? null,
            'backdrop_path' => $movie['backdrop_path'] ?? null,
            'release_date' => $this->nullIfEmpty($movie['release_date'] ?? null),
            'runtime' => $movie['runtime'] ?? null,
            'vote_average' => isset($movie['vote_average']) ? round($movie['vote_average'], 1) : null,
            'popularity' => $movie['popularity'] ?? 0,
        ];
    }

    private function personAttributes(array $person): array
    {
        return [
            'tmdb_id' => $person['id'],
            'name' => $person['name'],
            'profile_path' => $person['profile_path'] ?? null,
            'known_for' => $this->nullIfEmpty($person['known_for_department'] ?? null),
        ];
    }

    private function genreAttributes(array $genre): array
    {
        return ['tmdb_id' => $genre['id'], 'name' => $genre['name']];
    }

    private function trailerKey(array $videos): ?string
    {
        $trailers = collect($videos)->where('site', 'YouTube')->whereIn('type', ['Trailer', 'Teaser']);

        $best = $trailers->first(fn (array $video) => $video['type'] === 'Trailer' && ($video['official'] ?? false))
            ?? $trailers->firstWhere('type', 'Trailer')
            ?? $trailers->first();

        return $best['key'] ?? null;
    }

    private function nullIfEmpty(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : $value;
    }

    private function get(string $path, array $query = [], bool $nullOn404 = false): ?array
    {
        try {
            $response = $this->request()->get($path, $query);
        } catch (ConnectionException $e) {
            throw $this->unavailable($path, null, $e->getMessage());
        }

        if ($nullOn404 && $response->status() === 404) {
            return null;
        }

        if (! $response->successful() || ! is_array($response->json())) {
            throw $this->unavailable($path, $response);
        }

        return $response->json();
    }

    private function request(): PendingRequest
    {
        $key = (string) config('services.tmdb.key');
        $request = Http::baseUrl(config('services.tmdb.url'))
            ->acceptJson()
            ->timeout(config('services.tmdb.timeout'))
            ->withQueryParameters(['language' => config('services.tmdb.language')]);

        return str_starts_with($key, 'eyJ')
            ? $request->withToken($key)
            : $request->withQueryParameters(['api_key' => $key]);
    }

    private function unavailable(string $path, ?Response $response = null, ?string $error = null): TmdbUnavailable
    {
        Log::warning('TMDB request failed', [
            'path' => $path,
            'status' => $response?->status(),
            'error' => $error === null ? null : $this->redact($error),
        ]);

        return new TmdbUnavailable();
    }

    private function redact(string $text): string
    {
        $key = (string) config('services.tmdb.key');

        $text = preg_replace('/(api_key=)[^&\s"]+/i', '$1***', $text);

        return $key === '' ? $text : str_replace($key, '***', $text);
    }
}
