<?php

namespace Tests;

use App\Models\User;
use App\Services\TokenService;
use Illuminate\Http\Request;
use MoviePlatform\Common\Auth\Role;

class JwtMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $fixtures = base_path('tests/Fixtures/jwt');
        config([
            'jwt.private_key' => file_get_contents($fixtures.'/test-private.pem'),
            'jwt.public_key_path' => $fixtures.'/test-public.pem',
        ]);

        $this->app->router->group(['middleware' => 'jwt'], function ($router) {
            $router->get('/api/test-me', fn (Request $request) => response()->json([
                'id' => $request->user()->id,
                'role' => $request->user()->role,
            ]));
        });
    }

    private function token(): string
    {
        $user = (new User(['name' => 'Maria Makary']))
            ->forceFill(['id' => '0193a7c0-1111-7aaa-8bbb-000000000001', 'role' => Role::Admin]);

        return app(TokenService::class)->issue($user)->token;
    }

    public function test_token_issued_by_identity_passes_the_jwt_middleware(): void
    {
        $this->get('/api/test-me', ['Authorization' => 'Bearer '.$this->token()])
            ->seeStatusCode(200)
            ->seeJsonEquals(['id' => '0193a7c0-1111-7aaa-8bbb-000000000001', 'role' => 'admin']);
    }

    public function test_request_without_token_gets_401_json(): void
    {
        $this->get('/api/test-me')
            ->seeStatusCode(401)
            ->seeJsonEquals(['message' => 'Unauthenticated.']);
    }
}
