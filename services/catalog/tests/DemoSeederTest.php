<?php

namespace Tests;

use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Lumen\Testing\DatabaseTransactions;
use RuntimeException;

class DemoSeederTest extends TestCase
{
    use DatabaseTransactions;

    private const INCEPTION = '0193a7c1-de00-7aaa-8bbb-000000000101';

    private function seedDemo(): void
    {
        $this->app->make(DemoSeeder::class)->run();
    }

    private function clearDemoTmdbIds(): void
    {
        // A developer's database may already hold these TMDB rows; the test runs on a clean slate inside its transaction.
        $demo = json_decode(file_get_contents(base_path('../../seeds/demo.json')), true);
        DB::table('movies')->whereIn('tmdb_id', array_column($demo['movies'], 'tmdb_id'))->delete();
        DB::table('genres')->whereIn('tmdb_id', array_column($demo['genres'], 'tmdb_id'))->delete();
        DB::table('genres')->whereIn('name', array_column($demo['genres'], 'name'))->delete();
    }

    public function test_it_creates_the_demo_genres_and_movies_and_can_run_twice(): void
    {
        $this->clearDemoTmdbIds();

        $this->seedDemo();
        $this->seedDemo();

        $inception = DB::table('movies')->where('id', self::INCEPTION)->first();
        $this->assertSame(27205, $inception->tmdb_id);
        $this->assertSame('Inception', $inception->title);
        $this->assertNull($inception->synced_at, 'Opening the page syncs it from TMDB.');
        $this->assertSame(10, DB::table('movies')->where('id', 'like', '0193a7c1-de00-%')->count());
        $this->assertSame(12, DB::table('genres')->where('id', 'like', '0193a7b0-de00-%')->count());
        $this->assertEqualsCanonicalizing(['Action', 'Science Fiction', 'Adventure'], DB::table('movie_genre')
            ->join('genres', 'genres.id', '=', 'movie_genre.genre_id')->where('movie_id', self::INCEPTION)->pluck('name')->all());
    }

    public function test_a_rerun_leaves_a_movie_synced_from_tmdb_as_it_is(): void
    {
        $this->clearDemoTmdbIds();
        $this->seedDemo();
        $createdAt = DB::table('movies')->where('id', self::INCEPTION)->value('created_at');
        DB::table('movies')->where('id', self::INCEPTION)->update(['overview' => 'The full TMDB overview.', 'synced_at' => '2026-11-15 10:00:00']);

        $this->seedDemo();

        $inception = DB::table('movies')->where('id', self::INCEPTION)->first();
        $this->assertSame('The full TMDB overview.', $inception->overview);
        $this->assertSame('2026-11-15 10:00:00', $inception->synced_at);
        $this->assertSame($createdAt, $inception->created_at);
    }

    public function test_it_stops_when_a_tmdb_sync_saved_the_movie_under_another_id(): void
    {
        $this->clearDemoTmdbIds();
        DB::table('movies')->insert(['id' => (string) Str::uuid(), 'tmdb_id' => 27205, 'title' => 'Inception', 'popularity' => 0]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Catalog already has "Inception" under another id');

        $this->seedDemo();
    }
}
