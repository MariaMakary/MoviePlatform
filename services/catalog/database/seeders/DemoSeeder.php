<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The genres and movies of seeds/demo.json (task 1289), with fixed ids that the other services point at.
 * Each has its real tmdb_id and synced_at null, so opening a movie page syncs the poster, cast and trailer from TMDB.
 * Running it again updates the same rows. Never in production.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('The demo data is never seeded in production.');
        }

        $demo = json_decode(file_get_contents(base_path('../../seeds/demo.json')), true, flags: JSON_THROW_ON_ERROR);
        $now = Carbon::now();

        DB::transaction(function () use ($demo, $now) {
            foreach ($demo['genres'] as $genre) {
                $this->refuseOtherRow('genres', $genre['id'], ['tmdb_id' => $genre['tmdb_id']], $genre['name']);
                $this->refuseOtherRow('genres', $genre['id'], ['name' => $genre['name']], $genre['name']);
                DB::table('genres')->updateOrInsert(['id' => $genre['id']], [
                    'tmdb_id' => $genre['tmdb_id'], 'name' => $genre['name'], 'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            foreach ($demo['movies'] as $movie) {
                $this->refuseOtherRow('movies', $movie['id'], ['tmdb_id' => $movie['tmdb_id']], $movie['title']);
                DB::table('movies')->updateOrInsert(['id' => $movie['id']], [
                    'tmdb_id' => $movie['tmdb_id'],
                    'title' => $movie['title'],
                    'overview' => $movie['overview'],
                    'release_date' => $movie['release_date'],
                    'runtime' => $movie['runtime'],
                    'vote_average' => $movie['vote_average'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                DB::table('movie_genre')->where('movie_id', $movie['id'])->delete();
                DB::table('movie_genre')->insert(array_map(
                    fn (string $genre) => ['movie_id' => $movie['id'], 'genre_id' => $demo['genres'][$genre]['id']],
                    $movie['genres'],
                ));
            }
        });

        $this->command?->info(count($demo['genres']).' demo genres and '.count($demo['movies']).' demo movies.');
    }

    /** A TMDB sync may already have saved this genre or movie under another id: the other services would not find it. */
    private function refuseOtherRow(string $table, string $id, array $match, string $name): void
    {
        if (DB::table($table)->where($match)->where('id', '<>', $id)->exists()) {
            throw new RuntimeException("Catalog already has \"$name\" under another id (from a TMDB sync): seed the demo data on fresh databases (README, Demo data).");
        }
    }
}
