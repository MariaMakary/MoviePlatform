<?php

namespace Tests;

use Firebase\JWT\JWT;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Lumen\Testing\DatabaseTransactions;

class StatsApiTest extends TestCase
{
    use DatabaseTransactions;

    private const ADMIN = '0193a7c0-9999-7aaa-8bbb-000000000009';

    private const INCEPTION = '0193a7c1-3333-7aaa-8bbb-000000000100';

    private const ARRIVAL = '0193a7c1-3333-7aaa-8bbb-000000000200';

    private const DUNE = '0193a7c1-3333-7aaa-8bbb-000000000300';

    protected function setUp(): void
    {
        parent::setUp();

        config(['jwt.public_key_path' => base_path('tests/Fixtures/jwt/test-public.pem')]);

        DB::table('watch_history')->delete();
        DB::table('ratings')->delete();
        DB::table('reviews')->delete();
    }

    private function token(string $role = 'admin'): array
    {
        $this->app['auth']->forgetGuards();

        $token = JWT::encode(
            ['sub' => self::ADMIN, 'role' => $role, 'name' => 'Admin', 'exp' => time() + 1800],
            file_get_contents(base_path('tests/Fixtures/jwt/test-private.pem')),
            'RS256',
        );

        return ['Authorization' => "Bearer $token"];
    }

    private function stats(): array
    {
        $this->json('GET', '/api/stats', [], $this->token());
        $this->seeStatusCode(200);

        return json_decode($this->response->getContent(), true)['data'];
    }

    private function movie(int $number): string
    {
        return sprintf('0193a7c1-4444-7aaa-8bbb-%012d', $number);
    }

    private function user(int $number): string
    {
        return sprintf('0193a7c0-5555-7aaa-8bbb-%012d', $number);
    }

    private function watch(string $movieId, int $times): void
    {
        DB::table('watch_history')->insert(array_map(fn (int $i) => [
            'id' => (string) Str::uuid(),
            'user_id' => $this->user($i),
            'movie_id' => $movieId,
            'watched_at' => Carbon::now(),
        ], range(1, $times)));
    }

    private function rate(string $movieId, array $scores): void
    {
        DB::table('ratings')->insert(array_map(fn (int $score, int $i) => [
            'id' => (string) Str::uuid(),
            'user_id' => $this->user($i),
            'movie_id' => $movieId,
            'score' => $score,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ], $scores, array_keys($scores)));
    }

    private function review(?string $label, int $user): void
    {
        DB::table('reviews')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user($user),
            'movie_id' => self::INCEPTION,
            'body' => 'A review.',
            'sentiment_label' => $label,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    public function test_an_empty_platform_gives_zeros_and_empty_lists(): void
    {
        $this->assertSame([
            'reviews' => 0,
            'ratings' => 0,
            'sentiment' => ['positive' => 0, 'neutral' => 0, 'negative' => 0, 'not_analyzed' => 0],
            'most_watched' => [],
            'highest_rated' => [],
            'movie_counts' => [],
        ], $this->stats());
        $this->assertStringContainsString('"movie_counts":{}', $this->response->getContent());
    }

    public function test_totals_and_the_sentiment_distribution(): void
    {
        $this->review('positive', 1);
        $this->review('positive', 2);
        $this->review('negative', 3);
        $this->review(null, 4);
        $this->rate(self::INCEPTION, [5, 4, 3]);

        $stats = $this->stats();

        $this->assertSame(4, $stats['reviews']);
        $this->assertSame(3, $stats['ratings']);
        $this->assertSame(['positive' => 2, 'neutral' => 0, 'negative' => 1, 'not_analyzed' => 1], $stats['sentiment']);
    }

    public function test_the_ten_most_watched_movies_by_number_of_watches(): void
    {
        $this->watch(self::ARRIVAL, 5);
        $this->watch(self::INCEPTION, 7);
        foreach (range(1, 10) as $number) {
            $this->watch($this->movie($number), 1);
        }

        $mostWatched = $this->stats()['most_watched'];

        $this->assertCount(10, $mostWatched);
        $this->assertSame(['movie_id' => self::INCEPTION, 'watches' => 7], $mostWatched[0]);
        $this->assertSame(['movie_id' => self::ARRIVAL, 'watches' => 5], $mostWatched[1]);
    }

    public function test_a_movie_with_fewer_than_three_ratings_is_never_highest_rated(): void
    {
        $this->rate(self::DUNE, [5, 5]);
        $this->rate(self::INCEPTION, [5, 4, 4]);
        $this->rate(self::ARRIVAL, [5, 5, 4, 4]);

        $this->assertSame([
            ['movie_id' => self::ARRIVAL, 'average' => 4.5, 'ratings' => 4],
            ['movie_id' => self::INCEPTION, 'average' => 4.33, 'ratings' => 3],
        ], $this->stats()['highest_rated']);
    }

    public function test_highest_rated_keeps_the_top_ten(): void
    {
        foreach (range(1, 11) as $number) {
            $this->rate($this->movie($number), [5, 5, $number > 10 ? 1 : 5]);
        }

        $highestRated = $this->stats()['highest_rated'];

        $this->assertCount(10, $highestRated);
        $this->assertNotContains($this->movie(11), array_column($highestRated, 'movie_id'));
    }

    public function test_movie_counts_add_watches_and_ratings_per_movie(): void
    {
        $this->watch(self::INCEPTION, 3);
        $this->rate(self::INCEPTION, [5, 4]);
        $this->rate(self::ARRIVAL, [3]);

        $this->assertSame([self::INCEPTION => 5, self::ARRIVAL => 1], $this->stats()['movie_counts']);
    }

    public function test_movie_counts_keep_the_200_most_active_movies(): void
    {
        $this->watch(self::INCEPTION, 2);
        foreach (range(1, 200) as $number) {
            $this->watch($this->movie($number), 1);
        }

        $counts = $this->stats()['movie_counts'];

        $this->assertCount(200, $counts);
        $this->assertSame(2, $counts[self::INCEPTION]);
    }

    public function test_only_admins_see_the_stats(): void
    {
        $this->json('GET', '/api/stats', [], $this->token('user'));
        $this->seeStatusCode(403);

        $this->app['auth']->forgetGuards();
        $this->json('GET', '/api/stats');
        $this->seeStatusCode(401);
    }
}
