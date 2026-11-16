<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Lumen\Testing\DatabaseTransactions;
use RuntimeException;

class DemoSeederTest extends TestCase
{
    use DatabaseTransactions;

    private const LINA = '0193a7c0-de00-7aaa-8bbb-000000000001';

    private function seedDemo(): void
    {
        $this->app->make(DemoSeeder::class)->run();
    }

    public function test_it_creates_the_demo_users_with_their_genres_and_can_run_twice(): void
    {
        $other = User::factory()->create();

        $this->seedDemo();
        $this->seedDemo();

        $lina = User::findOrFail(self::LINA);
        $this->assertSame('lina@example.com', $lina->email);
        $this->assertTrue(Hash::check('password', $lina->password));
        $this->assertSame('user', $lina->role->value);
        $this->assertSame('2026-11-01T09:00:00Z', $lina->created_at->toIso8601ZuluString());
        $this->assertSame(5, User::where('email', 'like', '%@example.com')->where('id', 'like', '0193a7c0-de00-%')->count());
        $this->assertSame(3, DB::table('user_genres')->where('user_id', self::LINA)->count());
        $this->assertNotNull(User::find($other->id), 'Other users are left alone.');
    }

    public function test_it_stops_when_a_demo_email_belongs_to_someone_else(): void
    {
        User::factory()->create(['email' => 'lina@example.com']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('lina@example.com already belongs to another account');

        $this->seedDemo();
    }

    public function test_it_never_runs_in_production(): void
    {
        // Lumen reads APP_ENV from the environment on every call.
        $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'production';
        putenv('APP_ENV=production');

        try {
            $this->seedDemo();
            $this->fail('The seeder ran in production.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('never seeded in production', $e->getMessage());
        } finally {
            $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'testing';
            putenv('APP_ENV=testing');
        }

        $this->assertNull(User::find(self::LINA));
    }
}
