<?php

namespace Tests\Feature;

use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    public function test_identitys_429_shows_its_message_on_the_form(): void
    {
        Http::fake(['*/api/login' => Http::response(['message' => 'Too many attempts. Try again in 42 seconds.'], 429)]);

        Livewire::test(Login::class)
            ->set('email', 'maria@example.com')
            ->set('password', 'secret-password')
            ->call('login')
            ->assertHasErrors(['form' => 'Too many attempts. Try again in 42 seconds.']);
    }

    public function test_the_eleventh_login_from_one_ip_in_a_minute_never_reaches_identity(): void
    {
        Http::fake(['*/api/login' => Http::response(['message' => 'Invalid credentials.'], 401)]);

        for ($i = 0; $i < 10; $i++) {
            Livewire::test(Login::class)->set('email', "user$i@example.com")->set('password', 'x')->call('login')
                ->assertHasErrors(['form' => 'Invalid email or password.']);
        }

        Livewire::test(Login::class)->set('email', 'other@example.com')->set('password', 'x')->call('login')
            ->assertHasErrors('form')
            ->assertSee('Too many attempts.');
        Http::assertSentCount(10);
    }

    public function test_forgot_password_is_limited_per_ip(): void
    {
        Http::fake(['*/api/password/forgot' => Http::response(null, 204)]);

        for ($i = 0; $i < 5; $i++) {
            Livewire::test(ForgotPassword::class)->set('email', "user$i@example.com")->call('send')->assertSet('sent', true);
        }

        Livewire::test(ForgotPassword::class)->set('email', 'other@example.com')->call('send')
            ->assertSet('sent', false)
            ->assertHasErrors('form');
        Http::assertSentCount(5);
    }

    public function test_every_page_is_limited_per_ip(): void
    {
        config(['throttle.web' => ['attempts' => 2, 'minutes' => 1]]);
        Http::fake();
        $this->withoutVite();

        $this->get('/login')->assertOk();
        $this->get('/login')->assertOk();
        $this->get('/login')->assertStatus(429)->assertHeader('Retry-After');
    }
}
