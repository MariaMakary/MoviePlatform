<?php

namespace Tests;

use App\Models\MovieNight;
use Firebase\JWT\JWT;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Lumen\Testing\DatabaseTransactions;
use MoviePlatform\Common\MovieNights\MovieNightStatus;

class StatsApiTest extends TestCase
{
    use DatabaseTransactions;

    private const ADMIN = '0193a7c0-9999-7aaa-8bbb-000000000009';

    private const MARIA = '0193a7c0-1111-7aaa-8bbb-000000000001';

    private const JOE = '0193a7c0-2222-7aaa-8bbb-000000000002';

    private const TONIO = '0193a7c0-3333-7aaa-8bbb-000000000003';

    protected function setUp(): void
    {
        parent::setUp();

        config(['jwt.public_key_path' => base_path('tests/Fixtures/jwt/test-public.pem')]);
        Carbon::setTestNow('2026-11-18T12:00:00Z');

        // Inside the test transaction: local data never changes the numbers.
        foreach (['activities', 'messages', 'rooms', 'follows', 'movie_nights'] as $table) {
            DB::table($table)->delete();
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
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

    private function activity(string $userId, string $at, bool $authorBlocked = false): void
    {
        DB::table('activities')->insert([
            'id' => (string) Str::uuid(),
            'event_id' => (string) Str::uuid(),
            'user_id' => $userId,
            'type' => 'rating',
            'subject_id' => (string) Str::uuid(),
            'movie_id' => (string) Str::uuid(),
            'data' => '{"score": 4}',
            'author_blocked' => $authorBlocked,
            'created_at' => $at,
        ]);
    }

    private function message(string $roomId, string $at): void
    {
        DB::table('messages')->insert(['id' => (string) Str::uuid(), 'room_id' => $roomId, 'user_id' => self::JOE, 'body' => 'Hi', 'created_at' => $at]);
    }

    private function user(int $number): string
    {
        return sprintf('0193a7c0-5555-7aaa-8bbb-%012d', $number);
    }

    public function test_a_normal_user_gets_403_and_a_guest_401(): void
    {
        $this->json('GET', '/api/stats', [], $this->token('user'))->seeStatusCode(403);
        $this->assertSame('This action is unauthorized.', json_decode($this->response->getContent(), true)['message']);

        $this->app['auth']->forgetGuards();
        $this->json('GET', '/api/stats')->seeStatusCode(401);
    }

    public function test_an_empty_platform_gives_zeros_and_30_empty_days(): void
    {
        $stats = $this->stats();

        $this->assertSame(['activity_per_day', 'follows', 'movie_nights', 'messages_last_7_days', 'most_active_users'], array_keys($stats));
        $this->assertCount(30, $stats['activity_per_day']);
        $this->assertSame(['date' => '2026-10-20', 'count' => 0], $stats['activity_per_day'][0]);
        $this->assertSame(['date' => '2026-11-18', 'count' => 0], $stats['activity_per_day'][29]);
        $this->assertSame(0, $stats['follows']);
        $this->assertSame(['created' => 0, 'finalized' => 0], $stats['movie_nights']);
        $this->assertSame(0, $stats['messages_last_7_days']);
        $this->assertSame([], $stats['most_active_users']);
    }

    public function test_activity_per_day_counts_the_last_30_utc_days_oldest_first(): void
    {
        $this->activity(self::MARIA, '2026-10-19 23:59:59');   // day 31 ago: left out
        $this->activity(self::MARIA, '2026-10-20 00:00:00');   // first day
        $this->activity(self::JOE, '2026-11-17 08:00:00');
        $this->activity(self::JOE, '2026-11-17 23:59:59');
        $this->activity(self::TONIO, '2026-11-18 11:00:00', authorBlocked: true);   // today, a blocked author counts

        $days = collect($this->stats()['activity_per_day']);

        $this->assertSame(range(0, 29), $days->keys()->all());
        $this->assertSame('2026-10-20', $days->first()['date']);
        $this->assertSame('2026-11-18', $days->last()['date']);
        $this->assertSame(['2026-10-20' => 1, '2026-11-17' => 2, '2026-11-18' => 1], $days->where('count', '>', 0)->pluck('count', 'date')->all());
        $this->assertSame(4, $days->sum('count'));
    }

    public function test_follows_movie_nights_and_messages_are_counted(): void
    {
        foreach ([[self::MARIA, self::JOE], [self::JOE, self::MARIA], [self::TONIO, self::JOE]] as [$follower, $following]) {
            DB::table('follows')->insert(['follower_id' => $follower, 'following_id' => $following, 'created_at' => '2026-11-01 10:00:00']);
        }

        MovieNight::factory()->count(2)->create();
        foreach ([MovieNightStatus::Finalized, MovieNightStatus::Cancelled] as $status) {
            $night = MovieNight::factory()->create();
            $night->status = $status;
            $night->save();
        }

        $roomId = (string) Str::uuid();
        DB::table('rooms')->insert(['id' => $roomId, 'movie_id' => (string) Str::uuid(), 'name' => 'Arrival', 'created_at' => '2026-11-01 10:00:00']);
        $this->message($roomId, '2026-11-11 11:59:59');   // just over 7 days ago: left out
        $this->message($roomId, '2026-11-11 12:00:00');
        $this->message($roomId, '2026-11-18 11:59:00');

        $stats = $this->stats();

        $this->assertSame(3, $stats['follows']);
        $this->assertSame(['created' => 4, 'finalized' => 1], $stats['movie_nights']);
        $this->assertSame(2, $stats['messages_last_7_days']);
    }

    public function test_the_10_most_active_users_most_first_with_ties_by_id(): void
    {
        // users 1..12: user n has n activities, all time (not only the last 30 days)
        foreach (range(1, 12) as $n) {
            foreach (range(1, $n) as $i) {
                $this->activity($this->user($n), '2026-01-01 10:00:00');
            }
        }
        $this->activity(self::MARIA, '2026-11-18 10:00:00');
        $this->activity(self::MARIA, '2026-11-18 10:00:00');
        $this->activity(self::MARIA, '2026-11-18 10:00:00');   // 3, tied with user 3: MARIA's id sorts first

        $top = $this->stats()['most_active_users'];

        $this->assertCount(10, $top);
        $this->assertSame(['user_id' => $this->user(12), 'activities' => 12], $top[0]);
        $this->assertSame([12, 11, 10, 9, 8, 7, 6, 5, 4, 3], array_column($top, 'activities'));
        $this->assertSame(self::MARIA, $top[9]['user_id']);
    }
}
