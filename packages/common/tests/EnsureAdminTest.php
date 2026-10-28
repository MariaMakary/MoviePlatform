<?php

namespace MoviePlatform\Common\Tests;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Route;
use MoviePlatform\Common\CommonServiceProvider;
use Orchestra\Testbench\TestCase;

class EnsureAdminTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [CommonServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('jwt.public_key_path', __DIR__.'/Fixtures/jwt/test-public.pem');
    }

    protected function defineRoutes($router): void
    {
        Route::middleware(['jwt', 'admin'])->get('/api/admin/ping', fn () => ['ok' => true]);
        Route::middleware('admin')->get('/api/admin/without-jwt', fn () => ['ok' => true]);
    }

    private function token(string $role): string
    {
        return JWT::encode([
            'sub' => '0193a7c0-1111-7aaa-8bbb-000000000001',
            'role' => $role,
            'name' => 'Maria Makary',
            'iat' => time(),
            'exp' => time() + 7200,
        ], file_get_contents(__DIR__.'/Fixtures/jwt/test-private.pem'), 'RS256');
    }

    public function test_admin_passes(): void
    {
        $this->getJson('/api/admin/ping', ['Authorization' => 'Bearer '.$this->token('admin')])
            ->assertOk()
            ->assertExactJson(['ok' => true]);
    }

    public function test_user_gets_403(): void
    {
        $this->getJson('/api/admin/ping', ['Authorization' => 'Bearer '.$this->token('user')])
            ->assertStatus(403)
            ->assertExactJson(['message' => 'This action is unauthorized.']);
    }

    public function test_missing_token_gets_401_from_jwt(): void
    {
        $this->getJson('/api/admin/ping')
            ->assertStatus(401)
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public function test_admin_without_jwt_middleware_gets_401(): void
    {
        $this->getJson('/api/admin/without-jwt', ['Authorization' => 'Bearer '.$this->token('admin')])
            ->assertStatus(401)
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }
}
