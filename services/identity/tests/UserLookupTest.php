<?php

namespace Tests;

use App\Models\User;
use App\Services\TokenService;
use Illuminate\Support\Str;
use Laravel\Lumen\Testing\DatabaseTransactions;

class UserLookupTest extends TestCase
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

    private function user(array $attributes = []): User
    {
        return User::factory()->create($attributes)->fresh();
    }

    private function bearer(): array
    {
        return ['Authorization' => 'Bearer '.app(TokenService::class)->issue($this->user())->token];
    }

    private function publicJson(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'avatar' => $user->avatar,
            'bio' => $user->bio,
            'created_at' => $user->created_at->toIso8601ZuluString(),
        ];
    }

    private function search(string $query): void
    {
        $this->get('/api/users/search?q='.urlencode($query), $this->bearer());
    }

    private function foundNames(): array
    {
        return array_column($this->response->getData(true)['data'], 'name');
    }

    public function test_a_guest_sees_a_user_without_email_or_role(): void
    {
        $user = User::factory()->admin()->create(['avatar' => 'https://example.com/maria.png', 'bio' => 'Sci-fi first.'])->fresh();

        $this->get("/api/users/{$user->id}")
            ->seeStatusCode(200)
            ->seeJsonEquals(['data' => $this->publicJson($user)]);

        $this->assertStringNotContainsString($user->email, $this->response->getContent());
    }

    public function test_an_unknown_id_gets_404(): void
    {
        $this->get('/api/users/'.Str::uuid())
            ->seeStatusCode(404)
            ->seeJsonEquals(['message' => 'User not found.']);
    }

    public function test_an_id_that_is_not_a_uuid_gets_404(): void
    {
        $this->get('/api/users/not-a-uuid')->seeStatusCode(404);
    }

    public function test_bulk_lookup_returns_only_existing_users_in_the_order_of_the_ids(): void
    {
        $first = $this->user();
        $second = $this->user();
        $unknown = (string) Str::uuid();

        $this->get("/api/users?ids={$second->id},{$unknown},{$first->id}")
            ->seeStatusCode(200)
            ->seeJsonEquals(['data' => [$this->publicJson($second), $this->publicJson($first)]]);

        $this->assertStringNotContainsString('email', $this->response->getContent());
    }

    public function test_bulk_lookup_returns_a_repeated_id_once_in_any_letter_case(): void
    {
        $user = $this->user();

        $this->get("/api/users?ids={$user->id},".strtoupper($user->id).", {$user->id}")
            ->seeStatusCode(200)
            ->seeJsonEquals(['data' => [$this->publicJson($user)]]);
    }

    public function test_bulk_lookup_with_only_unknown_ids_is_empty(): void
    {
        $this->get('/api/users?ids='.Str::uuid())
            ->seeStatusCode(200)
            ->seeJsonEquals(['data' => []]);
    }

    public function test_bulk_lookup_accepts_100_ids_but_not_101(): void
    {
        $ids = array_map(fn () => (string) Str::uuid(), range(1, 101));

        $this->get('/api/users?ids='.implode(',', array_slice($ids, 0, 100)))->seeStatusCode(200);
        $this->get('/api/users?ids='.implode(',', $ids))->seeStatusCode(422);
    }

    public function test_bulk_lookup_needs_valid_ids(): void
    {
        foreach (['', '?ids=', '?ids=,', '?ids=not-a-uuid', '?ids[]='.Str::uuid()] as $query) {
            $this->get("/api/users$query")->seeStatusCode(422);
        }
    }

    public function test_search_needs_a_token(): void
    {
        $this->get('/api/users/search?q=maria')
            ->seeStatusCode(401)
            ->seeJsonEquals(['message' => 'Unauthenticated.']);
    }

    public function test_search_needs_at_least_2_characters(): void
    {
        foreach (['', '?q=', '?q=m'] as $query) {
            $this->get("/api/users/search$query", $this->bearer())->seeStatusCode(422);

            $this->assertArrayHasKey('q', $this->response->getData(true)['errors']);
        }
    }

    public function test_search_matches_part_of_the_name_in_any_letter_case_and_skips_blocked_users(): void
    {
        $maria = $this->user(['name' => 'Zqxa Makary']);
        $tonio = $this->user(['name' => 'tonio zqxa']);
        $this->user(['name' => 'ZQXA Blocked', 'is_blocked' => true]);
        $this->user(['name' => 'Zqx Other']);

        $this->search('QXA');

        $this->seeStatusCode(200);
        $this->assertEqualsCanonicalizing(
            [$this->publicJson($maria), $this->publicJson($tonio)],
            $this->response->getData(true)['data'],
        );
        $this->assertStringNotContainsString('email', $this->response->getContent());
    }

    public function test_search_returns_at_most_10_users(): void
    {
        foreach (range(1, 12) as $i) {
            $this->user(['name' => sprintf('Zqxb %02d', $i)]);
        }

        $this->search('zqxb');

        $this->seeStatusCode(200);
        $this->assertCount(10, $this->foundNames());
    }

    public function test_search_treats_percent_and_underscore_as_plain_characters(): void
    {
        $this->user(['name' => 'Zqxc_Lee']);
        $this->user(['name' => 'ZqxcXLee']);
        $this->user(['name' => '100% Zqxd']);
        $this->user(['name' => '100 Zqxd']);

        $this->search('zqxc_l');
        $this->assertSame(['Zqxc_Lee'], $this->foundNames());

        $this->search('0% zqxd');
        $this->assertSame(['100% Zqxd'], $this->foundNames());
    }
}
