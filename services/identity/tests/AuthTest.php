<?php

namespace Tests;

use App\Models\User;
use App\Services\TokenService;
use Carbon\CarbonImmutable;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Laravel\Lumen\Testing\DatabaseTransactions;
use MoviePlatform\Common\Auth\Role;

class AuthTest extends TestCase
{
    use DatabaseTransactions;

    private string $fixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtures = base_path('tests/Fixtures/jwt');
        config([
            'jwt.private_key' => file_get_contents($this->fixtures.'/test-private.pem'),
            'jwt.public_key_path' => $this->fixtures.'/test-public.pem',
        ]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    private function user(array $attributes = []): User
    {
        return User::factory()->create(['name' => 'Maria Makary', 'email' => 'maria@example.com', ...$attributes]);
    }

    private function login(string $email = 'maria@example.com', string $password = 'password'): void
    {
        $this->json('POST', '/api/login', ['email' => $email, 'password' => $password]);
    }

    private function claims(string $token): object
    {
        return JWT::decode($token, new Key(file_get_contents($this->fixtures.'/test-public.pem'), 'RS256'));
    }

    private function tokenIssuedAt(User $user, CarbonImmutable $at): string
    {
        CarbonImmutable::setTestNow($at);
        $token = app(TokenService::class)->issue($user)->token;
        CarbonImmutable::setTestNow();

        return $token;
    }

    private function bearer(string $token): array
    {
        return ['Authorization' => "Bearer $token"];
    }

    public function test_login_returns_a_30_minute_token_and_the_user(): void
    {
        $user = $this->user();

        $this->login();

        $this->seeStatusCode(200)->seeJson(['user' => [
            'id' => $user->id, 'name' => 'Maria Makary', 'email' => 'maria@example.com', 'avatar' => null, 'role' => 'user',
        ]]);
        $data = $this->response->getData(true)['data'];
        $claims = $this->claims($data['token']);
        $this->assertSame($user->id, $claims->sub);
        $this->assertSame(1800, $claims->exp - $claims->iat);
        $this->assertSame(CarbonImmutable::createFromTimestampUTC($claims->exp)->toIso8601ZuluString(), $data['expires_at']);
    }

    public function test_the_login_token_passes_the_jwt_middleware(): void
    {
        $user = $this->user();
        $this->login();
        $token = $this->response->getData(true)['data']['token'];

        $this->get('/api/me', $this->bearer($token))
            ->seeStatusCode(200)
            ->seeJsonEquals(['data' => [
                'id' => $user->id, 'name' => 'Maria Makary', 'email' => 'maria@example.com', 'avatar' => null, 'role' => 'user',
            ]]);
    }

    public function test_login_ignores_the_case_of_the_email(): void
    {
        $this->user(['email' => 'Maria@Example.com']);

        $this->login('MARIA@example.COM');

        $this->seeStatusCode(200);
    }

    public function test_a_wrong_password_an_unknown_email_and_a_social_account_get_the_same_401(): void
    {
        $this->user();
        $this->user(['email' => 'social@example.com', 'password' => null]);

        foreach ([['maria@example.com', 'wrong-password'], ['nobody@example.com', 'password'], ['social@example.com', 'password']] as [$email, $password]) {
            $this->login($email, $password);

            $this->seeStatusCode(401)->seeJsonEquals(['message' => 'Invalid credentials.']);
        }
    }

    public function test_a_blocked_user_cannot_log_in(): void
    {
        $this->user(['is_blocked' => true]);

        $this->login();

        $this->seeStatusCode(403)->seeJsonEquals(['message' => 'Account blocked.']);
    }

    public function test_login_validates_its_fields(): void
    {
        $this->json('POST', '/api/login', ['email' => 'not-an-email'])
            ->seeStatusCode(422)
            ->seeJsonStructure(['message', 'errors' => ['email', 'password']]);
    }

    public function test_logout_returns_204(): void
    {
        $token = $this->tokenIssuedAt($this->user(), CarbonImmutable::now());

        $this->json('POST', '/api/logout', [], $this->bearer($token))->seeStatusCode(204);
        $this->assertSame('', $this->response->getContent());
    }

    public function test_logout_needs_a_token(): void
    {
        $this->json('POST', '/api/logout')->seeStatusCode(401)->seeJsonEquals(['message' => 'Unauthenticated.']);
    }

    public function test_me_needs_a_token(): void
    {
        $this->get('/api/me')->seeStatusCode(401)->seeJsonEquals(['message' => 'Unauthenticated.']);
    }

    public function test_refresh_returns_a_token_with_a_later_expiry(): void
    {
        $user = $this->user();
        $old = $this->tokenIssuedAt($user, CarbonImmutable::now()->subMinutes(5));

        $this->json('POST', '/api/token/refresh', [], $this->bearer($old))->seeStatusCode(200);

        $new = $this->response->getData(true)['data'];
        $this->assertGreaterThan($this->claims($old)->exp, $this->claims($new['token'])->exp);
        $this->assertSame($user->id, $new['user']['id']);
    }

    public function test_refresh_puts_the_current_name_and_role_in_the_token(): void
    {
        $user = $this->user();
        $token = $this->tokenIssuedAt($user, CarbonImmutable::now());
        $user->forceFill(['name' => 'Maria M.', 'role' => Role::Admin])->save();

        $this->json('POST', '/api/token/refresh', [], $this->bearer($token))->seeStatusCode(200);

        $claims = $this->claims($this->response->getData(true)['data']['token']);
        $this->assertSame('Maria M.', $claims->name);
        $this->assertSame('admin', $claims->role);
    }

    public function test_an_expired_token_cannot_be_refreshed(): void
    {
        $token = $this->tokenIssuedAt($this->user(), CarbonImmutable::now()->subMinutes(31));

        $this->json('POST', '/api/token/refresh', [], $this->bearer($token))
            ->seeStatusCode(401)
            ->seeJsonEquals(['message' => 'Unauthenticated.']);
    }

    public function test_a_blocked_user_cannot_refresh_or_read_me(): void
    {
        $user = $this->user();
        $token = $this->tokenIssuedAt($user, CarbonImmutable::now());
        $user->forceFill(['is_blocked' => true])->save();

        $this->json('POST', '/api/token/refresh', [], $this->bearer($token))
            ->seeStatusCode(403)
            ->seeJsonEquals(['message' => 'Account blocked.']);
        $this->get('/api/me', $this->bearer($token))->seeStatusCode(403);
    }

    public function test_a_deleted_user_cannot_refresh_or_read_me(): void
    {
        $user = $this->user();
        $token = $this->tokenIssuedAt($user, CarbonImmutable::now());
        $user->delete();

        $this->json('POST', '/api/token/refresh', [], $this->bearer($token))
            ->seeStatusCode(401)
            ->seeJsonEquals(['message' => 'Unauthenticated.']);
        $this->get('/api/me', $this->bearer($token))->seeStatusCode(401);
    }
}
