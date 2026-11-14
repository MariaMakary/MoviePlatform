<?php

namespace Tests;

use App\Integration\Clients\TmdbClient;
use App\Models\Genre;
use App\Models\Movie;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Lumen\Testing\DatabaseTransactions;

class MovieSimilarTest extends TestCase
{
    use DatabaseTransactions;

    private const GENRE = 990201;

    private const TMDB_ID = 900700001;

    private int $status = 200;

    private int $calls = 0;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.tmdb.key' => 'test-key']);
        TmdbClient::clearCache();
        Genre::factory()->create(['tmdb_id' => self::GENRE, 'name' => 'Test Similar Genre']);

        Http::fake(function (Request $request) {
            $this->calls++;

            if ($this->status !== 200) {
                return Http::response([], $this->status);
            }

            $results = array_map(fn (int $i) => $this->tmdbMovie(900710000 + $i), range(1, 15));
            array_unshift($results, $this->tmdbMovie(self::TMDB_ID));

            return Http::response(['page' => 1, 'total_pages' => 1, 'total_results' => count($results), 'results' => $results]);
        });
    }

    private function tmdbMovie(int $id): array
    {
        return [
            'id' => $id,
            'title' => "Similar movie $id",
            'overview' => 'From the list.',
            'poster_path' => "/poster$id.jpg",
            'release_date' => '2023-03-10',
            'vote_average' => 6.8,
            'popularity' => 12.5,
            'genre_ids' => [self::GENRE],
        ];
    }

    private function movie(?int $tmdbId = self::TMDB_ID): Movie
    {
        return Movie::factory()->create(['tmdb_id' => $tmdbId]);
    }

    private function data(): array
    {
        return json_decode($this->response->getContent(), true)['data'];
    }

    public function test_it_returns_up_to_12_cards_without_the_movie_itself(): void
    {
        $movie = $this->movie();

        $this->get("/api/movies/{$movie->id}/similar")->seeStatusCode(200);

        $data = $this->data();
        $this->assertCount(12, $data);
        $this->assertSame('Similar movie 900710001', $data[0]['title']);
        $this->assertNotContains($movie->id, array_column($data, 'id'));
        $this->assertSame(['Test Similar Genre'], $data[0]['genres']);
        $this->assertSame(['id', 'title', 'poster_url', 'year', 'release_date', 'vote_average', 'genres'], array_keys($data[0]));
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/movie/'.self::TMDB_ID.'/similar'));
    }

    public function test_new_movies_are_created_once_and_reused(): void
    {
        $movie = $this->movie();

        $this->get("/api/movies/{$movie->id}/similar");
        $first = array_column($this->data(), 'id');
        $this->assertSame(1, Movie::where('tmdb_id', 900710001)->count());
        $this->assertTrue(Str::isUuid($first[0]));

        $this->get("/api/movies/{$movie->id}/similar");
        $this->assertSame($first, array_column($this->data(), 'id'));
        $this->assertSame(1, $this->calls, 'the second call is served from the cache');
    }

    public function test_an_unknown_movie_is_404(): void
    {
        $this->get('/api/movies/'.Str::uuid().'/similar')->seeStatusCode(404);
        $this->get('/api/movies/not-a-uuid/similar')->seeStatusCode(404);
    }

    public function test_a_movie_without_tmdb_id_has_no_similar_movies(): void
    {
        $movie = $this->movie(null);

        $this->get("/api/movies/{$movie->id}/similar")->seeStatusCode(200)->seeJsonEquals(['data' => []]);
        $this->assertSame(0, $this->calls);
    }

    public function test_a_movie_tmdb_no_longer_knows_has_no_similar_movies(): void
    {
        $this->status = 404;
        $movie = $this->movie();

        $this->get("/api/movies/{$movie->id}/similar")->seeStatusCode(200)->seeJsonEquals(['data' => []]);
    }

    public function test_tmdb_down_is_503(): void
    {
        $this->status = 503;
        $movie = $this->movie();

        $this->get("/api/movies/{$movie->id}/similar")->seeStatusCode(503)->seeJson(['message' => 'TMDB is unavailable.']);
    }
}
