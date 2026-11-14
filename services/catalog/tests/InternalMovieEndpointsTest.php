<?php

namespace Tests;

use App\Models\Genre;
use App\Models\Movie;
use App\Models\Person;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Lumen\Testing\DatabaseTransactions;

class InternalMovieEndpointsTest extends TestCase
{
    use DatabaseTransactions;

    private const TOKEN = 'test-service-token';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'internal.service_token' => self::TOKEN,
            'services.tmdb.key' => 'test-key',
            'services.tmdb.url' => 'https://api.themoviedb.org/3',
        ]);
    }

    private function token(): array
    {
        return ['X-Service-Token' => self::TOKEN];
    }

    private function data(): array
    {
        return json_decode($this->response->getContent(), true)['data'];
    }

    public function test_every_internal_endpoint_refuses_calls_without_the_right_service_token(): void
    {
        Http::fake();
        $id = Movie::factory()->create()->id;

        foreach (["/api/internal/discover?genre_ids={$id}", "/api/internal/movies?ids={$id}", "/api/internal/movies/{$id}/features", "/api/internal/movies/{$id}/similar"] as $url) {
            $this->get($url)->seeStatusCode(401)->seeJsonEquals(['message' => 'Unauthenticated.']);
            $this->get($url, ['X-Service-Token' => 'wrong'])->seeStatusCode(401);
            $this->get($url, ['Authorization' => 'Bearer something'])->seeStatusCode(401);
        }

        Http::assertNothingSent();
    }

    public function test_bulk_lookup_returns_movie_cards_in_the_order_of_the_ids(): void
    {
        $genre = Genre::factory()->create(['name' => 'Internal Lookup Genre']);
        [$a, $b] = Movie::factory()->count(2)->create();
        $a->genres()->attach($genre->id);

        $this->get("/api/internal/movies?ids={$b->id},".Str::orderedUuid().",{$a->id}", $this->token());

        $this->seeStatusCode(200);
        $data = $this->data();
        $this->assertSame([$b->id, $a->id], array_column($data, 'id'));
        $this->assertSame(['id', 'title', 'poster_url', 'year', 'release_date', 'vote_average', 'genres'], array_keys($data[0]));
        $this->assertSame(['Internal Lookup Genre'], $data[1]['genres']);
    }

    public function test_bulk_lookup_validates_the_ids(): void
    {
        $this->get('/api/internal/movies', $this->token())->seeStatusCode(422);
        $this->get('/api/internal/movies?ids=not-a-uuid', $this->token())->seeStatusCode(422);
    }

    public function test_features_list_the_genre_cast_and_director_ids(): void
    {
        $movie = Movie::factory()->create();
        $genres = Genre::factory()->count(2)->create();
        [$actor1, $actor2, $director] = Person::factory()->count(3)->create();
        $movie->genres()->attach($genres->pluck('id'));
        $movie->cast()->attach($actor2->id, ['character' => 'B', 'cast_order' => 1]);
        $movie->cast()->attach($actor1->id, ['character' => 'A', 'cast_order' => 0]);
        $movie->directors()->attach($director->id);

        $this->get("/api/internal/movies/{$movie->id}/features", $this->token());

        $this->seeStatusCode(200);
        $data = $this->data();
        $this->assertEqualsCanonicalizing($genres->pluck('id')->all(), $data['genre_ids']);
        $this->assertSame([$actor1->id, $actor2->id], $data['cast_ids']);
        $this->assertSame([$director->id], $data['director_ids']);
    }

    public function test_features_of_an_unknown_movie_is_404(): void
    {
        $this->get('/api/internal/movies/'.Str::orderedUuid().'/features', $this->token())->seeStatusCode(404);
    }

    private function similarList(array $movies): array
    {
        return ['page' => 1, 'total_pages' => 1, 'total_results' => count($movies), 'results' => $movies];
    }

    public function test_similar_returns_tmdb_similar_movies_and_creates_the_missing_ones(): void
    {
        $genre = Genre::factory()->create(['tmdb_id' => 990001, 'name' => 'Similar Genre Test']);
        $movie = Movie::factory()->create(['tmdb_id' => 1900000001]);
        $known = Movie::factory()->create(['tmdb_id' => 1900000002, 'title' => 'Already Here']);

        Http::fake(['api.themoviedb.org/3/movie/1900000001/similar*' => Http::response($this->similarList([
            ['id' => 1900000003, 'title' => 'Brand New', 'genre_ids' => [990001], 'release_date' => '2020-01-01', 'popularity' => 5, 'vote_average' => 7],
            ['id' => 1900000002, 'title' => 'Already Here', 'genre_ids' => [], 'release_date' => '2019-01-01', 'popularity' => 3, 'vote_average' => 6],
        ]))]);

        $this->get("/api/internal/movies/{$movie->id}/similar", $this->token());

        $this->seeStatusCode(200);
        $data = $this->data();
        $created = Movie::where('tmdb_id', 1900000003)->firstOrFail();
        $this->assertSame([$created->id, $known->id], array_column($data, 'id'));
        $this->assertSame('Brand New', $data[0]['title']);
        $this->assertSame(['Similar Genre Test'], $data[0]['genres']);
        $this->assertSame(1, Movie::where('tmdb_id', 1900000002)->count());
        Http::assertSent(fn ($request) => str_contains($request->url(), '/movie/1900000001/similar'));
    }

    public function test_a_movie_not_from_tmdb_has_no_similar_movies(): void
    {
        Http::fake();
        $movie = Movie::factory()->create(['tmdb_id' => null]);

        $this->get("/api/internal/movies/{$movie->id}/similar", $this->token());

        $this->seeStatusCode(200)->seeJsonEquals(['data' => []]);
        Http::assertNothingSent();
    }

    public function test_similar_is_503_when_tmdb_is_down(): void
    {
        Http::fake(['*' => Http::response([], 500)]);
        $movie = Movie::factory()->create();

        $this->get("/api/internal/movies/{$movie->id}/similar", $this->token());

        $this->seeStatusCode(503)->seeJsonEquals(['message' => 'TMDB is unavailable.']);
    }

    public function test_similar_of_an_unknown_movie_is_404(): void
    {
        Http::fake();

        $this->get('/api/internal/movies/'.Str::orderedUuid().'/similar', $this->token())->seeStatusCode(404);
    }

    public function test_discover_accepts_catalog_genre_ids(): void
    {
        $drama = Genre::factory()->create(['name' => 'Ids Drama Test']);
        $comedy = Genre::factory()->create(['name' => 'Ids Comedy Test']);
        $other = Genre::factory()->create(['name' => 'Ids Other Test']);
        [$low, $high, $both, $skipped] = Movie::factory()->count(4)->sequence(
            ['popularity' => 900001], ['popularity' => 900003], ['popularity' => 900002], ['popularity' => 900004],
        )->create();
        $low->genres()->attach($drama->id);
        $high->genres()->attach($comedy->id);
        $both->genres()->attach([$drama->id, $comedy->id]);
        $skipped->genres()->attach($other->id);

        $this->get("/api/internal/discover?genre_ids={$drama->id},{$comedy->id}&limit=50", $this->token());

        $this->seeStatusCode(200);
        $this->assertSame([$high->id, $both->id, $low->id], array_column($this->data(), 'id'));
    }

    public function test_discover_matches_genre_names_and_ids_together(): void
    {
        $byName = Genre::factory()->create(['name' => 'Mixed Name Test']);
        $byId = Genre::factory()->create(['name' => 'Mixed Id Test']);
        [$a, $b] = Movie::factory()->count(2)->sequence(['popularity' => 800002], ['popularity' => 800001])->create();
        $a->genres()->attach($byName->id);
        $b->genres()->attach($byId->id);

        $this->get("/api/internal/discover?genres=Mixed%20Name%20Test&genre_ids={$byId->id}&limit=50", $this->token());

        $this->seeStatusCode(200);
        $this->assertSame([$a->id, $b->id], array_column($this->data(), 'id'));
    }

    public function test_discover_needs_genres_or_genre_ids_and_valid_ids(): void
    {
        $this->get('/api/internal/discover', $this->token())->seeStatusCode(422);
        $this->get('/api/internal/discover?genre_ids=not-a-uuid', $this->token())->seeStatusCode(422);
        $ids = implode(',', array_map(fn () => (string) Str::orderedUuid(), range(1, 11)));
        $this->get("/api/internal/discover?genre_ids={$ids}", $this->token())->seeStatusCode(422);
    }
}
