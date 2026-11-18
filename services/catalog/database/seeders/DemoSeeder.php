<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use MoviePlatform\Common\Seeding\DemoData;
use RuntimeException;

/**
 * The genres and movies of seeds/demo.json (task 1289), with fixed ids that the other services point at.
 * Each has its real tmdb_id and synced_at null, so opening a movie page syncs the poster, cast and trailer from TMDB.
 * Running it again updates the same rows, except a movie already synced from TMDB, which is left as it is.
 * Never in production.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $demo = DemoData::load();
        $now = Carbon::now();

        DB::transaction(function () use ($demo, $now) {
            foreach ($demo['genres'] as $genre) {
                $this->refuseOtherRow('genres', $genre['id'], ['tmdb_id' => $genre['tmdb_id']], $genre['name']);
                $this->refuseOtherRow('genres', $genre['id'], ['name' => $genre['name']], $genre['name']);
                $this->save('genres', $genre['id'], ['tmdb_id' => $genre['tmdb_id'], 'name' => $genre['name']], $now);
            }

            foreach ($demo['movies'] as $movie) {
                $this->refuseOtherRow('movies', $movie['id'], ['tmdb_id' => $movie['tmdb_id']], $movie['title']);

                if (DB::table('movies')->where('id', $movie['id'])->whereNotNull('synced_at')->exists()) {
                    continue;
                }

                $this->save('movies', $movie['id'], [
                    'tmdb_id' => $movie['tmdb_id'],
                    'title' => $movie['title'],
                    'overview' => $movie['overview'],
                    'release_date' => $movie['release_date'],
                    'runtime' => $movie['runtime'],
                    'vote_average' => $movie['vote_average'],
                ], $now);
                DB::table('movie_genre')->where('movie_id', $movie['id'])->delete();
                DB::table('movie_genre')->insert(array_map(
                    fn (string $genre) => ['movie_id' => $movie['id'], 'genre_id' => $demo->genreId($genre)],
                    $movie['genres'],
                ));
            }
        });

        $this->command?->info(count($demo['genres']).' demo genres and '.count($demo['movies']).' demo movies.');
    }

    private function save(string $table, string $id, array $fields, Carbon $now): void
    {
        $updated = DB::table($table)->where('id', $id)->update([...$fields, 'updated_at' => $now]);

        if ($updated === 0) {
            DB::table($table)->insert(['id' => $id, ...$fields, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    /** A TMDB sync may already have saved this genre or movie under another id: the other services would not find it. */
    private function refuseOtherRow(string $table, string $id, array $match, string $name): void
    {
        if (DB::table($table)->where($match)->where('id', '<>', $id)->exists()) {
            throw new RuntimeException("Catalog already has \"$name\" under another id (from a TMDB sync): seed the demo data on fresh databases (README, Demo data).");
        }
    }
}
