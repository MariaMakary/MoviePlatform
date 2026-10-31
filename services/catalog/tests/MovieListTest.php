<?php

namespace Tests;

use App\Models\Genre;
use App\Models\Movie;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Lumen\Testing\DatabaseTransactions;

class MovieListTest extends TestCase
{
    use DatabaseTransactions;

    private const GENRE = 990101;

    private array $upcoming = [];

    private int $totalPages = 3;

    private int $totalResults = 57;

    private bool $tmdbDown = false;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.tmdb.key' => 'test-key']);
        Genre::factory()->create(['tmdb_id' => self::GENRE, 'name' => 'Test List Genre']);

        Http::fake(function (Request $request) {
            if ($this->tmdbDown) {
                return Http::response([], 503);
            }

            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $page = (int) ($query['page'] ?? 1);
            $path = parse_url($request->url(), PHP_URL_PATH);

            $results = str_ends_with($path, '/movie/upcoming')
                ? $this->upcoming
                : array_map(fn (int $i) => $this->tmdbMovie(900500000 + $page * 100 + $i), range(1, 20));

            return Http::response([
                'page' => $page,
                'total_pages' => $this->totalPages,
                'total_results' => $this->totalResults,
                'results' => $results,
            ]);
        });
    }

    private function tmdbMovie(int $id, array $overrides = []): array
    {
        return array_merge([
            'id' => $id,
            'title' => "List movie $id",
            'overview' => 'From the list.',
            'poster_path' => "/poster$id.jpg",
            'backdrop_path' => null,
            'release_date' => '2024-05-01',
            'vote_average' => 7.25,
            'popularity' => 50.5,
            'genre_ids' => [self::GENRE, 999999],
        ], $overrides);
    }

    private function body(): array
    {
        return json_decode($this->response->getContent(), true);
    }

    public function test_popular_returns_catalog_ids_and_the_meta(): void
    {
        $this->get('/api/movies/popular')->seeStatusCode(200);

        $body = $this->body();
        $this->assertCount(20, $body['data']);
        $this->assertSame(['current_page' => 1, 'last_page' => 3, 'total' => 57], $body['meta']);

        $first = $body['data'][0];
        $this->assertTrue(Str::isUuid($first['id']));
        $this->assertSame(Movie::where('tmdb_id', 900500101)->value('id'), $first['id']);
        $this->assertArrayNotHasKey('tmdb_id', $first);
        $this->assertSame('List movie 900500101', $first['title']);
        $this->assertSame('https://image.tmdb.org/t/p/w342/poster900500101.jpg', $first['poster_url']);
        $this->assertSame(2024, $first['year']);
        $this->assertSame(['Test List Genre'], $first['genres']);
    }

    public function test_page_2_returns_the_next_movies(): void
    {
        $this->get('/api/movies/popular?page=2')->seeStatusCode(200);

        $body = $this->body();
        $this->assertSame(2, $body['meta']['current_page']);
        $this->assertSame(Movie::where('tmdb_id', 900500201)->value('id'), $body['data'][0]['id']);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/movie/popular') && str_contains($request->url(), 'page=2'));
    }

    public function test_the_whole_page_costs_one_tmdb_call(): void
    {
        $this->get('/api/movies/popular')->seeStatusCode(200);

        Http::assertSentCount(1);
    }

    public function test_a_missing_movie_is_created_from_the_list_without_details(): void
    {
        $this->get('/api/movies/popular')->seeStatusCode(200);

        $movie = Movie::where('tmdb_id', 900500101)->firstOrFail();
        $this->assertSame('List movie 900500101', $movie->title);
        $this->assertSame('From the list.', $movie->overview);
        $this->assertSame('2024-05-01', $movie->release_date->toDateString());
        $this->assertSame('7.3', $movie->vote_average);
        $this->assertNull($movie->synced_at, 'A movie created from a list must be synced later (task 1167).');
        $this->assertNull($movie->runtime);
        $this->assertSame(['Test List Genre'], $movie->genres()->pluck('name')->all());
    }

    public function test_an_existing_movie_is_reused_and_keeps_its_edits(): void
    {
        $edited = Movie::factory()->create(['tmdb_id' => 900500105, 'title' => 'Title fixed by an admin', 'is_edited' => true]);

        $this->get('/api/movies/popular')->seeStatusCode(200);
        $this->get('/api/movies/popular')->seeStatusCode(200);

        $this->assertSame(1, Movie::where('tmdb_id', 900500105)->count());
        $this->assertSame(20, Movie::whereBetween('tmdb_id', [900500101, 900500120])->count());
        $card = collect($this->body()['data'])->firstWhere('id', $edited->id);
        $this->assertSame('Title fixed by an admin', $card['title']);
    }

    public function test_upcoming_keeps_only_future_release_dates(): void
    {
        $tomorrow = Carbon::tomorrow('UTC')->toDateString();
        $this->upcoming = [
            $this->tmdbMovie(900600001, ['release_date' => $tomorrow]),
            $this->tmdbMovie(900600002, ['release_date' => Carbon::today('UTC')->toDateString()]),
            $this->tmdbMovie(900600003, ['release_date' => '2020-01-01']),
            $this->tmdbMovie(900600004, ['release_date' => '']),
            $this->tmdbMovie(900600005, ['release_date' => Carbon::today('UTC')->addYear()->toDateString()]),
        ];

        $this->get('/api/movies/upcoming')->seeStatusCode(200);

        $ids = array_column($this->body()['data'], 'id');
        $this->assertSame(Movie::whereIn('tmdb_id', [900600001, 900600005])->orderBy('tmdb_id')->pluck('id')->all(), $ids);
        $this->assertSame(['current_page' => 1, 'last_page' => 3, 'total' => 57], $this->body()['meta']);
        $this->assertSame(0, Movie::whereIn('tmdb_id', [900600002, 900600003, 900600004])->count(), 'Past movies must not be created.');
    }

    public function test_upcoming_page_2(): void
    {
        $this->upcoming = [$this->tmdbMovie(900600010, ['release_date' => Carbon::today('UTC')->addMonth()->toDateString()])];

        $this->get('/api/movies/upcoming?page=2')->seeStatusCode(200);

        $this->assertSame(2, $this->body()['meta']['current_page']);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/movie/upcoming') && str_contains($request->url(), 'page=2'));
    }

    public function test_last_page_and_total_are_capped_to_what_tmdb_serves(): void
    {
        $this->totalPages = 45000;
        $this->totalResults = 900000;

        $this->get('/api/movies/popular')->seeStatusCode(200);

        $this->assertSame(['current_page' => 1, 'last_page' => 500, 'total' => 10000], $this->body()['meta']);
    }

    public function test_an_invalid_page_is_refused(): void
    {
        foreach (['0', '501', 'abc', '-1'] as $page) {
            $this->get("/api/movies/popular?page=$page")->seeStatusCode(422)->seeJsonStructure(['message', 'errors' => ['page']]);
        }

        Http::assertNothingSent();
    }

    public function test_tmdb_down_gives_503(): void
    {
        $this->tmdbDown = true;

        $this->get('/api/movies/popular')->seeStatusCode(503)->seeJsonEquals(['message' => 'TMDB is unavailable.']);
        $this->get('/api/movies/upcoming')->seeStatusCode(503)->seeJsonEquals(['message' => 'TMDB is unavailable.']);
    }

    public function test_no_token_is_needed(): void
    {
        $this->get('/api/movies/popular')->seeStatusCode(200);
        $this->get('/api/movies/upcoming')->seeStatusCode(200);
    }
}
