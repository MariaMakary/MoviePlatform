<?php

namespace MoviePlatform\Common\Tests;

use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use MoviePlatform\Common\CommonServiceProvider;
use Orchestra\Testbench\TestCase;

class VerifyJwtTest extends TestCase
{
    private const USER_ID = '0193a7c0-1111-7aaa-8bbb-000000000001';

    protected function getPackageProviders($app): array
    {
        return [CommonServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('jwt.public_key_path', $this->fixture('test-public.pem'));
    }

    protected function defineRoutes($router): void
    {
        Route::middleware('jwt')->get('/api/me', fn (Request $request) => [
            'id' => $request->user()->id,
            'role' => $request->user()->role,
            'name' => $request->user()->name,
            'is_admin' => $request->user()->isAdmin(),
        ]);
    }

    private function fixture(string $name): string
    {
        return __DIR__.'/Fixtures/jwt/'.$name;
    }

    private function claims(array $overrides = []): array
    {
        return array_merge([
            'sub' => self::USER_ID,
            'role' => 'user',
            'name' => 'Maria Makary',
            'iat' => time(),
            'exp' => time() + 7200,
        ], $overrides);
    }

    private function token(array $claims, string $key = 'test-private.pem'): string
    {
        return JWT::encode($claims, file_get_contents($this->fixture($key)), 'RS256');
    }

    private function callWith(?string $token)
    {
        $headers = $token === null ? [] : ['Authorization' => "Bearer $token"];

        return $this->getJson('/api/me', $headers);
    }

    private function assertUnauthenticated($response): void
    {
        $response->assertStatus(401)->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public function test_valid_token_passes_and_sets_the_user(): void
    {
        $this->callWith($this->token($this->claims(['role' => 'admin'])))
            ->assertOk()
            ->assertExactJson(['id' => self::USER_ID, 'role' => 'admin', 'name' => 'Maria Makary', 'is_admin' => true]);
    }

    public function test_missing_authorization_header_is_rejected(): void
    {
        $this->assertUnauthenticated($this->callWith(null));
    }

    public function test_expired_token_is_rejected(): void
    {
        $this->assertUnauthenticated($this->callWith($this->token($this->claims(['iat' => time() - 7300, 'exp' => time() - 100]))));
    }

    public function test_token_expired_within_the_leeway_still_passes(): void
    {
        $this->callWith($this->token($this->claims(['exp' => time() - 10])))->assertOk();
    }

    public function test_tampered_payload_is_rejected(): void
    {
        [$header, , $signature] = explode('.', $this->token($this->claims()));
        $payload = rtrim(strtr(base64_encode(json_encode($this->claims(['role' => 'admin']))), '+/', '-_'), '=');

        $this->assertUnauthenticated($this->callWith("$header.$payload.$signature"));
    }

    public function test_token_signed_with_another_key_is_rejected(): void
    {
        $this->assertUnauthenticated($this->callWith($this->token($this->claims(), 'other-private.pem')));
    }

    public function test_alg_none_is_rejected(): void
    {
        $encode = fn (array $data) => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');

        $this->assertUnauthenticated($this->callWith($encode(['alg' => 'none', 'typ' => 'JWT']).'.'.$encode($this->claims()).'.'));
    }

    public function test_hs256_signed_with_the_public_key_is_rejected(): void
    {
        $token = JWT::encode($this->claims(), file_get_contents($this->fixture('test-public.pem')), 'HS256');

        $this->assertUnauthenticated($this->callWith($token));
    }

    public function test_missing_sub_is_rejected(): void
    {
        $claims = $this->claims();
        unset($claims['sub']);

        $this->assertUnauthenticated($this->callWith($this->token($claims)));
    }

    public function test_missing_role_is_rejected(): void
    {
        $claims = $this->claims();
        unset($claims['role']);

        $this->assertUnauthenticated($this->callWith($this->token($claims)));
    }

    public function test_unknown_role_is_rejected(): void
    {
        $this->assertUnauthenticated($this->callWith($this->token($this->claims(['role' => 'superuser']))));
    }

    public function test_garbage_token_is_rejected(): void
    {
        $this->assertUnauthenticated($this->callWith('not-a-jwt'));
    }
}
