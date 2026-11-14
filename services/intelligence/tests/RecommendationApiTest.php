<?php

namespace Tests;

use App\Models\Recommendation;
use App\Models\UserTasteProfile;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Laravel\Lumen\Testing\DatabaseTransactions;

class RecommendationApiTest extends TestCase
{
    use DatabaseTransactions;

    private const ME = '0193a7c0-1111-7aaa-8bbb-000000000001';

    private const OTHER = '0193a7c0-2222-7aaa-8bbb-000000000002';

    private const SCI_FI = '0193a7b0-2222-7aaa-8bbb-000000000010';

    private const MOVIE = '0193a7c1-5555-7aaa-8bbb-000000000001';

    private const OLD_MOVIE = '0193a7c1-5555-7aaa-8bbb-000000000099';

    private bool $catalogDown = false;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'jwt.public_key_path' => base_path('tests/Fixtures/jwt/test-public.pem'),
            'services.catalog.url' => 'http://catalog.test',
            'internal.service_token' => 'test-service-token',
        ]);

        Http::fake(function (Request $request) {
            if ($this->catalogDown) {
                return Http::response(['message' => 'Server error.'], 500);
            }

            return match (parse_url($request->url(), PHP_URL_PATH)) {
                '/api/genres' => Http::response(['data' => [['id' => self::SCI_FI, 'name' => 'Science Fiction']]]),
                '/api/internal/discover' => Http::response(['data' => [[
                    'id' => self::MOVIE, 'title' => 'Arrival', 'poster_url' => null, 'year' => 2016, 'release_date' => '2016-11-11',
                    'vote_average' => 7.6, 'genres' => ['Science Fiction'],
                ]]]),
                default => Http::response(['data' => ['genre_ids' => [], 'cast_ids' => [], 'director_ids' => []]]),
            };
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function auth(string $userId = self::ME, string $role = 'user'): array
    {
        $token = JWT::encode(
            ['sub' => $userId, 'role' => $role, 'name' => 'Tonio', 'exp' => time() + 1800],
            file_get_contents(base_path('tests/Fixtures/jwt/test-private.pem')),
            'RS256',
        );

        return ['Authorization' => "Bearer $token"];
    }

    // Arrival: full genre match 3 + vote 7.6 × 0.5 = 6.8, divided by the highest raw score 12 = 0.5667.
    private function sciFiFan(string $userId = self::ME): void
    {
        UserTasteProfile::forceCreate(['user_id' => $userId, 'genre_weights' => [self::SCI_FI => 4]]);
    }

    public function test_a_user_gets_their_recommendations_best_first(): void
    {
        $this->sciFiFan();

        $this->get('/api/recommendations/'.self::ME, $this->auth())
            ->seeStatusCode(200)
            ->seeJsonEquals(['data' => [['movie_id' => self::MOVIE, 'score' => 0.5667, 'reason' => 'You like Science Fiction']]]);
    }

    public function test_another_users_recommendations_are_forbidden(): void
    {
        $this->get('/api/recommendations/'.self::OTHER, $this->auth())->seeStatusCode(403);

        Http::assertNothingSent();
    }

    public function test_an_admin_can_read_anyones_recommendations(): void
    {
        $this->sciFiFan(self::OTHER);

        $this->get('/api/recommendations/'.self::OTHER, $this->auth(self::ME, 'admin'))
            ->seeStatusCode(200)
            ->seeJson(['movie_id' => self::MOVIE]);
    }

    public function test_a_new_user_gets_an_empty_list(): void
    {
        $this->get('/api/recommendations/'.self::ME, $this->auth())
            ->seeStatusCode(200)
            ->seeJsonEquals(['data' => []]);
    }

    public function test_fresh_results_are_served_without_recomputing(): void
    {
        $this->sciFiFan();
        Recommendation::forceCreate(['user_id' => self::ME, 'movie_id' => self::OLD_MOVIE, 'score' => 0.9, 'reason' => 'Because you liked Inception']);

        $this->get('/api/recommendations/'.self::ME, $this->auth())
            ->seeJsonEquals(['data' => [['movie_id' => self::OLD_MOVIE, 'score' => 0.9, 'reason' => 'Because you liked Inception']]]);

        Http::assertNothingSent();
    }

    public function test_results_older_than_an_hour_are_recomputed(): void
    {
        $this->sciFiFan();
        Carbon::setTestNow('2026-11-14T10:00:00Z');
        Recommendation::forceCreate(['user_id' => self::ME, 'movie_id' => self::OLD_MOVIE, 'score' => 0.9, 'reason' => 'Old pick']);

        Carbon::setTestNow('2026-11-14T11:01:00Z');
        $this->get('/api/recommendations/'.self::ME, $this->auth())
            ->seeStatusCode(200)
            ->seeJsonEquals(['data' => [['movie_id' => self::MOVIE, 'score' => 0.5667, 'reason' => 'You like Science Fiction']]]);
    }

    public function test_catalog_down_while_recomputing_returns_the_stored_results(): void
    {
        $this->sciFiFan();
        Carbon::setTestNow('2026-11-14T10:00:00Z');
        Recommendation::forceCreate(['user_id' => self::ME, 'movie_id' => self::OLD_MOVIE, 'score' => 0.9, 'reason' => 'Old pick']);
        Carbon::setTestNow('2026-11-14T12:00:00Z');
        $this->catalogDown = true;

        $this->get('/api/recommendations/'.self::ME, $this->auth())
            ->seeStatusCode(200)
            ->seeJsonEquals(['data' => [['movie_id' => self::OLD_MOVIE, 'score' => 0.9, 'reason' => 'Old pick']]]);
    }

    public function test_a_token_is_required_and_the_id_must_be_a_uuid(): void
    {
        $this->get('/api/recommendations/'.self::ME)->seeStatusCode(401);
        $this->get('/api/recommendations/not-a-uuid', $this->auth())->seeStatusCode(404);
    }
}
