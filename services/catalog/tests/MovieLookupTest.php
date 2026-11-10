<?php

namespace Tests;

use App\Models\Genre;
use App\Models\Movie;
use Illuminate\Support\Str;
use Laravel\Lumen\Testing\DatabaseTransactions;

class MovieLookupTest extends TestCase
{
    use DatabaseTransactions;

    private function lookup(array $ids): void
    {
        $this->get('/api/movies?ids='.implode(',', $ids));
    }

    private function returnedIds(): array
    {
        return array_column(json_decode($this->response->getContent(), true)['data'], 'id');
    }

    public function test_results_follow_the_order_of_the_ids(): void
    {
        [$a, $b, $c] = Movie::factory()->count(3)->create();

        $this->lookup([$c->id, $a->id, $b->id]);

        $this->seeStatusCode(200);
        $this->assertSame([$c->id, $a->id, $b->id], $this->returnedIds());
    }

    public function test_unknown_ids_are_skipped(): void
    {
        $movie = Movie::factory()->create();

        $this->lookup([(string) Str::orderedUuid(), $movie->id, (string) Str::orderedUuid()]);

        $this->seeStatusCode(200);
        $this->assertSame([$movie->id], $this->returnedIds());
    }

    public function test_only_unknown_ids_give_an_empty_list(): void
    {
        $this->lookup([(string) Str::orderedUuid()]);

        $this->seeStatusCode(200)->seeJsonEquals(['data' => []]);
    }

    public function test_a_duplicated_id_is_returned_once(): void
    {
        $movie = Movie::factory()->create();

        $this->lookup([$movie->id, $movie->id]);

        $this->assertSame([$movie->id], $this->returnedIds());
    }

    public function test_100_ids_are_accepted(): void
    {
        $movies = Movie::factory()->count(3)->create();
        $ids = array_merge($movies->pluck('id')->all(), array_map(fn () => (string) Str::orderedUuid(), range(1, 97)));

        $this->lookup($ids);

        $this->seeStatusCode(200);
        $this->assertSame($movies->pluck('id')->all(), $this->returnedIds());
    }

    public function test_more_than_100_ids_are_refused(): void
    {
        $this->lookup(array_map(fn () => (string) Str::orderedUuid(), range(1, 101)));

        $this->seeStatusCode(422)->seeJsonStructure(['message', 'errors' => ['ids']]);
    }

    public function test_ids_are_required(): void
    {
        $this->get('/api/movies')->seeStatusCode(422)->seeJsonStructure(['message', 'errors' => ['ids']]);
        $this->get('/api/movies?ids=')->seeStatusCode(422);
    }

    public function test_an_id_that_is_not_a_uuid_is_refused(): void
    {
        $movie = Movie::factory()->create();

        $this->lookup([$movie->id, 'not-a-uuid']);

        $this->seeStatusCode(422)->seeJsonStructure(['message', 'errors' => ['ids.1']]);
    }

    public function test_a_card_has_poster_url_year_rating_and_genre_names(): void
    {
        $movie = Movie::factory()->create([
            'title' => 'Inception',
            'poster_path' => '/abc.jpg',
            'release_date' => '2010-07-15',
            'vote_average' => 8.37,
        ]);
        $movie->genres()->attach([
            Genre::factory()->create(['name' => 'Zz Test Sci-Fi'])->id,
            Genre::factory()->create(['name' => 'Aa Test Action'])->id,
        ]);

        $this->lookup([$movie->id]);

        $this->seeStatusCode(200)->seeJsonEquals(['data' => [[
            'id' => $movie->id,
            'title' => 'Inception',
            'poster_url' => 'https://image.tmdb.org/t/p/w342/abc.jpg',
            'year' => 2010,
            'release_date' => '2010-07-15',
            'vote_average' => 8.4,
            'genres' => ['Aa Test Action', 'Zz Test Sci-Fi'],
        ]]]);
    }

    public function test_a_card_without_poster_date_or_rating_has_nulls(): void
    {
        $movie = Movie::factory()->local()->create(['poster_path' => null, 'release_date' => null, 'vote_average' => null]);

        $this->lookup([$movie->id]);

        $card = json_decode($this->response->getContent(), true)['data'][0];
        $this->assertNull($card['poster_url']);
        $this->assertNull($card['year']);
        $this->assertNull($card['release_date']);
        $this->assertNull($card['vote_average']);
        $this->assertSame([], $card['genres']);
    }
}
