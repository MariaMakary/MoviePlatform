<?php

namespace Tests;

use App\Http\Controllers\Api\PasswordResetController;
use App\Mail\ResetPasswordMail;
use App\Models\PasswordResetToken;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Lumen\Testing\DatabaseTransactions;
use Symfony\Component\Mailer\Exception\TransportException;

class PasswordResetTest extends TestCase
{
    use DatabaseTransactions;

    private const LINE_BREAK_EMAIL = "a@b.com\r\nBcc: x@y.com";

    private const INVALID_LINK = ['token' => ['This password reset link is invalid or has expired.']];

    protected function setUp(): void
    {
        parent::setUp();

        $fixtures = base_path('tests/Fixtures/jwt');
        config([
            'jwt.private_key' => file_get_contents($fixtures.'/test-private.pem'),
            'jwt.public_key_path' => $fixtures.'/test-public.pem',
            'mail.gateway_url' => 'http://gateway.test',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function user(array $attributes = []): User
    {
        return User::factory()->create(['name' => 'Maria Makary', 'email' => 'maria@example.com', ...$attributes]);
    }

    private function forgot(string $email = 'maria@example.com'): void
    {
        $this->json('POST', '/api/password/forgot', ['email' => $email]);
    }

    private function reset(string $token, string $password = 'new-password', string $email = 'maria@example.com'): void
    {
        $this->json('POST', '/api/password/reset', [
            'email' => $email, 'token' => $token, 'password' => $password, 'password_confirmation' => $password,
        ]);
    }

    // Asks for a link and returns the token from the emailed URL.
    private function tokenFromEmail(string $email = 'maria@example.com'): string
    {
        Mail::fake();
        $this->forgot($email);

        $url = null;
        Mail::assertSent(ResetPasswordMail::class, function (ResetPasswordMail $mail) use (&$url) {
            $url = $mail->url;

            return true;
        });
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query['token'];
    }

    private function login(string $password): void
    {
        $this->json('POST', '/api/login', ['email' => 'maria@example.com', 'password' => $password]);
    }

    public function test_the_table_matches_the_erd(): void
    {
        $columns = collect(DB::select(
            "select column_name, data_type, character_maximum_length, is_nullable from information_schema.columns
             where table_schema = current_schema() and table_name = 'password_reset_tokens' order by ordinal_position"
        ))->mapWithKeys(fn ($c) => [$c->column_name => implode(' ', array_filter([
            $c->data_type,
            $c->character_maximum_length ? "({$c->character_maximum_length})" : null,
            $c->is_nullable === 'YES' ? 'NULL' : null,
        ]))])->all();

        $this->assertSame([
            'email' => 'character varying (255)',
            'token' => 'character varying (255)',
            'created_at' => 'timestamp without time zone NULL',
        ], $columns);

        $indexes = collect(DB::select("select indexname from pg_indexes where tablename = 'password_reset_tokens'"))->pluck('indexname');
        $this->assertContains('password_reset_tokens_pkey', $indexes);
    }

    public function test_forgot_emails_a_link_to_the_stored_address_and_keeps_only_a_hash(): void
    {
        Mail::fake();
        $this->user();

        $this->forgot('Maria@Example.COM');

        $this->seeStatusCode(200)->seeJsonEquals(['data' => ['message' => PasswordResetController::LINK_SENT]]);

        $sent = null;
        Mail::assertSent(ResetPasswordMail::class, function (ResetPasswordMail $mail) use (&$sent) {
            $sent = $mail;

            return $mail->hasTo('maria@example.com');
        });
        $this->assertStringStartsWith('http://gateway.test/reset-password?token=', $sent->url);
        $this->assertStringEndsWith('&email=maria%40example.com', $sent->url);

        parse_str((string) parse_url($sent->url, PHP_URL_QUERY), $query);
        $stored = PasswordResetToken::find('maria@example.com')->token;
        $this->assertNotSame($query['token'], $stored);
        $this->assertTrue(Hash::check($query['token'], $stored));
    }

    public function test_an_unknown_email_gets_the_same_answer_and_no_mail(): void
    {
        Mail::fake();

        $this->forgot('nobody@example.com');

        $this->seeStatusCode(200)->seeJsonEquals(['data' => ['message' => PasswordResetController::LINK_SENT]]);
        Mail::assertNothingSent();
        $this->assertSame(0, PasswordResetToken::count());
    }

    public function test_asking_again_replaces_the_older_link(): void
    {
        $this->user();
        $old = $this->tokenFromEmail();
        $new = $this->tokenFromEmail();

        $this->assertSame(1, PasswordResetToken::count());

        $this->reset($old);
        $this->seeStatusCode(422)->seeJsonEquals(['message' => 'The given data was invalid.', 'errors' => self::INVALID_LINK]);

        $this->reset($new);
        $this->seeStatusCode(204);
    }

    public function test_the_new_password_works_and_the_old_one_does_not(): void
    {
        $this->user();
        $token = $this->tokenFromEmail();

        $this->reset($token, 'new-password');
        $this->seeStatusCode(204);
        $this->assertSame(0, PasswordResetToken::count());

        $this->login('new-password');
        $this->seeStatusCode(200);

        $this->login('password');
        $this->seeStatusCode(401);
    }

    public function test_a_used_token_gets_422(): void
    {
        $this->user();
        $token = $this->tokenFromEmail();

        $this->reset($token);
        $this->seeStatusCode(204);

        $this->reset($token, 'another-password');
        $this->seeStatusCode(422)->seeJsonEquals(['message' => 'The given data was invalid.', 'errors' => self::INVALID_LINK]);
    }

    public function test_a_token_works_for_less_than_60_minutes(): void
    {
        $this->user();

        Carbon::setTestNow('2026-11-03T18:00:00Z');
        $token = $this->tokenFromEmail();

        Carbon::setTestNow('2026-11-03T19:00:00Z');
        $this->reset($token);
        $this->seeStatusCode(422)->seeJsonEquals(['message' => 'The given data was invalid.', 'errors' => self::INVALID_LINK]);

        Carbon::setTestNow('2026-11-03T18:59:00Z');
        $this->reset($token);
        $this->seeStatusCode(204);
    }

    public function test_a_wrong_token_or_another_email_gets_422_and_changes_nothing(): void
    {
        $this->user();
        $this->user(['email' => 'joe@example.com', 'name' => 'Joe Rohban']);
        $token = $this->tokenFromEmail();

        $this->reset('wrong-token');
        $this->seeStatusCode(422)->seeJsonEquals(['message' => 'The given data was invalid.', 'errors' => self::INVALID_LINK]);

        $this->reset($token, email: 'joe@example.com');
        $this->seeStatusCode(422)->seeJsonEquals(['message' => 'The given data was invalid.', 'errors' => self::INVALID_LINK]);

        $this->reset($token, email: 'nobody@example.com');
        $this->seeStatusCode(422)->seeJsonEquals(['message' => 'The given data was invalid.', 'errors' => self::INVALID_LINK]);

        $this->assertTrue(Hash::check('password', User::firstWhere('email', 'joe@example.com')->password));
        $this->assertSame(1, PasswordResetToken::count());
    }

    public function test_a_social_login_account_can_set_its_first_password(): void
    {
        $this->user(['password' => null]);
        $token = $this->tokenFromEmail();

        $this->reset($token, 'first-password');
        $this->seeStatusCode(204);

        $this->login('first-password');
        $this->seeStatusCode(200);
    }

    public function test_reset_validates_its_fields(): void
    {
        $this->json('POST', '/api/password/reset', [])->seeStatusCode(422);
        $this->assertEqualsCanonicalizing(['email', 'token', 'password'], array_keys($this->response->getData(true)['errors']));

        $this->json('POST', '/api/password/reset', [
            'email' => 'maria@example.com', 'token' => 'x', 'password' => 'short', 'password_confirmation' => 'other',
        ])->seeStatusCode(422);
        $this->assertSame(['password'], array_keys($this->response->getData(true)['errors']));
    }

    public function test_forgot_needs_a_valid_email(): void
    {
        foreach ([[], ['email' => 'not-an-email']] as $body) {
            $this->json('POST', '/api/password/forgot', $body)->seeStatusCode(422);

            $this->assertArrayHasKey('email', $this->response->getData(true)['errors']);
        }
    }

    public function test_an_email_with_a_line_break_is_refused_everywhere_and_sends_no_mail(): void
    {
        Mail::fake();
        $this->user();

        $requests = [
            '/api/register' => ['name' => 'Eve', 'email' => self::LINE_BREAK_EMAIL, 'password' => 'password', 'password_confirmation' => 'password'],
            '/api/login' => ['email' => self::LINE_BREAK_EMAIL, 'password' => 'password'],
            '/api/password/forgot' => ['email' => self::LINE_BREAK_EMAIL],
            '/api/password/reset' => ['email' => self::LINE_BREAK_EMAIL, 'token' => 'x', 'password' => 'password', 'password_confirmation' => 'password'],
        ];

        foreach ($requests as $uri => $body) {
            $this->json('POST', $uri, $body)->seeStatusCode(422);

            $this->assertArrayHasKey('email', $this->response->getData(true)['errors'], $uri);
        }

        Mail::assertNothingSent();
        $this->assertSame(1, User::count());
        $this->assertSame(0, PasswordResetToken::count());
    }

    public function test_forgot_still_answers_200_when_the_mail_server_is_down(): void
    {
        $this->user();
        Mail::shouldReceive('to->send')->andThrow(new TransportException('Connection refused'));

        $this->forgot();

        $this->seeStatusCode(200)->seeJsonEquals(['data' => ['message' => PasswordResetController::LINK_SENT]]);
    }

    public function test_the_mail_really_goes_through_the_lumen_mailer(): void
    {
        config(['mail.default' => 'array']);
        $this->user();

        $this->forgot();
        $this->seeStatusCode(200);

        $messages = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);

        $email = $messages->first()->getOriginalMessage();
        $this->assertSame('maria@example.com', $email->getTo()[0]->getAddress());
        $this->assertSame('Reset your MoviePlatform password', $email->getSubject());
        $this->assertStringContainsString('http://gateway.test/reset-password?token=', $email->getHtmlBody());
    }
}
