<?php

namespace Tests;

use App\Enums\CreditRole;
use App\Models\Genre;
use App\Models\Movie;
use App\Models\Person;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Lumen\Testing\DatabaseTransactions;

class PersonPageTest extends TestCase
{
    use DatabaseTransactions;

    private const PERSON = 900600001;

    private const GENRE = 990301;

    private bool $tmdbDown = false;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.tmdb.key' => 'test-key']);
        Cache::flush();

        Http::fake(fn (Request $request) => $this->tmdbDown ? Http::response([], 503) : Http::response($this->tmdbPerson()));
    }

    private function tmdbPerson(): array
    {
        return [
            'id' => self::PERSON,
            'name' => 'Person From TMDB',
            'profile_path' => '/person.jpg',
            'biography' => 'Born somewhere.',
            'birthday' => '1970-07-30',
            'known_for_department' => 'Directing',
            'combined_credits' => [
                'cast' => [
                    ['id' => 900610001, 'media_type' => 'movie', 'title' => 'Acted Old', 'release_date' => '2001-01-01', 'character' => 'Hero', 'genre_ids' => [self::GENRE], 'vote_average' => 7.0, 'popularity' => 3],
                    ['id' => 900610002, 'media_type' => 'movie', 'title' => 'Acted New', 'release_date' => '2020-01-01', 'character' => '', 'genre_ids' => [], 'popularity' => 5],
                    ['id' => 900610003, 'media_type' => 'tv', 'name' => 'A TV show'],
                ],
                'crew' => [
                    ['id' => 900610004, 'media_type' => 'movie', 'title' => 'Directed', 'release_date' => '2010-07-15', 'job' => 'Director', 'genre_ids' => []],
                    ['id' => 900610005, 'media_type' => 'movie', 'title' => 'Produced', 'job' => 'Producer', 'genre_ids' => []],
                ],
            ],
        ];
    }

    private function person(array $attributes = []): Person
    {
        return Person::factory()->create(array_merge(['tmdb_id' => self::PERSON, 'name' => 'Local Name', 'biography' => null], $attributes));
    }

    private function data(): array
    {
        return json_decode($this->response->getContent(), true)['data'];
    }

    public function test_first_open_syncs_the_person_and_creates_missing_movies(): void
    {
        $genre = Genre::factory()->create(['tmdb_id' => self::GENRE]);
        $existing = Movie::factory()->create(['tmdb_id' => 900610004, 'title' => 'Already local']);
        $person = $this->person();

        $this->get("/api/people/{$person->id}")->seeStatusCode(200);

        $data = $this->data();
        $this->assertSame($person->id, $data['id']);
        $this->assertArrayNotHasKey('tmdb_id', $data);
        $this->assertSame('Person From TMDB', $data['name']);
        $this->assertSame('https://image.tmdb.org/t/p/w185/person.jpg', $data['photo_url']);
        $this->assertSame('Born somewhere.', $data['biography']);
        $this->assertSame('1970-07-30', $data['birthday']);
        $this->assertSame('Directing', $data['known_for']);

        // Acting: newest first, TV shows left out; directing: crew jobs other than Director left out.
        $this->assertSame(['Acted New', 'Acted Old'], array_column($data['filmography']['acting'], 'title'));
        $this->assertSame([$genre->name], $data['filmography']['acting'][1]['genres']);
        $this->assertSame([$existing->id], array_column($data['filmography']['directing'], 'id'));
        $this->assertSame('Already local', $data['filmography']['directing'][0]['title']);
        $this->assertTrue(Str::isUuid($data['filmography']['acting'][0]['id']));
        $this->assertSame(['id', 'title', 'poster_url', 'year', 'release_date', 'vote_average', 'genres'], array_keys($data['filmography']['acting'][0]));

        $this->assertNull(Movie::where('tmdb_id', 900610003)->first());
        $this->assertNull(Movie::where('tmdb_id', 900610005)->first());
        $this->assertSame('Hero', $person->movies()->where('title', 'Acted Old')->first()->pivot->character);
        $this->assertSame(CreditRole::Director, $person->movies()->where('title', 'Already local')->first()->pivot->role);
    }

    public function test_a_person_opened_twice_calls_tmdb_once(): void
    {
        $person = $this->person();

        $this->get("/api/people/{$person->id}")->seeStatusCode(200);
        $this->get("/api/people/{$person->id}")->seeStatusCode(200);

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/person/'.self::PERSON)
            && str_contains($request->url(), 'combined_credits'));
        $this->assertCount(2, $this->data()['filmography']['acting']);
    }

    public function test_a_credit_saved_by_a_movie_sync_keeps_its_cast_order(): void
    {
        $person = $this->person();
        $movie = Movie::factory()->create(['tmdb_id' => 900610001]);
        $movie->cast()->attach($person->id, ['character' => 'Old name', 'cast_order' => 2]);

        $this->get("/api/people/{$person->id}")->seeStatusCode(200);

        $credit = $movie->cast()->first()->pivot;
        $this->assertSame(2, $credit->cast_order);
        $this->assertSame('Hero', $credit->character);
        $this->assertSame(1, $movie->cast()->count());
    }

    public function test_with_tmdb_down_the_local_data_is_returned_and_retried_next_time(): void
    {
        $this->tmdbDown = true;
        $person = $this->person();
        $movie = Movie::factory()->create(['title' => 'Local credit']);
        $movie->directors()->attach($person->id);

        $this->get("/api/people/{$person->id}")->seeStatusCode(200);

        $this->assertSame('Local Name', $this->data()['name']);
        $this->assertSame([], $this->data()['filmography']['acting']);
        $this->assertSame(['Local credit'], array_column($this->data()['filmography']['directing'], 'title'));

        $this->tmdbDown = false;
        $this->get("/api/people/{$person->id}")->seeStatusCode(200);

        Http::assertSentCount(2);
        $this->assertSame('Person From TMDB', $this->data()['name']);
    }

    public function test_a_person_without_tmdb_id_is_never_synced(): void
    {
        $person = $this->person(['tmdb_id' => null]);

        $this->get("/api/people/{$person->id}")->seeStatusCode(200);

        Http::assertNothingSent();
        $this->assertSame('Local Name', $this->data()['name']);
    }

    public function test_an_unknown_id_gives_404(): void
    {
        $this->get('/api/people/'.Str::uuid())->seeStatusCode(404)->seeJsonEquals(['message' => 'Person not found.']);
        $this->get('/api/people/not-a-uuid')->seeStatusCode(404);

        Http::assertNothingSent();
    }
}
