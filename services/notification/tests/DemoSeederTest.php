<?php

namespace Tests;

use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Lumen\Testing\DatabaseTransactions;

class DemoSeederTest extends TestCase
{
    use DatabaseTransactions;

    private const LINA = '0193a7c0-de00-7aaa-8bbb-000000000001';

    private const OMAR = '0193a7c0-de00-7aaa-8bbb-000000000002';

    public function test_it_replays_the_demo_events_into_notifications_and_can_run_twice(): void
    {
        $this->app->make(DemoSeeder::class)->run();
        $this->app->make(DemoSeeder::class)->run();

        // 5 welcomes + 7 new followers + 3 invitations + 2 answers.
        $this->assertSame(17, DB::table('notifications')->where('user_id', 'like', '0193a7c0-de00-%')->count());

        $lina = DB::table('notifications')->where('user_id', self::LINA)->orderBy('created_at')->get();
        $this->assertSame('welcome', $lina->first()->type);
        $this->assertSame('2026-11-01 09:00:00', $lina->first()->created_at);
        $this->assertSame(['Omar Khoury accepted your invitation', 'Karim Aoun declined your invitation'],
            $lina->where('type', 'movie_night_invite_answered')->pluck('title')->values()->all());
        $this->assertSame(2, $lina->whereNull('read_at')->count(), 'The two newest stay unread.');

        $this->assertSame('Lina Haddad started following you', DB::table('notifications')->where('user_id', self::OMAR)->where('type', 'new_follower')->orderBy('created_at')->value('title'));
    }
}
