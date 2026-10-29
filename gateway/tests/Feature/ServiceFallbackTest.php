<?php

namespace Tests\Feature;

use App\Exceptions\ServiceUnavailable;
use App\Livewire\Dev\ServiceStatus;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Sleep;
use Livewire\Livewire;
use Tests\TestCase;

class ServiceFallbackTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Sleep::fake();
    }

    public function test_a_down_service_shows_service_unavailable_and_the_others_still_render(): void
    {
        $this->app['env'] = 'local';

        Http::fake(function (Request $request) {
            if (str_contains($request->url(), ':8002')) {
                throw new ConnectionException('Connection refused');
            }

            return Http::response(['status' => 'ok', 'service' => 'up']);
        });

        Livewire::test(ServiceStatus::class)
            ->assertOk()
            ->assertSet('unavailable', ['catalog' => 'Catalog'])
            ->assertSee("The Catalog service isn't responding.")
            ->assertSee('Try again')
            ->assertSee('Identity')
            ->assertSee('Notification');
    }

    public function test_the_section_recovers_when_the_service_is_back(): void
    {
        $this->app['env'] = 'local';
        $down = true;

        Http::fake(function () use (&$down) {
            if ($down) {
                throw new ConnectionException('Connection refused');
            }

            return Http::response(['status' => 'ok']);
        });

        $component = Livewire::test(ServiceStatus::class)->assertSee("isn't responding");

        $down = false;

        $component->call('$refresh')
            ->assertSet('unavailable', [])
            ->assertDontSee("isn't responding");
    }

    public function test_services_page_is_hidden_outside_local(): void
    {
        $this->app['env'] = 'production';

        $this->get('/dev/services')->assertNotFound();
    }

    public function test_an_uncaught_service_unavailable_shows_the_503_page_in_the_layout(): void
    {
        Route::get('/test-unavailable', fn () => throw new ServiceUnavailable('Catalog'))->middleware('web');

        $this->get('/test-unavailable')
            ->assertStatus(503)
            ->assertSee('aria-label="Main"', false)
            ->assertSee("The Catalog service isn't responding.")
            ->assertSee('Back to home');
    }
}
