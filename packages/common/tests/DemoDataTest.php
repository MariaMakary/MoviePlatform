<?php

namespace MoviePlatform\Common\Tests;

use LogicException;
use MoviePlatform\Common\Seeding\DemoData;
use Orchestra\Testbench\TestCase;
use RuntimeException;

class DemoDataTest extends TestCase
{
    private function demo(): DemoData
    {
        return DemoData::load(__DIR__.'/Fixtures/demo.json');
    }

    public function test_it_reads_the_file_and_finds_users_movies_and_genres_by_key(): void
    {
        $demo = $this->demo();

        $this->assertSame('password', $demo['password']);
        $this->assertSame('Omar', $demo->user(2)['name']);
        $this->assertSame('0193a7c0-de00-7aaa-8bbb-000000000001', $demo->userId(1));
        $this->assertSame(['0193a7c0-de00-7aaa-8bbb-000000000001', '0193a7c0-de00-7aaa-8bbb-000000000002'], $demo->userIds());
        $this->assertSame('0193a7c1-de00-7aaa-8bbb-000000000001', $demo->movieId('inception'));
        $this->assertSame('0193a7b0-de00-7aaa-8bbb-000000000001', $demo->genreId('drama'));
        $this->assertTrue(isset($demo['movies']));
        $this->assertFalse(isset($demo['nights']));
    }

    public function test_times_are_read_in_utc(): void
    {
        $this->assertSame('2026-11-14T20:20:00Z', DemoData::at('2026-11-14T22:20:00+02:00')->toIso8601ZuluString());
    }

    public function test_it_is_read_only(): void
    {
        $this->expectException(LogicException::class);

        $this->demo()['password'] = 'changed';
    }

    public function test_it_never_loads_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('never seeded in production');

        $this->demo();
    }
}
