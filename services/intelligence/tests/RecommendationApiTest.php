<?php

namespace Tests;

use App\Models\Recommendation;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use Laravel\Lumen\Testing\DatabaseTransactions;

class RecommendationApiTest extends TestCase
{
    use DatabaseTransactions;

    private const ME = '0193a7c0-1111-7aaa-8bbb-000000000001';

    private const OTHER = '0193a7c0-2222-7aaa-8bbb-000000000002';

    private const MOVIE = '0193a7c1-5555-7aaa-8bbb-000000000001';

    private const OTHER_MOVIE = '0193a7c1-5555-7aaa-8bbb-000000000002';

    protected function setUp(): void
    {
        parent::setUp();

        config(['jwt.public_key_path' => base_path('tests/Fixtures/jwt/test-public.pem')]);

        Http::fake();
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

    private function pick(string $userId, string $movieId, float $score, string $reason): void
    {
        Recommendation::forceCreate(['user_id' => $userId, 'movie_id' => $movieId, 'score' => $score, 'reason' => $reason]);
    }

    public function test_a_user_gets_their_stored_recommendations_best_first_without_calling_catalog(): void
    {
        $this->pick(self::ME, self::OTHER_MOVIE, 0.4, 'Popular in your genres');
        $this->pick(self::ME, self::MOVIE, 0.5667, 'You like Science Fiction');

        $this->get('/api/recommendations/'.self::ME, $this->auth())
            ->seeStatusCode(200)
            ->seeJsonEquals(['data' => [
                ['movie_id' => self::MOVIE, 'score' => 0.5667, 'reason' => 'You like Science Fiction'],
                ['movie_id' => self::OTHER_MOVIE, 'score' => 0.4, 'reason' => 'Popular in your genres'],
            ]]);

        Http::assertNothingSent();
    }

    public function test_another_users_recommendations_are_forbidden(): void
    {
        $this->get('/api/recommendations/'.self::OTHER, $this->auth())->seeStatusCode(403);
    }

    public function test_an_admin_can_read_anyones_recommendations(): void
    {
        $this->pick(self::OTHER, self::MOVIE, 0.5, 'You like Science Fiction');

        $this->get('/api/recommendations/'.self::OTHER, $this->auth(self::ME, 'admin'))
            ->seeStatusCode(200)
            ->seeJson(['movie_id' => self::MOVIE]);
    }

    public function test_a_user_without_recommendations_gets_an_empty_list(): void
    {
        $this->get('/api/recommendations/'.self::ME, $this->auth())
            ->seeStatusCode(200)
            ->seeJsonEquals(['data' => []]);

        Http::assertNothingSent();
    }

    public function test_a_token_is_required_and_the_id_must_be_a_uuid(): void
    {
        $this->get('/api/recommendations/'.self::ME)->seeStatusCode(401);
        $this->get('/api/recommendations/not-a-uuid', $this->auth())->seeStatusCode(404);
    }
}
