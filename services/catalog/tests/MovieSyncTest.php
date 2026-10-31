<?php

namespace Tests;

use App\Enums\CreditRole;
use App\Models\Genre;
use App\Models\Movie;
use App\Models\Person;
use App\Services\MovieSyncService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Lumen\Testing\DatabaseTransactions;

class MovieSyncTest extends TestCase
{
    use DatabaseTransactions;

    private const MOVIE = 900000001;

    private const DIRECTOR = 900100000;

    private array $details = [];

    private int $popularPages = 0;

    private bool $tmdbDown = false;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.tmdb.key' => 'test-key']);
        $this->details[self::MOVIE] = $this->tmdbDetails(self::MOVIE);
        $this->fakeTmdb();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function tmdbDetails(int $id, array $overrides = []): array
    {
        return array_merge([
            'id' => $id,
            'title' => "Movie $id",
            'overview' => 'From TMDB.',
            'poster_path' => '/poster.jpg',
            'backdrop_path' => '/backdrop.jpg',
            'release_date' => '2010-07-15',
            'runtime' => 148,
            'vote_average' => 8.37,
            'popularity' => 83.9,
            'genres' => [['id' => 990001, 'name' => 'Test Action'], ['id' => 990002, 'name' => 'Test Sci-Fi']],
            'credits' => [
                'cast' => array_map(fn (int $i) => [
                    'id' => 900200000 + $i, 'name' => "Actor $i", 'character' => "Role $i", 'order' => $i, 'known_for_department' => 'Acting',
                ], range(0, 17)),
                'crew' => [['id' => self::DIRECTOR, 'name' => 'Test Director', 'job' => 'Director', 'known_for_department' => 'Directing']],
            ],
            'videos' => ['results' => [['site' => 'YouTube', 'type' => 'Trailer', 'key' => 'trailer123', 'official' => true]]],
        ], $overrides);
    }

    private function fakeTmdb(): void
    {
        Http::fake(function (Request $request) {
            if ($this->tmdbDown) {
                return Http::response([], 503);
            }

            $path = parse_url($request->url(), PHP_URL_PATH);

            if (preg_match('#/movie/(\d+)$#', $path, $m)) {
                $id = (int) $m[1];
                $this->details[$id] ??= $this->tmdbDetails($id);

                return $id === 900999999
                    ? Http::response(['status_message' => 'Not found.'], 404)
                    : Http::response($this->details[$id]);
            }

            if (str_ends_with($path, '/movie/popular')) {
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
                $page = (int) $query['page'];

                return Http::response([
                    'page' => $page,
                    'total_pages' => $this->popularPages,
                    'results' => array_map(fn (int $i) => ['id' => 900000000 + $page * 100 + $i, 'title' => 'x'], range(1, 20)),
                ]);
            }

            if (str_ends_with($path, '/genre/movie/list')) {
                return Http::response(['genres' => [['id' => 990001, 'name' => 'Test Action'], ['id' => 990003, 'name' => 'Test Drama']]]);
            }

            return Http::response([], 500);
        });
    }

    private function sync(int $tmdbId = self::MOVIE): ?Movie
    {
        return app(MovieSyncService::class)->sync($tmdbId);
    }

    private function credits(Movie $movie): \Illuminate\Support\Collection
    {
        return DB::table('movie_person')->where('movie_id', $movie->id)->get();
    }

    public function test_sync_saves_the_movie_genres_top_15_cast_directors_and_trailer(): void
    {
        $movie = $this->sync();

        $this->assertSame('Movie 900000001', $movie->title);
        $this->assertSame('2010-07-15', $movie->release_date->toDateString());
        $this->assertSame(148, $movie->runtime);
        $this->assertSame('8.4', $movie->vote_average);
        $this->assertSame('trailer123', $movie->trailer_key);
        $this->assertNotNull($movie->synced_at);
        $this->assertFalse($movie->is_edited);

        $this->assertEqualsCanonicalizing(['Test Action', 'Test Sci-Fi'], $movie->genres()->pluck('name')->all());

        $cast = $movie->cast()->get();
        $this->assertCount(MovieSyncService::CAST_LIMIT, $cast);
        $this->assertSame(['Actor 0', 'Actor 1', 'Actor 2'], $cast->take(3)->pluck('name')->all());
        $this->assertSame('Role 0', $cast[0]->pivot->character);
        $this->assertSame(14, $cast->last()->pivot->cast_order);

        $this->assertSame(['Test Director'], $movie->directors()->pluck('name')->all());
    }

    public function test_running_the_sync_twice_creates_no_duplicates_and_updates_the_data(): void
    {
        $first = $this->sync();
        $creditIds = $this->credits($first)->pluck('id')->sort()->values();

        $this->details[self::MOVIE]['title'] = 'New title on TMDB';
        $second = $this->sync();

        $this->assertSame($first->id, $second->id);
        $this->assertSame('New title on TMDB', $second->fresh()->title);
        $this->assertSame(1, Movie::where('tmdb_id', self::MOVIE)->count());
        $this->assertSame(16, $this->credits($second)->count());
        $this->assertEquals($creditIds, $this->credits($second)->pluck('id')->sort()->values(), 'Credits were recreated instead of upserted.');
        $this->assertSame(1, Person::where('tmdb_id', self::DIRECTOR)->count());
        $this->assertSame(1, Genre::where('tmdb_id', 990001)->count());
    }

    public function test_an_edited_movie_keeps_its_fields_and_genres(): void
    {
        $movie = $this->sync();
        $drama = Genre::create(['name' => 'Test Admin Genre']);
        $movie->update(['title' => 'Title fixed by an admin', 'overview' => 'Admin overview', 'is_edited' => true]);
        $movie->genres()->sync([$drama->id]);
        $syncedBefore = $movie->synced_at;

        Carbon::setTestNow(Carbon::now()->addHour());
        $this->details[self::MOVIE]['title'] = 'TMDB title';
        $this->sync();

        $movie->refresh();
        $this->assertSame('Title fixed by an admin', $movie->title);
        $this->assertSame('Admin overview', $movie->overview);
        $this->assertTrue($movie->is_edited);
        $this->assertSame(['Test Admin Genre'], $movie->genres()->pluck('name')->all());
        $this->assertTrue($movie->synced_at->greaterThan($syncedBefore));
        $this->assertSame(16, $this->credits($movie)->count());
    }

    public function test_credits_follow_tmdb_changes(): void
    {
        $movie = $this->sync();
        $actor0Credit = $this->credits($movie)->firstWhere('character', 'Role 0');

        $cast = &$this->details[self::MOVIE]['credits']['cast'];
        $cast[0]['character'] = 'Renamed role';
        $cast[1]['order'] = 99;

        $this->sync();

        $credits = $this->credits($movie);
        $this->assertSame('Renamed role', $credits->firstWhere('id', $actor0Credit->id)->character);
        $this->assertSame(16, $credits->count());
        $this->assertFalse($movie->cast()->pluck('name')->contains('Actor 1'), 'Actor 1 left the top 15 but kept a credit.');
        $this->assertTrue($movie->cast()->pluck('name')->contains('Actor 15'));
    }

    public function test_a_person_listed_twice_gets_one_credit(): void
    {
        $this->details[self::MOVIE]['credits']['cast'][1]['id'] = 900200000;

        $movie = $this->sync();

        $this->assertSame(1, $this->credits($movie)->where('role', CreditRole::Cast->value)->where('person_id', Person::where('tmdb_id', 900200000)->value('id'))->count());
    }

    public function test_a_local_genre_with_the_same_name_is_linked_to_tmdb(): void
    {
        $local = Genre::create(['name' => 'Test Action']);

        $this->sync();

        $this->assertSame(990001, $local->fresh()->tmdb_id);
        $this->assertSame(1, Genre::where('name', 'Test Action')->count());
    }

    public function test_a_movie_missing_on_tmdb_saves_nothing(): void
    {
        $this->assertNull($this->sync(900999999));
        $this->assertSame(0, Movie::where('tmdb_id', 900999999)->count());
    }

    public function test_genre_sync_creates_no_duplicates(): void
    {
        $sync = app(MovieSyncService::class);

        $this->assertSame(2, $sync->syncGenres());
        $this->assertSame(2, $sync->syncGenres());

        $this->assertSame(1, Genre::where('tmdb_id', 990001)->count());
        $this->assertSame('Test Drama', Genre::where('tmdb_id', 990003)->value('name'));
    }

    public function test_the_commands_sync_genres_and_popular_movies_without_duplicates(): void
    {
        $this->popularPages = 3;
        $ids = range(900000101, 900000320);
        $count = fn () => Movie::whereIn('tmdb_id', $ids)->count();

        $this->assertSame(0, $this->artisan('catalog:sync-genres'));
        $this->assertSame(0, $this->artisan('catalog:sync-popular', ['pages' => 2]));
        $this->assertStringContainsString('Synced 40 movies.', $this->app[Kernel::class]->output());
        $this->assertSame(40, $count());

        $this->assertSame(0, $this->artisan('catalog:sync-popular', ['pages' => 2]));
        $this->assertSame(40, $count());
        $this->assertSame(1, Genre::where('tmdb_id', 990003)->count());
    }

    public function test_sync_popular_stops_at_the_last_tmdb_page(): void
    {
        $this->popularPages = 1;

        $this->assertSame(20, app(MovieSyncService::class)->syncPopular(5));
    }

    public function test_the_commands_fail_cleanly_when_tmdb_is_down(): void
    {
        $this->tmdbDown = true;

        $this->assertSame(1, $this->artisan('catalog:sync-genres'));
        $this->assertStringContainsString('TMDB is unavailable.', $this->app[Kernel::class]->output());

        $this->assertSame(1, $this->artisan('catalog:sync-popular', ['pages' => 1]));
        $this->assertStringContainsString('TMDB is unavailable.', $this->app[Kernel::class]->output());
    }

    public function test_sync_popular_rejects_a_bad_page_count(): void
    {
        $this->assertSame(2, $this->artisan('catalog:sync-popular', ['pages' => 0]));
    }
}
