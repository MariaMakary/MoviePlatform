<?php

namespace Tests;

use Database\Seeders\DemoSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Lumen\Testing\DatabaseTransactions;

class DemoSeederTest extends TestCase
{
    use DatabaseTransactions;

    private const LINA = '0193a7c0-de00-7aaa-8bbb-000000000001';

    private const DEMO_USERS = '0193a7c0-de00-%';

    public function test_it_creates_the_demo_activity_and_can_run_twice(): void
    {
        $someone = (string) Str::uuid();
        DB::table('ratings')->insert(['id' => (string) Str::uuid(), 'user_id' => $someone, 'movie_id' => (string) Str::uuid(), 'score' => 3,
            'created_at' => Carbon::now(), 'updated_at' => Carbon::now()]);

        $this->app->make(DemoSeeder::class)->run();
        $this->app->make(DemoSeeder::class)->run();

        $count = fn (string $table) => DB::table($table)->where('user_id', 'like', self::DEMO_USERS)->count();
        $this->assertSame(12, $count('ratings'));
        $this->assertSame(4, $count('reviews'));
        $this->assertSame(12, $count('watch_history'));
        $this->assertSame(7, $count('watchlist'));
        $this->assertSame(3, $count('lists'));
        $this->assertSame(3, DB::table('list_movie')->where('list_id', '0193a7d2-de00-7aaa-8bbb-000000000001')->count());

        $review = DB::table('reviews')->where('id', '0193a7d1-de00-7aaa-8bbb-000000000001')->first();
        $this->assertSame(self::LINA, $review->user_id);
        $this->assertSame('positive', $review->sentiment_label);
        $this->assertSame(1, DB::table('ratings')->where('user_id', $someone)->count(), 'Other users are left alone.');
    }
}
