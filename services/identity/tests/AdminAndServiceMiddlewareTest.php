<?php

namespace Tests;

use App\Models\User;
use App\Services\TokenService;

class AdminAndServiceMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $fixtures = base_path('tests/Fixtures/jwt');
        config([
            'jwt.private_key' => file_get_contents($fixtures.'/test-private.pem'),
            'jwt.public_key_path' => $fixtures.'/test-public.pem',
            'internal.service_token' => 'test-service-token',
        ]);

        $this->app->router->group(['middleware' => ['jwt', 'admin']], function ($router) {
            $router->get('/api/test-admin', fn () => response()->json(['ok' => true]));
        });

        $this->app->router->group(['middleware' => 'service'], function ($router) {
            $router->get('/api/internal/test-ping', fn () => response()->json(['ok' => true]));
        });
    }

    private function token(string $role): string
    {
        $user = new User(['name' => 'Maria Makary']);
        $user->setKeyType('string')->setIncrementing(false);
        $user->forceFill(['id' => '0193a7c0-1111-7aaa-8bbb-000000000001', 'role' => $role]);

        return app(TokenService::class)->issue($user);
    }

    public function test_admin_token_passes_the_admin_middleware(): void
    {
        $this->get('/api/test-admin', ['Authorization' => 'Bearer '.$this->token('admin')])
            ->seeStatusCode(200)
            ->seeJsonEquals(['ok' => true]);
    }

    public function test_user_token_gets_403_json(): void
    {
        $this->get('/api/test-admin', ['Authorization' => 'Bearer '.$this->token('user')])
            ->seeStatusCode(403)
            ->seeJsonEquals(['message' => 'This action is unauthorized.']);
    }

    public function test_correct_service_token_passes(): void
    {
        $this->get('/api/internal/test-ping', ['X-Service-Token' => 'test-service-token'])
            ->seeStatusCode(200)
            ->seeJsonEquals(['ok' => true]);
    }

    public function test_wrong_service_token_gets_401_json(): void
    {
        $this->get('/api/internal/test-ping', ['X-Service-Token' => 'wrong'])
            ->seeStatusCode(401)
            ->seeJsonEquals(['message' => 'Unauthenticated.']);
    }
}
