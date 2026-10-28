<?php

namespace MoviePlatform\Common\Tests;

use Illuminate\Support\Facades\Route;
use MoviePlatform\Common\CommonServiceProvider;
use Orchestra\Testbench\TestCase;

class VerifyServiceTokenTest extends TestCase
{
    private const TOKEN = 'test-service-token';

    protected function getPackageProviders($app): array
    {
        return [CommonServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('internal.service_token', self::TOKEN);
    }

    protected function defineRoutes($router): void
    {
        Route::middleware('service')->get('/api/internal/ping', fn () => ['ok' => true]);
    }

    private function assertUnauthenticated($response): void
    {
        $response->assertStatus(401)->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public function test_correct_token_passes(): void
    {
        $this->getJson('/api/internal/ping', ['X-Service-Token' => self::TOKEN])
            ->assertOk()
            ->assertExactJson(['ok' => true]);
    }

    public function test_missing_token_is_rejected(): void
    {
        $this->assertUnauthenticated($this->getJson('/api/internal/ping'));
    }

    public function test_wrong_token_is_rejected(): void
    {
        $this->assertUnauthenticated($this->getJson('/api/internal/ping', ['X-Service-Token' => 'wrong']));
    }

    public function test_user_jwt_is_not_accepted(): void
    {
        $this->assertUnauthenticated($this->getJson('/api/internal/ping', ['Authorization' => 'Bearer '.self::TOKEN]));
    }

    public function test_everything_is_rejected_when_the_secret_is_not_configured(): void
    {
        config(['internal.service_token' => '']);

        $this->assertUnauthenticated($this->getJson('/api/internal/ping', ['X-Service-Token' => '']));
    }
}
