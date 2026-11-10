<?php

namespace Tests;

use App\Models\Genre;
use App\Models\Movie;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Lumen\Testing\DatabaseTransactions;

class GenreEndpointsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
    }

    private function body(): array
    {
        return json_decode($this->response->getContent(), true);
    }

    public function test_all_genres_are_listed_alphabetically_with_id_and_name(): void
    {
        $zeta = Genre::factory()->create(['name' => 'Zzz genre test']);
        $alpha = Genre::factory()->create(['name' => 'Aaa genre test']);

        $this->get('/api/genres')->seeStatusCode(200);

        $data = $this->body()['data'];
        $this->assertArrayNotHasKey('meta', $this->body());
        $this->assertContains(['id' => $alpha->id, 'name' => 'Aaa genre test'], $data);
        $this->assertContains(['id' => $zeta->id, 'name' => 'Zzz genre test'], $data);
        $this->assertLessThan(array_search($zeta->id, array_column($data, 'id')), array_search($alpha->id, array_column($data, 'id')));
        Http::assertNothingSent();
    }

    public function test_a_genre_lists_its_local_movies_by_popularity_by_default(): void
    {
        $genre = Genre::factory()->create();
        $low = Movie::factory()->create(['popularity' => 1, 'vote_average' => 9]);
        $high = Movie::factory()->create(['popularity' => 50, 'vote_average' => 5]);
        $other = Movie::factory()->create(['popularity' => 99]);
        $genre->movies()->attach([$low->id, $high->id]);

        $this->get("/api/genres/{$genre->id}/movies")->seeStatusCode(200);

        $this->assertSame([$high->id, $low->id], array_column($this->body()['data'], 'id'));
        $this->assertSame(['current_page' => 1, 'last_page' => 1, 'total' => 2], array_intersect_key($this->body()['meta'], array_flip(['current_page', 'last_page', 'total'])));
        $this->assertSame([$genre->name], $this->body()['data'][0]['genres']);
        $this->assertNotContains($other->id, array_column($this->body()['data'], 'id'));

        $this->get("/api/genres/{$genre->id}/movies?sort=vote_average")->seeStatusCode(200);
        $this->assertSame([$low->id, $high->id], array_column($this->body()['data'], 'id'));

        Http::assertNothingSent();
    }

    public function test_genre_movies_are_paginated_by_20(): void
    {
        $genre = Genre::factory()->create();
        $genre->movies()->attach(Movie::factory()->count(21)->create()->pluck('id'));

        $this->get("/api/genres/{$genre->id}/movies?page=2")->seeStatusCode(200);

        $this->assertCount(1, $this->body()['data']);
        $this->assertSame(2, $this->body()['meta']['last_page']);
    }

    public function test_an_unknown_genre_gives_404(): void
    {
        $this->get('/api/genres/'.Str::uuid().'/movies')->seeStatusCode(404)->seeJsonEquals(['message' => 'Genre not found.']);
        $this->get('/api/genres/not-a-uuid/movies')->seeStatusCode(404);
    }

    public function test_invalid_sort_or_page_gives_422(): void
    {
        $genre = Genre::factory()->create();

        $this->get("/api/genres/{$genre->id}/movies?sort=release_date")->seeStatusCode(422);
        $this->get("/api/genres/{$genre->id}/movies?page=0")->seeStatusCode(422);
    }
}
