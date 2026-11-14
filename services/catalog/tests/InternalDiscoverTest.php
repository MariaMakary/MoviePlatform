<?php

namespace Tests;

use App\Models\Genre;
use App\Models\Movie;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Lumen\Testing\DatabaseTransactions;

class InternalDiscoverTest extends TestCase
{
    use DatabaseTransactions;

    private const TOKEN = 'test-service-token';

    private Genre $drama;

    private Genre $comedy;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
        config(['internal.service_token' => self::TOKEN]);

        $this->drama = Genre::factory()->create(['name' => 'Discover Drama Test']);
        $this->comedy = Genre::factory()->create(['name' => 'Discover Comedy Test']);
    }

    private function token(): array
    {
        return ['X-Service-Token' => self::TOKEN];
    }

    private function movie(Genre $genre, float $popularity): Movie
    {
        $movie = Movie::factory()->create(['popularity' => $popularity]);
        $genre->movies()->attach($movie->id);

        return $movie;
    }

    private function ids(): array
    {
        return array_column(json_decode($this->response->getContent(), true)['data'], 'id');
    }

    private function discover(string $query): static
    {
        return $this->get('/api/internal/discover?'.$query, $this->token());
    }

    public function test_it_refuses_calls_without_the_right_service_token(): void
    {
        $this->get('/api/internal/discover?genres=Discover%20Drama%20Test')->seeStatusCode(401)->seeJsonEquals(['message' => 'Unauthenticated.']);
        $this->get('/api/internal/discover?genres=Discover%20Drama%20Test', ['X-Service-Token' => 'wrong'])->seeStatusCode(401);
        $this->get('/api/internal/discover?genres=Discover%20Drama%20Test', ['Authorization' => 'Bearer something'])->seeStatusCode(401);
    }

    public function test_it_returns_the_most_popular_local_movies_in_the_genres_as_cards(): void
    {
        $low = $this->movie($this->drama, 5);
        $high = $this->movie($this->drama, 90);
        $comedy = $this->movie($this->comedy, 50);
        $this->movie(Genre::factory()->create(['name' => 'Discover Other Test']), 99);

        $this->discover('genres=Discover%20Drama%20Test,Discover%20Comedy%20Test')->seeStatusCode(200);

        $this->assertSame([$high->id, $comedy->id, $low->id], $this->ids());
        $card = json_decode($this->response->getContent(), true)['data'][0];
        $this->assertSame(['id', 'title', 'poster_url', 'year', 'release_date', 'vote_average', 'genres'], array_keys($card));
        $this->assertSame(['Discover Drama Test'], $card['genres']);
        Http::assertNothingSent();
    }

    public function test_a_movie_in_several_matching_genres_is_returned_once(): void
    {
        $both = $this->movie($this->drama, 10);
        $this->comedy->movies()->attach($both->id);

        $this->discover('genres=Discover%20Drama%20Test,Discover%20Comedy%20Test')->seeStatusCode(200);

        $this->assertSame([$both->id], $this->ids());
    }

    public function test_limit_and_exclude_ids(): void
    {
        $movies = collect(range(1, 5))->map(fn (int $i) => $this->movie($this->drama, $i * 10));

        $this->discover('genres=Discover%20Drama%20Test&limit=2')->seeStatusCode(200);
        $this->assertSame([$movies[4]->id, $movies[3]->id], $this->ids());

        $this->discover('genres=Discover%20Drama%20Test&limit=2&exclude_ids='.$movies[4]->id.','.$movies[3]->id)->seeStatusCode(200);
        $this->assertSame([$movies[2]->id, $movies[1]->id], $this->ids());
    }

    public function test_the_default_limit_is_10(): void
    {
        foreach (range(1, 12) as $i) {
            $this->movie($this->drama, $i);
        }

        $this->discover('genres=Discover%20Drama%20Test')->seeStatusCode(200);

        $this->assertCount(10, $this->ids());
    }

    public function test_an_unknown_genre_gives_an_empty_list(): void
    {
        $this->discover('genres=No%20Such%20Genre%20Test')->seeStatusCode(200)->seeJsonEquals(['data' => []]);
    }

    public function test_invalid_queries_are_refused(): void
    {
        foreach ([
            ['', 'genres'],
            ['genres=Discover%20Drama%20Test&limit=0', 'limit'],
            ['genres=Discover%20Drama%20Test&limit=51', 'limit'],
            ['genres=Discover%20Drama%20Test&exclude_ids=abc', 'exclude_ids.0'],
        ] as [$query, $field]) {
            $this->discover($query)->seeStatusCode(422)->seeJsonStructure(['message', 'errors' => [$field]]);
        }

        $id = (string) Str::uuid();
        $this->discover("genres=Discover%20Drama%20Test&exclude_ids=$id")->seeStatusCode(200);
    }
}
