<?php

namespace Tests\Feature;

use App\Services\DisplayTime;
use Tests\TestCase;

class DisplayTimeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.display_timezone' => 'Asia/Beirut']);
    }

    public function test_service_date_is_shown_in_beirut_winter_time(): void
    {
        $this->assertSame('2026-11-03 20:20', DisplayTime::fromService('2026-11-03T18:20:00Z')->format('Y-m-d H:i'));
    }

    public function test_service_date_is_shown_in_beirut_summer_time(): void
    {
        $this->assertSame('2026-07-15 21:00', DisplayTime::fromService('2026-07-15T18:00:00Z')->format('Y-m-d H:i'));
    }

    public function test_form_date_is_sent_to_a_service_in_utc(): void
    {
        $this->assertSame('2026-11-07T19:00:00Z', DisplayTime::toService('2026-11-07T21:00'));
    }

    public function test_round_trip_keeps_the_same_moment(): void
    {
        $utc = '2026-11-07T19:00:00Z';

        $this->assertSame($utc, DisplayTime::toService(DisplayTime::fromService($utc)->format('Y-m-d\TH:i')));
    }

    public function test_empty_values_stay_null(): void
    {
        $this->assertNull(DisplayTime::fromService(null));
        $this->assertNull(DisplayTime::toService(''));
    }

    public function test_date_component_shows_beirut_time_and_keeps_utc_in_datetime(): void
    {
        $this->blade('<x-date :value="$value" />', ['value' => '2026-11-03T18:20:00Z'])
            ->assertSee('<time datetime="2026-11-03T18:20:00Z"', false)
            ->assertSee('3 Nov 2026, 20:20');
    }

    public function test_date_component_accepts_a_format(): void
    {
        $this->blade('<x-date :value="$value" format="H:i" />', ['value' => '2026-11-03T18:20:00Z'])
            ->assertSee('20:20')
            ->assertDontSee('Nov');
    }

    public function test_date_component_renders_nothing_without_a_date(): void
    {
        $this->blade('<x-date :value="null" />')->assertDontSee('<time', false);
    }
}
