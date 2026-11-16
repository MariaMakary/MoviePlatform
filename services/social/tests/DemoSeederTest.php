<?php

namespace Tests;

use App\Models\Activity;
use App\Services\ActivityService;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Lumen\Testing\DatabaseTransactions;

class DemoSeederTest extends TestCase
{
    use DatabaseTransactions;

    private const LINA = '0193a7c0-de00-7aaa-8bbb-000000000001';

    private const KARIM = '0193a7c0-de00-7aaa-8bbb-000000000004';

    private const NIGHT = '0193a7e0-de00-7aaa-8bbb-000000000001';

    private function seedDemo(): void
    {
        $this->app->make(DemoSeeder::class)->run();
    }

    public function test_it_creates_follows_a_movie_night_a_chat_and_the_feed_and_can_run_twice(): void
    {
        $this->seedDemo();
        $this->seedDemo();

        $this->assertSame(7, DB::table('follows')->where('follower_id', 'like', '0193a7c0-de00-%')->count());
        $this->assertSame(4, DB::table('participants')->where('movie_night_id', self::NIGHT)->count(), 'The host and 3 guests.');
        $this->assertSame('accepted', DB::table('participants')->where('movie_night_id', self::NIGHT)->where('user_id', self::LINA)->value('status'));
        $this->assertSame(2, DB::table('votes')->where('movie_night_id', self::NIGHT)->count());
        $this->assertSame(3, DB::table('messages')->where('id', 'like', '0193a7e3-de00-%')->count());

        // 12 watches + 12 ratings + 4 reviews + 2 public lists: the private list never reaches the feed.
        $this->assertSame(30, Activity::where('user_id', 'like', '0193a7c0-de00-%')->count());
        $this->assertSame(0, Activity::where('subject_id', '0193a7d2-de00-7aaa-8bbb-000000000003')->count());
    }

    public function test_linas_feed_respects_karims_privacy(): void
    {
        $this->seedDemo();

        $feed = $this->app->make(ActivityService::class)->feedFor(self::LINA)->get();

        $this->assertNotEmpty($feed);
        $this->assertTrue($feed->contains(fn (Activity $a) => $a->user_id === self::KARIM && $a->type->value === 'review'));
        $this->assertFalse($feed->contains(fn (Activity $a) => $a->user_id === self::KARIM && $a->type->value === 'rating'), 'Karim hides his ratings.');
        $this->assertSame('2026-11-14T22:20:00Z', Activity::where('subject_id', '0193a7d0-de00-7aaa-8bbb-000000000012')->first()->created_at->toIso8601ZuluString());
    }
}
