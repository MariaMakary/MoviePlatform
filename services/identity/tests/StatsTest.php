<?php

namespace Tests;

use App\Models\User;
use App\Services\TokenService;
use Illuminate\Support\Carbon;
use Laravel\Lumen\Testing\DatabaseTransactions;

class StatsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $fixtures = base_path('tests/Fixtures/jwt');
        config([
            'jwt.private_key' => file_get_contents($fixtures.'/test-private.pem'),
            'jwt.public_key_path' => $fixtures.'/test-public.pem',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function headers(User $user): array
    {
        $this->app['auth']->forgetGuards();

        return ['Authorization' => 'Bearer '.app(TokenService::class)->issue($user)->token];
    }

    private function stats(User|array $user): array
    {
        $this->json('GET', '/api/stats', [], is_array($user) ? $user : $this->headers($user));
        $this->seeStatusCode(200);

        return json_decode($this->response->getContent(), true)['data'];
    }

    private function signedUpAt(string $at, int $count = 1, array $state = []): void
    {
        User::factory()->count($count)->create(['created_at' => $at, 'updated_at' => $at, ...$state]);
    }

    public function test_counts_users_blocked_users_and_admins(): void
    {
        $admin = User::factory()->admin()->create();
        $before = $this->stats($admin);

        User::factory()->count(3)->create();
        User::factory()->count(2)->blocked()->create();
        User::factory()->admin()->create();

        $after = $this->stats($admin);

        $this->assertSame($before['total_users'] + 6, $after['total_users']);
        $this->assertSame($before['blocked_users'] + 2, $after['blocked_users']);
        $this->assertSame($before['admins'] + 1, $after['admins']);
    }

    public function test_signups_cover_the_last_30_days_with_zero_for_empty_days(): void
    {
        $admin = User::factory()->admin()->create(['created_at' => '2031-01-01T00:00:00Z']);
        $headers = $this->headers($admin);
        Carbon::setTestNow(Carbon::parse('2031-03-30T15:00:00Z'));

        $this->signedUpAt('2031-02-28T23:59:59Z');
        $this->signedUpAt('2031-03-01T00:00:00Z', 2);
        $this->signedUpAt('2031-03-15T12:00:00Z', 3, ['is_blocked' => true]);
        $this->signedUpAt('2031-03-30T14:00:00Z');
        $this->signedUpAt('2031-03-31T09:00:00Z');

        $signups = $this->stats($headers)['signups'];

        $this->assertCount(30, $signups);
        $this->assertSame(['date' => '2031-03-01', 'count' => 2], $signups[0]);
        $this->assertSame(['date' => '2031-03-30', 'count' => 1], $signups[29]);
        $this->assertSame(['date' => '2031-03-15', 'count' => 3], $signups[14]);
        $this->assertSame(['date' => '2031-03-02', 'count' => 0], $signups[1]);
        $this->assertSame(27, collect($signups)->where('count', 0)->count());
        $this->assertSame(range(0, 29), array_keys($signups));
        $this->assertSame(
            collect(range(0, 29))->map(fn (int $i) => Carbon::parse('2031-03-01')->addDays($i)->toDateString())->all(),
            array_column($signups, 'date'),
        );
    }

    public function test_a_normal_user_gets_403_and_a_guest_401(): void
    {
        $this->json('GET', '/api/stats', [], $this->headers(User::factory()->create()));
        $this->seeStatusCode(403);
        $this->seeJsonEquals(['message' => 'This action is unauthorized.']);

        $this->app['auth']->forgetGuards();
        $this->json('GET', '/api/stats');
        $this->seeStatusCode(401);
    }
}
