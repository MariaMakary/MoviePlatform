<?php

namespace MoviePlatform\Common\Tests;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Route;
use MoviePlatform\Common\CommonServiceProvider;
use MoviePlatform\Common\Http\Middleware\ThrottleRequests;
use Orchestra\Testbench\TestCase;

class ThrottleRequestsTest extends TestCase
{
    private const MARIA = '0193a7c0-1111-7aaa-8bbb-000000000001';

    private const JOY = '0193a7c0-1111-7aaa-8bbb-000000000002';

    protected function getPackageProviders($app): array
    {
        return [CommonServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('jwt.public_key_path', __DIR__.'/Fixtures/jwt/test-public.pem');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('throttle.api', ['attempts' => 3, 'minutes' => 1, 'by' => 'user', 'clear_on_success' => false]);
        $app['config']->set('throttle.login', ['attempts' => 2, 'minutes' => 1, 'by' => 'email', 'clear_on_success' => true]);
    }

    protected function defineRoutes($router): void
    {
        Route::middleware(ThrottleRequests::class)->get('/api/things', fn () => ['ok' => true]);
        Route::middleware(ThrottleRequests::class.':login')->post('/api/login', fn () => request('password') === 'right'
            ? response()->json(['ok' => true])
            : response()->json(['message' => 'Invalid credentials.'], 401));
    }

    private function as(string $userId): array
    {
        $token = JWT::encode(['sub' => $userId, 'role' => 'user', 'name' => 'Test', 'exp' => time() + 600],
            file_get_contents(__DIR__.'/Fixtures/jwt/test-private.pem'), 'RS256');

        return ['Authorization' => "Bearer $token"];
    }

    public function test_api_routes_are_limited_per_user_with_retry_after(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->getJson('/api/things', $this->as(self::MARIA))->assertOk();
        }

        $response = $this->getJson('/api/things', $this->as(self::MARIA))->assertStatus(429);
        $this->assertStringStartsWith('Too many attempts. Try again in', $response->json('message'));
        $this->assertGreaterThan(0, (int) $response->headers->get('Retry-After'));

        $this->getJson('/api/things', $this->as(self::JOY))->assertOk();
    }

    public function test_requests_without_a_valid_token_are_not_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->getJson('/api/things')->assertOk();
            $this->getJson('/api/things', ['Authorization' => 'Bearer not-a-jwt'])->assertOk();
        }
    }

    public function test_email_limits_count_per_lowercase_email_and_clear_on_success(): void
    {
        $this->postJson('/api/login', ['email' => 'Maria@Example.com', 'password' => 'wrong'])->assertStatus(401);
        $this->postJson('/api/login', ['email' => 'maria@example.com', 'password' => 'right'])->assertOk();

        $this->postJson('/api/login', ['email' => 'maria@example.com', 'password' => 'wrong'])->assertStatus(401);
        $this->postJson('/api/login', ['email' => 'maria@example.com', 'password' => 'wrong'])->assertStatus(401);
        $this->postJson('/api/login', ['email' => 'maria@example.com', 'password' => 'right'])->assertStatus(429);

        $this->postJson('/api/login', ['email' => 'joy@example.com', 'password' => 'wrong'])->assertStatus(401);
    }
}
