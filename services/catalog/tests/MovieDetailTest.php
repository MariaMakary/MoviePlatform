<?php

namespace Tests;

use App\Models\Genre;
use App\Models\Movie;
use App\Models\Person;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Lumen\Testing\DatabaseTransactions;

class MovieDetailTest extends TestCase
{
    use DatabaseTransactions;

    private const MOVIE = 900700001;

    private const GENRE = 990201;

    private bool $tmdbDown = false;

    private bool $tmdbMissing = false;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.tmdb.key' => 'test-key']);

        Http::fake(function (Request $request) {
            if ($this->tmdbDown) {
                return Http::response([], 503);
            }

            if ($this->tmdbMissing) {
                return Http::response(['status_code' => 34], 404);
            }

            return Http::response($this->tmdbDetails());
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function tmdbDetails(): array
    {
        return [
            'id' => self::MOVIE,
            'title' => 'Detail movie',
            'overview' => 'Full overview from TMDB.',
            'poster_path' => '/poster.jpg',
            'backdrop_path' => '/backdrop.jpg',
            'release_date' => '2010-07-15',
            'runtime' => 148,
            'vote_average' => 8.37,
            'popularity' => 83.9,
            'genres' => [['id' => self::GENRE, 'name' => 'Test Detail Genre']],
            'credits' => [
                'cast' => array_map(fn (int $i) => [
                    'id' => 900800000 + $i, 'name' => "Actor $i", 'character' => "Role $i", 'order' => $i,
                    'profile_path' => $i === 0 ? '/actor0.jpg' : null, 'known_for_department' => 'Acting',
                ], range(0, 17)),
                'crew' => [['id' => 900900000, 'name' => 'Detail Director', 'job' => 'Director', 'profile_path' => '/director.jpg', 'known_for_department' => 'Directing']],
            ],
            'videos' => ['results' => [['site' => 'YouTube', 'type' => 'Trailer', 'key' => 'trailer123', 'official' => true]]],
        ];
    }

    private function listMovie(array $attributes = []): Movie
    {
        return Movie::factory()->create(array_merge([
            'tmdb_id' => self::MOVIE,
            'title' => 'From the list',
            'overview' => 'Short overview.',
            'runtime' => null,
            'synced_at' => null,
        ], $attributes));
    }

    private function data(): array
    {
        return json_decode($this->response->getContent(), true)['data'];
    }

    public function test_a_movie_created_from_a_list_gets_its_full_details_on_first_open(): void
    {
        $movie = $this->listMovie();

        $this->get("/api/movies/{$movie->id}")->seeStatusCode(200);

        $data = $this->data();
        $this->assertSame($movie->id, $data['id']);
        $this->assertArrayNotHasKey('tmdb_id', $data);
        $this->assertSame('Detail movie', $data['title']);
        $this->assertSame('Full overview from TMDB.', $data['overview']);
        $this->assertSame('https://image.tmdb.org/t/p/w342/poster.jpg', $data['poster_url']);
        $this->assertSame('https://image.tmdb.org/t/p/w1280/backdrop.jpg', $data['backdrop_url']);
        $this->assertSame(2010, $data['year']);
        $this->assertSame('2010-07-15', $data['release_date']);
        $this->assertSame(148, $data['runtime']);
        $this->assertSame(8.4, $data['vote_average']);
        $this->assertSame('trailer123', $data['trailer_key']);

        $genre = Genre::where('tmdb_id', self::GENRE)->firstOrFail();
        $this->assertSame([['id' => $genre->id, 'name' => 'Test Detail Genre']], $data['genres']);

        $director = Person::where('tmdb_id', 900900000)->firstOrFail();
        $this->assertSame([['id' => $director->id, 'name' => 'Detail Director', 'photo_url' => 'https://image.tmdb.org/t/p/w185/director.jpg']], $data['directors']);

        $this->assertCount(15, $data['cast']);
        $this->assertSame(['Actor 0', 'Actor 1', 'Actor 2'], array_column(array_slice($data['cast'], 0, 3), 'name'));
        $this->assertSame('Role 0', $data['cast'][0]['character']);
        $this->assertSame('https://image.tmdb.org/t/p/w185/actor0.jpg', $data['cast'][0]['photo_url']);
        $this->assertNull($data['cast'][1]['photo_url']);
        $this->assertTrue(Str::isUuid($data['cast'][0]['id']));

        $this->assertNotNull($movie->fresh()->synced_at);
    }

    public function test_a_recently_synced_movie_is_served_without_calling_tmdb(): void
    {
        $movie = $this->listMovie(['synced_at' => Carbon::now()->subDays(6)]);

        $this->get("/api/movies/{$movie->id}")->seeStatusCode(200);

        Http::assertNothingSent();
        $this->assertSame('From the list', $this->data()['title']);
        $this->assertSame([], $this->data()['cast']);
    }

    public function test_a_movie_synced_more_than_7_days_ago_is_synced_again(): void
    {
        $movie = $this->listMovie(['synced_at' => Carbon::now()->subDays(8)]);

        $this->get("/api/movies/{$movie->id}")->seeStatusCode(200);

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/movie/'.self::MOVIE));
        $this->assertSame('Detail movie', $this->data()['title']);
    }

    public function test_with_tmdb_down_the_local_copy_is_returned(): void
    {
        $this->tmdbDown = true;
        $movie = $this->listMovie();

        $this->get("/api/movies/{$movie->id}")->seeStatusCode(200);

        $data = $this->data();
        $this->assertSame('From the list', $data['title']);
        $this->assertSame('Short overview.', $data['overview']);
        $this->assertNull($data['runtime']);
        $this->assertSame([], $data['cast']);
        $this->assertNull($movie->fresh()->synced_at, 'The movie must be synced again on the next open.');
    }

    public function test_a_movie_removed_from_tmdb_keeps_its_local_copy(): void
    {
        $this->tmdbMissing = true;
        $movie = $this->listMovie();

        $this->get("/api/movies/{$movie->id}")->seeStatusCode(200);

        $this->assertSame('From the list', $this->data()['title']);
    }

    public function test_a_local_movie_is_never_synced(): void
    {
        $movie = Movie::factory()->local()->create(['title' => 'Added by an admin']);

        $this->get("/api/movies/{$movie->id}")->seeStatusCode(200);

        Http::assertNothingSent();
        $this->assertSame('Added by an admin', $this->data()['title']);
    }

    public function test_an_edited_movie_keeps_its_edits_when_synced(): void
    {
        $movie = $this->listMovie(['title' => 'Title fixed by an admin', 'is_edited' => true]);

        $this->get("/api/movies/{$movie->id}")->seeStatusCode(200);

        $this->assertSame('Title fixed by an admin', $this->data()['title']);
        $this->assertCount(15, $this->data()['cast']);
    }

    public function test_an_unknown_id_gives_404(): void
    {
        $this->get('/api/movies/'.Str::uuid())->seeStatusCode(404)->seeJsonEquals(['message' => 'Movie not found.']);
        $this->get('/api/movies/not-a-uuid')->seeStatusCode(404);

        Http::assertNothingSent();
    }

    public function test_no_token_is_needed(): void
    {
        $movie = $this->listMovie(['synced_at' => Carbon::now()]);

        $this->get("/api/movies/{$movie->id}")->seeStatusCode(200);
    }
}
