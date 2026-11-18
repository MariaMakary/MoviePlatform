<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class GenreStatsService
{
    public function totals(array $movieCounts): array
    {
        $counts = [];

        foreach ($movieCounts as $movieId => $count) {
            $movieId = strtolower((string) $movieId);
            $counts[$movieId] = ($counts[$movieId] ?? 0) + (int) $count;
        }

        if ($counts === []) {
            return [];
        }

        $totals = [];

        DB::table('movie_genre')
            ->join('genres', 'genres.id', '=', 'movie_genre.genre_id')
            ->whereIn('movie_genre.movie_id', array_keys($counts))
            ->get(['movie_genre.movie_id', 'genres.name'])
            ->each(function ($row) use ($counts, &$totals) {
                $totals[$row->name] = ($totals[$row->name] ?? 0) + $counts[$row->movie_id];
            });

        return collect($totals)
            ->filter(fn (int $total) => $total > 0)
            ->map(fn (int $total, string $genre) => ['genre' => $genre, 'count' => $total])
            ->sortBy([['count', 'desc'], ['genre', 'asc']])
            ->values()
            ->all();
    }
}
