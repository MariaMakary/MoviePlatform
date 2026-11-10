<?php

namespace Tests;

use App\Models\Genre;
use App\Models\Movie;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Lumen\Testing\DatabaseTransactions;

class MovieSearchTest extends TestCase
{
    use DatabaseTransactions;

    // A made-up word, so the movies already in the local catalog-db never match.
    private const WORD = 'Zorblaxian';

    private const TMDB_GENRE = 990201;

    private array $tmdbResults = [];

    private bool $tmdbDown = false;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.tmdb.key' => 'test-key']);

        Http::fake(function () {
            if ($this->tmdbDown) {
                return Http::response([], 503);
            }

            return Http::response([
                'page' => 1,
                'total_pages' => 1,
                'total_results' => count($this->tmdbResults),
                'results' => $this->tmdbResults,
            ]);
        });
    }

    private function movie(string $title, array $attributes = []): Movie
    {
        return Movie::factory()->create(['title' => $title, ...$attributes]);
    }

    private function tmdbMovie(int $id, string $title): array
    {
        return [
            'id' => $id,
            'title' => $title,
            'overview' => 'From TMDB search.',
            'poster_path' => "/search$id.jpg",
            'backdrop_path' => null,
            'release_date' => '2019-03-01',
            'vote_average' => 6.5,
            'popularity' => 12.5,
            'genre_ids' => [self::TMDB_GENRE],
        ];
    }

    private function search(array $query): array
    {
        $this->get('/api/movies/search?'.http_build_query($query));

        return json_decode($this->response->getContent(), true);
    }

    private function titles(array $body): array
    {
        return array_column($body['data'], 'title');
    }

    public function test_a_title_matches_case_insensitively_anywhere_and_returns_cards(): void
    {
        $movie = $this->movie('The '.self::WORD.' Returns');
        $this->movie('Something else');

        $body = $this->search(['q' => strtolower(self::WORD)]);

        $this->seeStatusCode(200);
        $this->assertSame(['The '.self::WORD.' Returns'], $this->titles($body));
        $this->assertSame($movie->id, $body['data'][0]['id']);
        $this->assertSame(['id', 'title', 'poster_url', 'year', 'release_date', 'vote_average', 'genres'], array_keys($body['data'][0]));
        $this->assertSame(['current_page' => 1, 'last_page' => 1, 'total' => 1], $body['meta']);
    }

    public function test_ten_local_matches_do_not_ask_tmdb(): void
    {
        foreach (range(1, 10) as $i) {
            $this->movie(self::WORD." $i");
        }

        $this->search(['q' => self::WORD]);

        $this->seeStatusCode(200);
        Http::assertNothingSent();
    }

    public function test_a_title_missing_locally_is_found_through_tmdb_and_gets_a_catalog_id(): void
    {
        $existing = $this->movie(self::WORD.' Origins', ['tmdb_id' => 990300001]);
        $this->tmdbResults = [$this->tmdbMovie(990300001, self::WORD.' Origins'), $this->tmdbMovie(990300002, self::WORD.' Rising')];
        Genre::factory()->create(['tmdb_id' => self::TMDB_GENRE, 'name' => 'Search Test Genre']);

        $body = $this->search(['q' => self::WORD]);

        $this->seeStatusCode(200);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/search/movie') && str_contains($request->url(), 'query='.self::WORD));

        $created = Movie::where('tmdb_id', 990300002)->sole();
        $this->assertTrue(Str::isUuid($created->id));
        $this->assertSame(['Search Test Genre'], $created->genres()->pluck('name')->all());
        $this->assertSame(1, Movie::where('tmdb_id', 990300001)->count(), 'An existing movie is reused, not duplicated.');
        $this->assertEqualsCanonicalizing([$existing->id, $created->id], array_column($body['data'], 'id'));
    }

    public function test_tmdb_down_still_returns_the_local_results(): void
    {
        $this->movie(self::WORD.' Alone');
        $this->tmdbDown = true;

        $body = $this->search(['q' => self::WORD]);

        $this->seeStatusCode(200);
        $this->assertSame([self::WORD.' Alone'], $this->titles($body));
    }

    public function test_genre_and_year_filters_combine_with_the_text(): void
    {
        $drama = Genre::factory()->create(['name' => 'Search Drama']);
        $comedy = Genre::factory()->create(['name' => 'Search Comedy']);

        $match = $this->movie(self::WORD.' Match', ['release_date' => '2010-06-01']);
        $match->genres()->attach($drama);
        $wrongYear = $this->movie(self::WORD.' Wrong year', ['release_date' => '2011-01-01']);
        $wrongYear->genres()->attach($drama);
        $wrongGenre = $this->movie(self::WORD.' Wrong genre', ['release_date' => '2010-02-01']);
        $wrongGenre->genres()->attach($comedy);

        $this->assertSame([self::WORD.' Match'], $this->titles($this->search(['q' => self::WORD, 'genre' => $drama->id, 'year' => 2010])));
        $this->assertEqualsCanonicalizing([self::WORD.' Match', self::WORD.' Wrong year'], $this->titles($this->search(['q' => self::WORD, 'genre' => $drama->id])));
        $this->assertEqualsCanonicalizing([self::WORD.' Match', self::WORD.' Wrong genre'], $this->titles($this->search(['q' => self::WORD, 'year' => 2010])));
    }

    public function test_filters_work_without_a_text_and_never_ask_tmdb(): void
    {
        $genre = Genre::factory()->create(['name' => 'Search Only Genre']);
        $this->movie(self::WORD.' Tagged')->genres()->attach($genre);

        $this->assertSame([self::WORD.' Tagged'], $this->titles($this->search(['genre' => $genre->id])));
        Http::assertNothingSent();
    }

    public function test_sorts_are_descending_with_empty_values_last(): void
    {
        $this->movie(self::WORD.' A', ['popularity' => 10, 'vote_average' => 9.1, 'release_date' => '2001-01-01']);
        $this->movie(self::WORD.' B', ['popularity' => 30, 'vote_average' => 5.0, 'release_date' => null]);
        $this->movie(self::WORD.' C', ['popularity' => 20, 'vote_average' => null, 'release_date' => '2020-01-01']);

        $this->assertSame([self::WORD.' B', self::WORD.' C', self::WORD.' A'], $this->titles($this->search(['q' => self::WORD])));
        $this->assertSame([self::WORD.' A', self::WORD.' B', self::WORD.' C'], $this->titles($this->search(['q' => self::WORD, 'sort' => 'vote_average'])));
        $this->assertSame([self::WORD.' C', self::WORD.' A', self::WORD.' B'], $this->titles($this->search(['q' => self::WORD, 'sort' => 'release_date'])));
    }

    public function test_results_are_paginated_by_20(): void
    {
        foreach (range(1, 25) as $i) {
            $this->movie(self::WORD." $i", ['popularity' => $i]);
        }

        $body = $this->search(['q' => self::WORD, 'page' => 2]);

        $this->assertSame(['current_page' => 2, 'last_page' => 2, 'total' => 25], $body['meta']);
        $this->assertSame([self::WORD.' 5', self::WORD.' 4', self::WORD.' 3', self::WORD.' 2', self::WORD.' 1'], $this->titles($body));
    }

    public function test_like_wildcards_are_searched_literally(): void
    {
        $this->movie(self::WORD.' 100% Real');
        $this->movie(self::WORD.' 100 Real');

        $this->assertSame([self::WORD.' 100% Real'], $this->titles($this->search(['q' => self::WORD.' 100%'])));
    }

    public function test_invalid_filters_get_422(): void
    {
        foreach ([['q' => 'a'], ['q' => str_repeat('a', 101)], ['genre' => 'not-a-uuid'], ['year' => 'soon'], ['sort' => 'title'], ['page' => 0]] as $query) {
            $this->search($query);

            $this->seeStatusCode(422);
            $this->assertArrayHasKey(array_key_first($query), json_decode($this->response->getContent(), true)['errors']);
        }

        Http::assertNothingSent();
    }
}
