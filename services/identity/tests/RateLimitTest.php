<?php

namespace Tests;

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Laravel\Lumen\Testing\DatabaseTransactions;

class RateLimitTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'jwt.private_key' => file_get_contents(base_path('tests/Fixtures/jwt/test-private.pem')),
            'jwt.public_key_path' => base_path('tests/Fixtures/jwt/test-public.pem'),
        ]);
        Mail::fake();
        User::factory()->create(['email' => 'maria@example.com']);
    }

    private function login(string $email = 'maria@example.com', string $password = 'wrong-password'): void
    {
        $this->json('POST', '/api/login', ['email' => $email, 'password' => $password]);
    }

    public function test_the_sixth_failed_login_in_a_minute_gets_429_with_retry_after(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->login();
            $this->seeStatusCode(401);
        }

        $this->login();

        $this->seeStatusCode(429);
        $this->seeJsonStructure(['message']);
        $retryAfter = (int) $this->response->headers->get('Retry-After');
        $this->assertGreaterThan(0, $retryAfter);
        $this->assertLessThanOrEqual(60, $retryAfter);
    }

    public function test_even_the_right_password_is_refused_while_locked(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->login();
        }

        $this->login(password: 'password');

        $this->seeStatusCode(429);
    }

    public function test_the_limit_is_per_email_and_ignores_case(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->login('Maria@Example.com');
        }

        $this->login();
        $this->seeStatusCode(429);

        $this->login('someone@example.com');
        $this->seeStatusCode(401);
    }

    public function test_a_successful_login_resets_the_counter(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->login();
        }
        $this->login(password: 'password');
        $this->seeStatusCode(200);

        for ($i = 0; $i < 5; $i++) {
            $this->login();
            $this->seeStatusCode(401);
        }
    }

    public function test_forgot_password_allows_three_requests_per_email(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->json('POST', '/api/password/forgot', ['email' => 'maria@example.com']);
            $this->assertNotSame(429, $this->response->getStatusCode());
        }

        $this->json('POST', '/api/password/forgot', ['email' => 'maria@example.com']);

        $this->seeStatusCode(429);
    }

    public function test_register_and_reset_are_limited_too(): void
    {
        foreach (['/api/register' => 5, '/api/password/reset' => 5] as $uri => $attempts) {
            for ($i = 0; $i < $attempts; $i++) {
                $this->json('POST', $uri, ['email' => 'new@example.com']);
                $this->seeStatusCode(422);
            }

            $this->json('POST', $uri, ['email' => 'new@example.com']);
            $this->seeStatusCode(429);
        }
    }

    public function test_every_other_route_is_limited_per_user(): void
    {
        config(['throttle.api.attempts' => 2]);
        $this->json('POST', '/api/login', ['email' => 'maria@example.com', 'password' => 'password']);
        $headers = ['Authorization' => 'Bearer '.$this->response->json('data.token')];

        $this->json('GET', '/api/me', [], $headers)->seeStatusCode(200);
        $this->json('GET', '/api/me', [], $headers)->seeStatusCode(200);
        $this->json('GET', '/api/me', [], $headers)->seeStatusCode(429);
        $this->assertNotNull($this->response->headers->get('Retry-After'));
    }
}
