<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\AdminSeeder;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Laravel\Lumen\Testing\DatabaseTransactions;
use MoviePlatform\Common\Auth\Role;
use RuntimeException;

class AdminSeederTest extends TestCase
{
    use DatabaseTransactions;

    private const PASSWORD = 'test-admin-password';

    private string $fixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtures = base_path('tests/Fixtures/jwt');
        config([
            'jwt.private_key' => file_get_contents($this->fixtures.'/test-private.pem'),
            'jwt.public_key_path' => $this->fixtures.'/test-public.pem',
            'admin.password' => self::PASSWORD,
        ]);

        User::where('email', 'admin@kaia.com')->delete();
    }

    public function test_the_seeder_creates_one_admin_even_when_run_twice(): void
    {
        (new AdminSeeder)->run();
        (new AdminSeeder)->run();

        $admins = User::where('email', 'admin@kaia.com')->get();
        $this->assertCount(1, $admins);
        $this->assertSame(Role::Admin, $admins[0]->role);
        $this->assertSame('KAIA Admin', $admins[0]->name);
        $this->assertFalse($admins[0]->is_blocked);
    }

    public function test_logging_in_with_the_admin_returns_a_token_with_role_admin(): void
    {
        (new AdminSeeder)->run();

        $this->json('POST', '/api/login', ['email' => 'admin@kaia.com', 'password' => self::PASSWORD]);

        $this->seeStatusCode(200)->seeJson(['role' => 'admin']);
        $token = $this->response->getData(true)['data']['token'];
        $claims = JWT::decode($token, new Key(file_get_contents($this->fixtures.'/test-public.pem'), 'RS256'));
        $this->assertSame('admin', $claims->role);
    }

    public function test_an_existing_account_with_that_email_is_left_unchanged(): void
    {
        $user = User::factory()->create(['email' => 'admin@kaia.com', 'password' => 'their-password']);

        (new AdminSeeder)->run();

        $this->assertSame(Role::User, $user->fresh()->role);
        $this->assertSame(1, User::where('email', 'admin@kaia.com')->count());
    }

    public function test_a_missing_or_short_password_stops_the_seeder(): void
    {
        foreach ([null, '', 'short'] as $password) {
            config(['admin.password' => $password]);

            try {
                (new AdminSeeder)->run();
                $this->fail('The seeder ran without a valid password.');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('secrets/admin_password', $e->getMessage());
            }
        }

        $this->assertSame(0, User::where('email', 'admin@kaia.com')->count());
    }
}
