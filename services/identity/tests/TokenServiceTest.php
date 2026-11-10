<?php

namespace Tests;

use App\Models\User;
use App\Services\TokenService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use MoviePlatform\Common\Auth\Role;
use RuntimeException;

class TokenServiceTest extends TestCase
{
    private string $fixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtures = base_path('tests/Fixtures/jwt');
        config(['jwt.private_key' => file_get_contents($this->fixtures.'/test-private.pem')]);
    }

    private function user(array $attributes = []): User
    {
        return (new User(['name' => 'Maria Makary', 'email' => 'maria@example.com']))
            ->forceFill(['id' => '0193a7c0-1111-7aaa-8bbb-000000000001', 'role' => Role::User, ...$attributes]);
    }

    private function publicKey(string $name = 'test'): Key
    {
        return new Key(file_get_contents($this->fixtures."/$name-public.pem"), 'RS256');
    }

    public function test_token_has_the_agreed_claims(): void
    {
        $issued = app(TokenService::class)->issue($this->user(['role' => Role::Admin]));

        $claims = JWT::decode($issued->token, $this->publicKey());

        $this->assertSame('0193a7c0-1111-7aaa-8bbb-000000000001', $claims->sub);
        $this->assertSame('admin', $claims->role);
        $this->assertSame('Maria Makary', $claims->name);
        $this->assertSame(1800, $claims->exp - $claims->iat);
        $this->assertEqualsWithDelta(time(), $claims->iat, 5);
        $this->assertSame($claims->exp, $issued->expiresAt->getTimestamp());
    }

    public function test_token_is_signed_with_rs256(): void
    {
        $token = app(TokenService::class)->issue($this->user())->token;

        $header = json_decode(base64_decode(strtr(explode('.', $token)[0], '-_', '+/')), true);

        $this->assertSame('RS256', $header['alg']);
    }

    public function test_token_is_rejected_by_another_public_key(): void
    {
        $token = app(TokenService::class)->issue($this->user())->token;

        $this->expectException(SignatureInvalidException::class);

        JWT::decode($token, $this->publicKey('other'));
    }

    public function test_role_defaults_to_user(): void
    {
        $token = app(TokenService::class)->issue($this->user(['role' => null]))->token;

        $this->assertSame('user', JWT::decode($token, $this->publicKey())->role);
    }

    public function test_missing_private_key_fails_clearly(): void
    {
        config(['jwt.private_key' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('secrets/jwt_private_key');

        app(TokenService::class)->issue($this->user());
    }

    public function test_public_key_path_points_to_the_committed_key(): void
    {
        $this->assertFileExists(config('jwt.public_key_path'));
        $this->assertStringStartsWith('-----BEGIN PUBLIC KEY-----', file_get_contents(config('jwt.public_key_path')));
    }
}
